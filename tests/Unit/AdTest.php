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

