<?php

use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Database;
use PozarniPoplach\DeviceAuth;

require_once __DIR__ . '/../Pest.php';

beforeEach(function () {
    $this->db = $this->createMock(Database::class);
    // Mock the mysqli object for escape string
    $this->mysqli = new class {
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;
    $this->appd = clearAppData();
    
    // Clear credentials
    unset($_SERVER['HTTP_X_DEVICE_UUID']);
    unset($_SERVER['HTTP_X_DEVICE_TOKEN']);
    unset($_GET['uuid']);
    unset($_POST['uuid']);
});

test('calendar api returns 401 if unauthorized', function () {
    $this->db->method('getRow')->willReturn(null);
    $DB = $this->db;

    ob_start();
    include __DIR__ . '/../../view/api/calendar.php';
    ob_end_clean();

    expect(http_response_code())->toBe(401);
    $output = json_decode($this->appd->getData('OUTPUT_JSON'), true);
    expect($output['success'])->toBeFalse();
    expect($output['error'])->toBe('Unauthorized');
});

test('calendar api returns calendar events for authorized device', function () {
    $tempIcs = tempnam(sys_get_temp_dir(), 'ics');
    file_put_contents($tempIcs, "BEGIN:VCALENDAR\nVERSION:2.0\nEND:VCALENDAR");

    // 1. Mock DeviceAuth validation (getRow called in validateDevice)
    // 2. Mock Unit->getId (getRow called in Modul->get via getId)
    // 3. Terminate loop (getRow called again in Modul->get)
    $this->db->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['unit_id' => 1, 'refresh_token_hash' => hash('sha256', 'valid_token')],
            ['id' => 1, 'fullname' => 'Test Unit', 'calendar_url' => $tempIcs],
            null
        );

    $this->db->method('query')->willReturn(true);
    
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'test-uuid';
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'valid_token';
    
    $DB = $this->db;

    ob_start();
    include __DIR__ . '/../../view/api/calendar.php';
    ob_end_clean();

    expect(http_response_code() === false || http_response_code() === 200)->toBeTrue();
    $output = json_decode($this->appd->getData('OUTPUT_JSON'), true);
    expect($output)->toBeArray();

    unlink($tempIcs);
});
