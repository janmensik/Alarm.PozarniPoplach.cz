<?php

use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Database;

beforeEach(function () {
    // Reset AppData singleton
    $refl = new ReflectionClass(AppData::class);
    $instance = $refl->getProperty('instance');
    $instance->setValue(null, null);

    $this->appData = AppData::getInstance();

    // Create a mock for Database
    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class extends mysqli {
        public function __construct() {
        }
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    // Mock for Smarty
    $this->smarty = new class {
        public $assigns = [];
        public $tpl_vars = [];

        public function assign($key, $value = null) {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $this->assigns[$k] = $v;
                    $this->tpl_vars[$k] = $v;
                }
            } else {
                $this->assigns[$key] = $value;
                $this->tpl_vars[$key] = $value;
            }
        }

        public function display($template) {
            // Do nothing
        }
    };
});

test('activate page rejects POST without CSRF token', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['csrf_token'] = 'valid-token';
    $_POST = [
        'unit_id' => 123,
        'device_name' => 'Test',
        // Missing CSRF token
    ];
    $_GET = ['code' => 'TESTCODE'];

    $this->db->expects($this->any())->method('query')->willReturn(true);
    $this->db->expects($this->any())->method('getRow')->willReturn([
        'status' => 'pending',
        'unit_id' => null,
        'device_uuid' => 'test-uuid'
    ]);

    $APPD = $this->appData;
    $DB = $this->db;
    $Smarty = $this->smarty;

    @session_start();
    ob_start();
    try {
        include __DIR__ . '/../../view/page/activate.php';
    } catch (\Exception $e) {
        // Exit is called in activate.php, which we can't catch easily without runkit or similar,
        // but we can check Smarty assigns before exit
    }
    ob_end_clean();

    expect($Smarty->assigns['error'])->toBe('Neplatný bezpečnostní token (CSRF). Zkuste to prosím znovu.');
    expect(isset($Smarty->assigns['success']))->toBeFalse();
});

test('activate page accepts POST with valid CSRF token and valid pincode', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['csrf_token'] = 'valid-token';
    $_POST = [
        'unit_id' => 123,
        'device_name' => 'Test',
        'csrf_token' => 'valid-token',
        'pincode' => '1234'
    ];
    $_GET = ['code' => 'TESTCODE'];

    $this->db->expects($this->any())->method('query')->willReturn(true);
    // getRow gets called in checkSessionStatus, then for unit pincode
    $this->db->expects($this->any())->method('getRow')->willReturnCallback(function($result) {
        // Since we are mocking query returning true, we just return a map based on context or a general mock.
        // It's tricky to map it without knowing the query in getRow, but we can return an array
        // that satisfies both `checkSessionStatus` and the new `pincode` check.
        return [
            'status' => 'pending',
            'unit_id' => null,
            'device_uuid' => 'test-uuid',
            'pincode' => '1234'
        ];
    });

    $APPD = $this->appData;
    $DB = $this->db;
    $Smarty = $this->smarty;

    @session_start();
    ob_start();
    try {
        include __DIR__ . '/../../view/page/activate.php';
    } catch (\Exception $e) {
        // Exit is called
    }
    ob_end_clean();

    // Test that the form was processed since token was valid
    // The device_code will be TESTCODE and linkSessionToUnit will be called
    expect($Smarty->assigns['success'])->toBeTrue();
});

test('activate page shows error for valid CSRF token but invalid pincode', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['csrf_token'] = 'valid-token';
    $_POST = [
        'unit_id' => 123,
        'device_name' => 'Test',
        'csrf_token' => 'valid-token',
        'pincode' => 'wrong'
    ];
    $_GET = ['code' => 'TESTCODE'];

    $this->db->expects($this->any())->method('query')->willReturn(true);
    // getRow gets called in checkSessionStatus, then for unit pincode
    $this->db->expects($this->any())->method('getRow')->willReturnCallback(function($result) {
        return [
            'status' => 'pending',
            'unit_id' => null,
            'device_uuid' => 'test-uuid',
            'pincode' => '1234'
        ];
    });

    $APPD = $this->appData;
    $DB = $this->db;
    $Smarty = $this->smarty;

    ob_start();
    try {
        include __DIR__ . '/../../view/page/activate.php';
    } catch (\Exception $e) {
        // Exit is called
    }
    ob_end_clean();

    expect($Smarty->assigns['error'])->toBe('Neplatný PIN kód jednotky.');
    expect(isset($Smarty->assigns['success']))->toBeFalse();
});

test('activate page renders in manual entry mode when no code is provided', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = [];
    $_POST = [];

    $this->db->method('query')->willReturn(true);
    $this->db->method('getAllRows')->willReturn([['id' => 1, 'fullname' => 'JSDH Test']]);

    $APPD = $this->appData;
    $DB = $this->db;
    $Smarty = $this->smarty;

    ob_start();
    include __DIR__ . '/../../view/page/activate.php';
    ob_end_clean();

    expect($Smarty->assigns['manual_mode'])->toBeTrue();
    expect($Smarty->assigns['device_code'])->toBeNull();
    expect($Smarty->assigns['error'])->toBeNull();
});

test('activate page shows error when activation code is invalid or expired', function () {
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_GET = ['code' => 'EXPIRED9'];
    $_POST = [];

    $this->db->method('query')->willReturn(true);
    $this->db->method('getRow')->willReturn(null); // No active pending session
    $this->db->method('getAllRows')->willReturn([]);

    $APPD = $this->appData;
    $DB = $this->db;
    $Smarty = $this->smarty;

    ob_start();
    include __DIR__ . '/../../view/page/activate.php';
    ob_end_clean();

    expect($Smarty->assigns['error'])->toBe('Neplatný nebo prošlý aktivační kód.');
    expect($Smarty->assigns['session_data'])->toBeNull();
});

test('activate page shows error when linking session to unit fails', function () {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SESSION['csrf_token'] = 'token-123';
    $_POST = [
        'unit_id' => 10,
        'csrf_token' => 'token-123',
        'pincode' => '1234'
    ];
    $_GET = ['code' => 'CODE1234'];

    // getRow for checkSessionStatus returns pending session
    $this->db->method('getRow')->willReturn([
        'status' => 'pending',
        'unit_id' => null,
        'device_uuid' => 'test-uuid',
        'pincode' => '1234'
    ]);

    // query fails during linkSessionToUnit UPDATE query
    $this->db->expects($this->any())
        ->method('query')
        ->willReturnCallback(function ($query) {
            if (str_contains($query, 'UPDATE alarm_device_session')) {
                return false;
            }
            return true;
        });

    $APPD = $this->appData;
    $DB = $this->db;
    $Smarty = $this->smarty;

    ob_start();
    include __DIR__ . '/../../view/page/activate.php';
    ob_end_clean();

    expect($Smarty->assigns['error'])->toBe('Nepodařilo se autorizovat zařízení.');
});
