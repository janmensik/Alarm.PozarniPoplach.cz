<?php

use Janmensik\Jmlib\Database;
use PozarniPoplach\Ad;

beforeEach(function () {
    require_once __DIR__ . '/../../include/class.Ad.php';

    // Create a mock for Database
    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class extends mysqli {
        public function __construct() {}
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    $this->ad = new Ad($this->db);
});

test('getAd returns null when no active ads are found for unit or globally', function () {
    // 1st query: unit-specific ads
    // 2nd query: random global ads
    $this->db->method('query')->willReturn(true);
    $this->db->method('getRowsCount')->willReturn(0);

    // getAllRows returns empty for unit-specific ads
    $this->db->expects($this->once())
        ->method('getAllRows')
        ->willReturn([]);

    // getRow returns false for global ads
    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(false);

    $result = $this->ad->getAd(123);

    expect($result)->toBeNull();
});

test('getAd returns unit-specific ad when available', function () {
    $this->db->method('query')->willReturn(true);

    // getAllRows returns a unit-specific ad
    $this->db->expects($this->once())
        ->method('getAllRows')
        ->willReturn([['id' => 77]]);

    // getRow called inside getAdData
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 77, 'status' => 'active', 'target_link' => ''],
            false
        );

    $result = $this->ad->getAd(123);

    expect($result)->not->toBeNull();
    expect($result['id'])->toBe(77);
});

test('getAd falls back to global ad when no unit-specific ads are available', function () {
    $this->db->method('query')->willReturn(true);

    // Set a cache total to pretend we have results for getRandom
    $this->ad->cache_total = 1;

    // getAllRows returns empty for unit-specific ads
    $this->db->expects($this->once())
        ->method('getAllRows')
        ->willReturn([]);

    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            // First for get() called by getRandom
            ['id' => 42, 'status' => 'active', 'target_link' => ''],
            false, // end of getRandom get() loop

            // Second for get() called by getAdData
            ['id' => 42, 'status' => 'active', 'target_link' => ''],
            false // end of getAdData get() loop
        );

    $result = $this->ad->getAd(123);

    expect($result)->not->toBeNull();
    expect($result['id'])->toBe(42);
    expect($result)->not->toHaveKey('qr_code_data');
});

test('getAd returns ad data with QR code when target_link is present', function () {
    $this->db->method('query')->willReturn(true);

    $this->ad->cache_total = 1;

    // getAllRows returns empty for unit-specific ads
    $this->db->expects($this->once())
        ->method('getAllRows')
        ->willReturn([]);

    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 99, 'status' => 'active', 'target_link' => 'https://example.com/promo'],
            false,

            ['id' => 99, 'status' => 'active', 'target_link' => 'https://example.com/promo'],
            false
        );

    $result = $this->ad->getAd(123);

    expect($result)->not->toBeNull();
    expect($result['id'])->toBe(99);
    expect($result)->toHaveKey('qr_code_data');
    expect(str_contains(base64_decode(explode(',', $result['qr_code_data'])[1] ?? ''), '<svg') || str_contains($result['qr_code_data'], '<svg'))->toBeTrue();
});
