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
    $this->mysqli = $this->createMock(mysqli::class);
    $this->mysqli->method('real_escape_string')->willReturnArgument(0);
    $this->db->db = $this->mysqli;

    // Default query return
    $this->db->method('query')->willReturn(true);

    $this->dispatch = new Dispatch($this->db);
});

test('getDispatch returns null for empty id', function () {
    expect($this->dispatch->getDispatch(null))->toBeNull();
    expect($this->dispatch->getDispatch(0))->toBeNull();
});

test('getDispatch returns data with vehicles', function () {
    $dispatch_id = 456;
    
    // Modul::get calls getRow in a loop
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => $dispatch_id, 'event' => 'Fire'], // Result of get() loop 1
            false // Result of get() loop end
        );
        
    $this->db->expects($this->any())
        ->method('getAllRows')
        ->willReturnOnConsecutiveCalls(
            [['fullname' => 'Vehicle 1']], // unit_vehicles
            [['fullname' => 'Other 1']]    // other_vehicles
        );

    $this->db->method('getRowsCount')->willReturn(1);

    $result = $this->dispatch->getDispatch($dispatch_id);

    expect($result)->not->toBeNull();
    expect($result['id'])->toBe($dispatch_id);
    expect($result['unit_vehicles'])->toHaveCount(1);
    expect($result['other_vehicles'])->toHaveCount(1);
});

test('getLastDispatch returns null when no dispatch found', function () {
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturn(false);

    $result = $this->dispatch->getLastDispatch(123);
    expect($result)->toBeNull();
});

test('checkUnitPincode returns unit_id for valid pincode', function () {
    $this->db->expects($this->once())
        ->method('getResult')
        ->willReturn(789);

    $result = $this->dispatch->checkUnitPincode('1234');
    expect($result)->toBe(789);
});

test('checkUnitPincode returns null for invalid pincode', function () {
    $this->db->expects($this->once())
        ->method('getResult')
        ->willReturn(null);

    $result = $this->dispatch->checkUnitPincode('wrong');
    expect($result)->toBeNull();
});

test('getRandomDispatch returns a dispatch', function () {
    $this->db->expects($this->any())
        ->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 999, 'event' => 'Random'], // Result of getRandom loop 1
            false, // Result of getRandom loop end
            ['id' => 999, 'event' => 'Random'], // Result of getDispatch -> getId loop 1
            false // Result of getDispatch -> getId loop end
        );
    
    $this->db->expects($this->any())
        ->method('getAllRows')
        ->willReturnOnConsecutiveCalls(
            [['fullname' => 'V1']], // unit_vehicles
            [['fullname' => 'O1']]  // other_vehicles
        );

    $this->db->method('getRowsCount')->willReturn(1);

    $result = $this->dispatch->getRandomDispatch(123);
    expect($result['id'])->toBe(999);
});

test('beautifulLastDispatch returns beautified data from cache', function () {
    $dispatch = [
        'id' => 123,
        'unit_fullname' => 'JSDH Příbram',
        'event_name' => 'Požár',
        'event_subtype_icon' => 'fa-fire',
        'address_city' => 'Příbram',
        'address_city_part' => 'Příbram', // same as city
        'has_streetview' => 1,
        'directions_distance' => 10.5,
        'directions_duration' => 15,
        'directions_polyline' => 'abc',
        'gps_latitude' => 50.1,
        'gps_longitude' => 14.1
    ];

    $_ENV['GOOGLE_MAPS_API_KEY'] = 'fake-key';

    $result = $this->dispatch->beautifulLastDispatch($dispatch);

    expect($result['unit'])->toBe('JSDH Příbram');
    expect($result['event'])->toBe('Požár');
    expect($result['event_icon'])->toBe('fa-fire');
    expect($result['address_city_part'])->toBeNull();
    expect($result['directions']['distance'])->toBe(10.5);
    expect($result['streetview_available'])->toBeTrue();
});

test('createTestAlarm creates a new dispatch', function () {
    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn([
            'id' => 123,
            'fullname' => 'Unit 1',
            'base_latitude' => 50.0,
            'base_longitude' => 14.0,
            'unit_vehicle_id' => 500,
            'unit_vehicle_name' => 'Tatra',
            'unit_vehicle_callsign' => 'T123',
            'unit_vehicle_type_id' => 1,
            'region_id' => 10,
            'region_title' => 'Středočeský'
        ]);

    $this->db->method('getId')->willReturn(999);

    $result = $this->dispatch->createTestAlarm(123);
    expect($result)->toBe(999);
});

