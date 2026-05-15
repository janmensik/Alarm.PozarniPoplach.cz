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
    
    include __DIR__ . '/../../view/api/dispatch.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Unauthorized');
});

test('dispatch returns peacetime data when no recent dispatch', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'test-token';
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['unit_id' => 123, 'refresh_token_hash' => hash('sha256', 'test-token')], // validateDevice
            false, // getLastDispatch
            ['fullname' => 'Test Unit'], // Fallback unit name
            [ // getAdForDevice: device lookup
                'ad_probability' => 100, 
                'ad_sticky_duration' => 240, 
                'current_ad_id' => null, 
                'ad_expires_at' => null
            ],
            ['id' => 1, 'status' => 'active', 'target_link' => 'https://example.com'], // getAdForDevice: random pick
            false, // Modul::get loop end
            ['id' => 1, 'status' => 'active', 'target_link' => 'https://example.com'], // getAdData lookup
            false // Modul::get loop end
        );

    $this->db->method('query')->willReturn(true);
    
    include __DIR__ . '/../../view/api/dispatch.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['dispatch_status'])->toBe('peacetime');
    expect($output['unit'])->toBe('Test Unit');
    expect($output)->toHaveKey('ad');
    expect($output['ad'])->not->toBeNull();
    expect($output['ad']['id'])->toBe(1);
});

test('dispatch returns alarm data when recent dispatch exists', function () {
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'test-token';
    $_ENV['GOOGLE_MAPS_API_KEY'] = 'fake-key';
    $_ENV['MAPBOX_API_KEY'] = 'fake-key';
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    $recent_ts = time() - 60;
    
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['unit_id' => 123, 'refresh_token_hash' => hash('sha256', 'test-token')], // validateDevice
            [ // getLastDispatch result
                'id' => 456,
                'dispatched_at_ts' => $recent_ts,
                'unit_fullname' => 'Test Unit',
                'event_name' => 'Fire',
                'gps_latitude' => '50.0',
                'gps_longitude' => '14.0',
                'base_latitude' => '50.1',
                'base_longitude' => '14.1'
            ],
            false // Modul::get loop end
        );

    $this->db->method('query')->willReturn(true);
    
    include __DIR__ . '/../../view/api/dispatch.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['dispatch_status'])->toBe('alarm');
    expect($output['event'])->toBe('Fire');
});
