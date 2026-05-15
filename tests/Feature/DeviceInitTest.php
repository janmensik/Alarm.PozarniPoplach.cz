<?php

use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Database;
use PozarniPoplach\DeviceAuth;

beforeEach(function () {
    // Reset AppData singleton
    $refl = new ReflectionClass(AppData::class);
    $instance = $refl->getProperty('instance');
    $instance->setValue(null, null);
    
    $this->appData = AppData::getInstance();
    
    // Create a mock for Database
    $this->db = $this->createMock(Database::class);
    // Mock the mysqli object for escape string
    // We use an anonymous class to avoid "object is already closed" issues with procedural mysqli functions
    $this->mysqli = new class extends mysqli {
        public function __construct() {}
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;
});

test('device-init returns error if uuid is missing', function () {
    $_POST = [];
    $_GET = [];
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    include __DIR__ . '/../../view/api/device-init.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Missing device UUID');
});

test('device-init initializes session with uuid', function () {
    $_POST = ['uuid' => 'test-uuid'];
    
    $APPD = $this->appData;
    $DB = $this->db;
    
    // We expect a query to cleanup and then an insert
    // But DeviceAuth::initSession calls $this->DB->query twice.
    
    $this->db->expects($this->exactly(2))
        ->method('query')
        ->willReturn(true);
        
    include __DIR__ . '/../../view/api/device-init.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['success'])->toBeTrue();
    expect($output)->toHaveKey('device_code');
    expect($output)->toHaveKey('qr_code_data');
});
