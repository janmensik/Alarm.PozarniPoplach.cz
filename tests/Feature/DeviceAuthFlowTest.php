<?php

use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Database;

beforeEach(function () {
    clearAppData();

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
});

test('device-poll returns status', function () {
    $_GET = ['code' => 'ABCDEFGH'];

    $this->db->expects($this->once())
        ->method('query')
        ->willReturn(true);

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(['status' => 'pending', 'unit_id' => null, 'device_uuid' => 'test-uuid']);

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-poll.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);

    expect($output['success'])->toBeTrue();
    expect($output['status'])->toBe('pending');
});

test('device-authorize completes authorization', function () {
    $_POST = ['code' => 'ABCDEFGH'];

    $this->db->method('query')->willReturn(true);

    // First call to checkSessionStatusWithExtraFields
    // Second call to INSERT into alarm_device_authorized
    // Third call to DELETE from alarm_device_session

    $this->db->expects($this->exactly(3))
        ->method('query')
        ->willReturn(true);

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn([
            'status' => 'linked',
            'unit_id' => 123,
            'device_uuid' => 'test-uuid',
            'device_name' => 'Test Kiosk'
        ]);

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-authorize.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);

    expect($output['success'])->toBeTrue();
    expect($output)->toHaveKey('refresh_token');
    expect($output['unit_id'])->toBe(123);
});

test('device-validate validates token', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'test-token';

    $this->db->method('query')->willReturn(true);

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn([
            'unit_id' => 123,
            'refresh_token_hash' => hash('sha256', 'test-token'),
            'last_seen_ts' => null
        ]);

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-validate.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);

    expect($output['success'])->toBeTrue();
    expect($output['unit_id'])->toBe(123);
});

test('device-poll returns error when code parameter is missing', function () {
    $_GET = [];

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-poll.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Missing device code');
});

test('device-poll returns expired when session is expired or not found', function () {
    $_GET = ['code' => 'EXPIRED1'];

    $this->db->expects($this->once())->method('query')->willReturn(true);
    $this->db->expects($this->once())->method('getRow')->willReturn(null);

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-poll.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['status'])->toBe('expired');
    expect($output['error'])->toBe('Session expired or not found');
});

test('device-authorize returns error when code parameter is missing', function () {
    $_POST = [];
    $_GET = [];

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-authorize.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Missing device code');
});

test('device-authorize returns error when session is not linked', function () {
    $_POST = ['code' => 'NOTLINKED'];

    $this->db->expects($this->once())->method('query')->willReturn(true);
    $this->db->expects($this->once())->method('getRow')->willReturn([
        'status' => 'pending',
        'unit_id' => null,
        'device_uuid' => 'test-uuid',
        'device_name' => 'Kiosk'
    ]);

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-authorize.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Authorization failed or session not linked');
});

test('device-validate returns error when credentials are missing', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = '';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = '';

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-validate.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Missing parameters');
});

test('device-validate returns error when token is invalid', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'wrong-token';

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn([
            'unit_id' => 123,
            'refresh_token_hash' => hash('sha256', 'actual-valid-token'),
            'last_seen_ts' => null
        ]);

    $APPD = AppData::getInstance();
    $DB = $this->db;

    include __DIR__ . '/../../view/api/device-validate.php';

    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Invalid token or device');
});
