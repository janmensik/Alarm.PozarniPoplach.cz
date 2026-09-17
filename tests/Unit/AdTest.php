<?php

use Janmensik\Jmlib\Database;
use PozarniPoplach\Ad;

require_once __DIR__ . '/../../include/class.Ad.php';

beforeEach(function () {
    // Create a mock for Database
    $this->db = $this->createMock(Database::class);
    // Mock the mysqli object for escape string if needed
    $this->mysqli = new class extends mysqli {
        public function __construct() {
        }
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    $this->ad = new Ad($this->db);
});

test('setAdHit correctly executes insert query', function () {
    $unitId = 123;
    $advertId = 456;

    $expectedQuery = 'INSERT INTO advert_hit (advert_id, unit_id, display_count) VALUES ("456", "123", 1) ON DUPLICATE KEY UPDATE display_count = display_count + 1, last_displayed_at = CURRENT_TIMESTAMP;';

    $this->db->expects($this->once())
        ->method('query')
        ->with($expectedQuery)
        ->willReturn(true);

    $this->ad->setAdHit($unitId, $advertId);
});

test('logLinkHit correctly executes update query', function () {
    $advertId = 456;

    $expectedQuery = 'UPDATE advert_hit SET link_count = link_count + 1 WHERE advert_id = 456';

    $this->db->expects($this->once())
        ->method('query')
        ->with($expectedQuery)
        ->willReturn(true);

    $this->ad->logLinkHit($advertId);
});

test('validate returns errors when status is missing', function () {
    $this->ad->data = ['status' => ''];
    $errors = $this->ad->validate();
    expect($errors)->toHaveKey('status', 'Status is required');
});

test('validate returns empty array when status is present', function () {
    $this->ad->data = ['status' => 'active'];
    $errors = $this->ad->validate();
    expect($errors)->toBeEmpty();
});

test('getAdForDevice returns null when device is not found', function () {
    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(null);

    $result = $this->ad->getAdForDevice('unknown-uuid', 10);
    expect($result)->toBeNull();
});

test('getAdForDevice returns cached ad during active sticky window', function () {
    $futureTime = date('Y-m-d H:i:s', time() + 3600);
    $deviceRow = [
        'ad_probability' => 100,
        'ad_sticky_duration' => 60,
        'current_ad_id' => 55,
        'ad_expires_at' => $futureTime
    ];

    $adRow = [
        'id' => 55,
        'title' => 'Test Ad',
        'status' => 'active',
        'target_link' => '',
        'banner_image_url' => 'https://example.com/banner.png'
    ];

    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls($deviceRow, $adRow, false);

    $result = $this->ad->getAdForDevice('uuid-sticky', 10);
    expect($result)->not->toBeNull();
    expect($result['id'])->toBe(55);
    expect($result['title'])->toBe('Test Ad');
});

test('getAdForDevice returns null (sticky silence) during sticky window when current_ad_id is null', function () {
    $futureTime = date('Y-m-d H:i:s', time() + 3600);
    $deviceRow = [
        'ad_probability' => 50,
        'ad_sticky_duration' => 60,
        'current_ad_id' => null,
        'ad_expires_at' => $futureTime
    ];

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn($deviceRow);

    $result = $this->ad->getAdForDevice('uuid-silence', 10);
    expect($result)->toBeNull();
});

test('getAdTotals calculates total views and clicks', function () {
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 1, 'display_count_total' => 100, 'link_count_total' => 10],
            ['id' => 2, 'display_count_total' => 250, 'link_count_total' => 25],
            false
        );

    $totals = $this->ad->getAdTotals();
    expect($totals['total_views'])->toBe(350);
    expect($totals['total_clicks'])->toBe(35);
});

test('getActiveReport returns report sorted by views descending', function () {
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 1, 'status' => 'active', 'display_count_total' => 50],
            false
        );

    $report = $this->ad->getActiveReport();
    expect($report)->toBeArray();
    expect($report)->toHaveCount(1);
    expect($report[0]['id'])->toBe(1);
});
