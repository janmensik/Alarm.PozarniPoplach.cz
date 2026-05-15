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
        public function __construct() {}
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;
});

test('dispatch returns 401 if unauthorized', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = '';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = '';
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    // We expect NO query because credentials are empty
    
    include __DIR__ . '/../../view/api/dispatch.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Unauthorized');
    // Note: http_response_code(401) is called but hard to test in CLI without headers_list()
});

test('dispatch returns peacetime data when no recent dispatch', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'test-token';
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    // 1. validateDevice query
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnCallback(function() {
            static $count = 0;
            $count++;
            if ($count === 1) {
                // validateDevice result
                return ['unit_id' => 123, 'refresh_token_hash' => hash('sha256', 'test-token')];
            }
            if ($count === 2) {
                 // getLastDispatch (via Modul::get) result - none
                 return false;
            }
            if ($count === 3) {
                // Fallback unit name lookup
                return ['fullname' => 'Test Unit'];
            }
            if ($count === 4) {
                // Ad lookup (Modul::get via getRandom)
                return ['id' => 1, 'status' => 'active', 'target_link' => 'https://example.com'];
            }
            return false;
        });

    $this->db->method('query')->willReturn(true);
    
    include __DIR__ . '/../../view/api/dispatch.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['dispatch_status'])->toBe('peacetime');
    expect($output['unit'])->toBe('Test Unit');
    expect($output)->toHaveKey('ad');
});

test('dispatch returns alarm data when recent dispatch exists', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'test-token';
    $_ENV['GOOGLE_MAPS_API_KEY'] = 'fake-key';
    $_ENV['MAPBOX_API_KEY'] = 'fake-key';
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    // Recent timestamp
    $recent_ts = time() - 60;
    
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnCallback(function() use ($recent_ts) {
            static $count = 0;
            $count++;
            if ($count === 1) {
                return ['unit_id' => 123, 'refresh_token_hash' => hash('sha256', 'test-token')];
            }
            if ($count === 2) {
                // getLastDispatch result
                return [
                    'id' => 456,
                    'dispatched_at_ts' => $recent_ts,
                    'unit_fullname' => 'Test Unit',
                    'event_name' => 'Fire',
                    'gps_latitude' => '50.0',
                    'gps_longitude' => '14.0',
                    'base_latitude' => '50.1',
                    'base_longitude' => '14.1'
                ];
            }
            return false;
        });

    $this->db->method('query')->willReturn(true);
    
    // Mock file_get_contents for Google Maps Streetview check
    // We can't easily mock global functions in PHP unless they are namespaced or we use a library.
    // However, the code uses @file_get_contents which suppresses errors.
    
    include __DIR__ . '/../../view/api/dispatch.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['dispatch_status'])->toBe('alarm');
    expect($output['event'])->toBe('Fire');
});
