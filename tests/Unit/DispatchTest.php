<?php

namespace Tests\Unit;

use Janmensik\Jmlib\Database;
use mysqli;

// We need to require the class since it might not be autoloaded properly depending on composer.json
require_once __DIR__ . '/../../include/class.Dispatch.php';

use PozarniPoplach\Dispatch;

beforeEach(function () {
    // Create a mock for Database since it's required by Dispatch's constructor
    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class extends mysqli {
        public function __construct() {}
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    $this->dispatch = new Dispatch($this->db);
});

test('extractUnitRegistration correctly extracts registration from a valid email string', function () {
    $result = $this->dispatch->extractUnitRegistration('notifikace.A1B2C3@pozarnipoplach.cz');
    expect($result)->toBe('A1B2C3');
});

test('extractUnitRegistration returns null for empty input', function () {
    $result = $this->dispatch->extractUnitRegistration('');
    expect($result)->toBeNull();

    $result = $this->dispatch->extractUnitRegistration([]);
    expect($result)->toBeNull();

    $result = $this->dispatch->extractUnitRegistration(null);
    expect($result)->toBeNull();
});

test('extractUnitRegistration returns null for an invalid email string', function () {
    $result = $this->dispatch->extractUnitRegistration('invalid.email@example.com');
    expect($result)->toBeNull();

    $result = $this->dispatch->extractUnitRegistration('notifikace.A1B2C@pozarnipoplach.cz'); // 5 chars instead of 6
    expect($result)->toBeNull();
});

test('extractUnitRegistration returns extracted code from an array containing a valid email', function () {
    $emails = [
        'invalid@example.com',
        'notifikace.X9Y8Z7@pozarnipoplach.cz',
        'another.invalid@test.com'
    ];
    $result = $this->dispatch->extractUnitRegistration($emails);
    expect($result)->toBe('X9Y8Z7');
});

test('extractUnitRegistration returns null for an array containing only invalid emails', function () {
    $emails = [
        'invalid@example.com',
        'notifikace.A1B2C@pozarnipoplach.cz', // 5 chars
        'another.invalid@test.com'
    ];
    $result = $this->dispatch->extractUnitRegistration($emails);
    expect($result)->toBeNull();
});

test('extractUnitRegistration returns the first match when multiple valid emails are provided', function () {
    $emails = [
        'notifikace.111111@pozarnipoplach.cz',
        'notifikace.222222@pozarnipoplach.cz'
    ];
    $result = $this->dispatch->extractUnitRegistration($emails);
    expect($result)->toBe('111111');
});

test('extractUnitRegistration is case insensitive', function () {
    $result = $this->dispatch->extractUnitRegistration('NoTiFiKaCe.aBcDeF@PoZaRnIpOpLaCh.Cz');
    expect($result)->toBe('aBcDeF');
});
