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