test('parseDispatchHtml correctly parses a sample dispatch email', function () {
    $html = '<div>
    JSDH Testovaci Lhota
    <b><big>POŽÁR - Lesní požár</big></b><br>
    KRAJ: <b>Středočeský</b><br>
    OBEC: <b>Testovaci Lhota</b> (okr.: Praha-západ)<br>
    ČÁST: <b>Horní Lhota</b><br>
    ULICE: <b>V Zahradách</b><br>
    Č.P.: <b>123</b><br>
    GPS: <b>50.123456, 14.123456</b><br>
    OBJEKT: <b>Les</b><br>
    UPŘESNĚNÍ:<br><b>u křížku</b><br>
    CO SE STALO:<br><b>hoří tráva a nízký porost</b><br>
    OZNÁMIL: <b>Jan Novák</b><br>
    Telefon: <b>123456789</b><br>
    TECHNIKA JSDH Testovaci Lhota:<br>
    <big>CAS 20 - S1R - JSD 123<br>DA - L1Z - JSD 124</big><br>
    <i>TECHNIKA dalších jednotek PO:</i><br>
    <big>HZS Kladno: CAS 20 - S2Z - HZS 121<br>JSDH Jiná Lhota: CAS 32 - T815 - JSD 125</big><br>
    <small><i>Událost č. 123456789 - odbavil Dispečer 1 - 28.05.2026 14:00:00</i></small>
</div>';

    $result = $this->dispatch->parseDispatchHtml($html);

    expect($result['unit'])->toBe('JSDH Testovaci Lhota');
    expect($result['event'])->toBe('POŽÁR');
    expect($result['event_sub'])->toBe('Lesní požár');
    expect($result['address']['region'])->toBe('Středočeský');
    expect($result['address']['city'])->toBe('Testovaci Lhota');
    expect($result['address']['district'])->toBe('Praha-západ');
    expect($result['address']['city_part'])->toBe('Horní Lhota');
    expect($result['address']['street'])->toBe('V Zahradách');
    expect($result['address']['house_number'])->toBe('123');
    expect($result['address']['gps_latitude'])->toBe('50.123456');
    expect($result['address']['gps_longitude'])->toBe('14.123456');
    expect($result['object_description'])->toBe('Les');
    expect($result['clarification'])->toBe('u křížku');
    expect($result['situation'])->toBe('hoří tráva a nízký porost');
    expect($result['notifier'])->toBe('Jan Novák');
    expect($result['notifier_phone'])->toBe('123456789');
    expect($result['dispatch_id'])->toBe('123456789');
    expect($result['dispatched_by'])->toBe('Dispečer 1');
    expect($result['dispatched_at'])->toBe('28.05.2026 14:00:00');
    
    expect($result['unit_vehicles'])->toHaveCount(2);
    expect($result['unit_vehicles'][0]['fullname'])->toBe('CAS 20 - S1R - JSD 123');
    expect($result['unit_vehicles'][0]['callsign'])->toBe('JSD 123');
    
    expect($result['other_vehicles'])->toHaveCount(2);
    expect($result['other_vehicles'][0]['unit'])->toBe('HZS Kladno');
    expect($result['other_vehicles'][0]['callsign'])->toBe('HZS 121');
});

test('linkParsedDispatch correctly links data with database records', function () {
    $data = [
        'unit' => 'JSDH Testovaci Lhota',
        'event' => 'POŽÁR',
        'event_sub' => 'Lesní požár',
        'address' => [
            'region' => 'Středočeský'
        ],
        'unit_vehicles' => [
            ['fullname' => 'CAS 20 - S1R - JSD 123', 'callsign' => 'JSD 123', 'vehicle_type_code' => 'S1R']
        ]
    ];

    $this->db->expects($this->any())
        ->method('query')
        ->willReturn(true);

    $this->db->expects($this->any())
        ->method('getAllRows')
        ->willReturnOnConsecutiveCalls(
            [['id' => 1, 'code' => 'S1R', 'type' => 'CAS', 'icon' => 'fire']], // vehicle_types
            [['id' => 10, 'rzpk' => 'S', 'title' => 'Středočeský']], // regions
            [['id' => 100, 'name' => 'POŽÁR', 'icon' => 'fire', 'parent_id' => null, 'level' => 1], ['id' => 101, 'name' => 'Lesní požár', 'icon' => 'tree', 'parent_id' => 100, 'level' => 2]], // event_types
            [['id' => 500, 'callsign' => 'JSD 123', 'name' => 'Tatra', 'vehicle_type_id' => 1]] // unit_vehicles batch
        );

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(['id' => 123, 'fullname' => 'JSDH Testovaci Lhota']); // unit lookup

    $result = $this->dispatch->linkParsedDispatch($data, 'REG123');

    expect($result['unit_id'])->toBe(123);
    expect($result['address']['region_id'])->toBe(10);
    expect($result['event_type']['id'])->toBe(100);
    expect($result['event_subtype']['id'])->toBe(101);
    expect($result['unit_vehicles'][0]['unit_vehicle_id'])->toBe(500);
});

test('prepareSave correctly prepares data for database insertion', function () {
    $data = [
        'dispatch_id' => '123456789',
        'unit_id' => 123,
        'plaindata' => '<html>...</html>',
        'unit' => 'JSDH Testovaci Lhota',
        'event_type' => ['id' => 100],
        'event_subtype' => ['id' => 101],
        'event' => 'POŽÁR',
        'event_sub' => 'Lesní požár',
        'address' => [
            'region_id' => 10,
            'city' => 'Testovaci Lhota',
            'gps_latitude' => 50.123,
            'gps_longitude' => 14.123
        ],
        'dispatched_at' => '28.05.2026 14:00:00',
        'other_vehicles' => [
            ['fullname' => 'Other 1', 'unit' => 'HZS Kladno']
        ],
        'unit_vehicles' => [
            ['fullname' => 'Unit 1', 'unit_vehicle_id' => 500]
        ]
    ];

    $result = $this->dispatch->prepareSave($data);

    expect($result['unit_id'])->toBe('"123"');
    expect($result['dispatch_identification'])->toBe('"123456789"');
    expect($result['event_id'])->toBe('"100"');
    expect($result['event_subtype_id'])->toBe('"101"');
    expect($result['address_city'])->toBe('"Testovaci Lhota"');
    expect($result['gps_latitude'])->toBe('"50.123"');
    expect($result['other_vehicles'])->toHaveCount(1);
    expect($result['unit_vehicles'])->toHaveCount(1);
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
