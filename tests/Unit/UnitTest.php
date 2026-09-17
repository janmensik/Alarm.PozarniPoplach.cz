<?php

use Janmensik\Jmlib\Database;
use PozarniPoplach\Unit;

require_once __DIR__ . '/../../include/class.Unit.php';

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

    $this->unit = new Unit($this->db);
});

test('getRegions returns an array of regions', function () {
    $expectedQuery = 'SELECT id, RZPK, title FROM region ORDER BY title ASC';
    $queryResult = 'mocked_query_result'; // arbitrary mock
    $regionsData = [
        ['id' => 1, 'RZPK' => 'A', 'title' => 'Region A'],
        ['id' => 2, 'RZPK' => 'B', 'title' => 'Region B'],
    ];

    $this->db->expects($this->once())
        ->method('query')
        ->with($expectedQuery, 'get_regions')
        ->willReturn($queryResult);

    $this->db->expects($this->once())
        ->method('getAllRows')
        ->with($queryResult)
        ->willReturn($regionsData);

    $result = $this->unit->getRegions();

    expect($result)->toBe($regionsData);
});

test('getRegions returns null when no regions are found', function () {
    $expectedQuery = 'SELECT id, RZPK, title FROM region ORDER BY title ASC';
    $queryResult = 'mocked_query_result'; // arbitrary mock
    $regionsData = [];

    $this->db->expects($this->once())
        ->method('query')
        ->with($expectedQuery, 'get_regions')
        ->willReturn($queryResult);

    $this->db->expects($this->once())
        ->method('getAllRows')
        ->with($queryResult)
        ->willReturn($regionsData);

    $result = $this->unit->getRegions();

    expect($result)->toBeNull();
});

test('validate returns errors when required fields are missing', function () {
    $this->unit->data = [];
    $errors = $this->unit->validate();

    expect($errors)->toHaveKey('fullname', 'Fullname is required');
    expect($errors)->toHaveKey('registration', 'Registration is required');
    expect($errors)->toHaveKey('category', 'Category is required');
});

test('validate returns empty array when required fields are populated', function () {
    $this->unit->data = [
        'fullname' => 'JSDH Příbram',
        'registration' => 'ABC123',
        'category' => 'JPO III'
    ];
    $errors = $this->unit->validate();

    expect($errors)->toBeEmpty();
});
