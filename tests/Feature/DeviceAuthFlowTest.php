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

test('device-poll returns status', function () {
    $_GET = ['code' => 'ABCDEFGH'];
    
    $this->db->expects($this->once())
        ->method('query')
        ->willReturn(true);
    
    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(['status' => 'pending', 'unit_id' => null, 'device_uuid' => 'test-uuid']);
        
    $APPD = $this->appData;
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
        
    $APPD = $this->appData;
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
            'refresh_token_hash' => hash('sha256', 'test-token')
        ]);
        
    $APPD = $this->appData;
    $DB = $this->db;
    
    include __DIR__ . '/../../view/api/device-validate.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['success'])->toBeTrue();
    expect($output['unit_id'])->toBe(123);
});
