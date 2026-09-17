<?php

namespace Tests\Feature;

use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Database;
use PozarniPoplach\Dispatch;

require_once __DIR__ . '/../../include/class.Dispatch.php';

beforeEach(function () {
    clearAppData();

    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class {
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    $this->smarty = new class {
        public array $assigns = [];

        public function assign($key, $value = null): void {
            if (is_array($key)) {
                foreach ($key as $k => $v) {
                    $this->assigns[$k] = $v;
                }
            } else {
                $this->assigns[$key] = $value;
            }
        }
    };

    $_GET = [];
});

test('alarm page sets PAGE to alarm and assigns assetsVersion', function () {
    $APPD = AppData::getInstance();
    $DB = $this->db;
    $Smarty = $this->smarty;

    include __DIR__ . '/../../view/page/alarm.php';

    expect($APPD->getData('PAGE'))->toBe('alarm');
    expect($Smarty->assigns)->toHaveKey('assetsVersion');
    expect($Smarty->assigns['assetsVersion'])->toBeString();
    expect(strlen($Smarty->assigns['assetsVersion']))->toBe(32);
    expect($Smarty->assigns)->not->toHaveKey('data');
});

test('alarm page handles valid legacy pincode and assigns dispatch data', function () {
    $_GET['pincode'] = '1234';

    $APPD = AppData::getInstance();
    $DB = $this->db;
    $Smarty = $this->smarty;

    $dispatchMock = $this->getMockBuilder(Dispatch::class)
        ->disableOriginalConstructor()
        ->onlyMethods(['checkUnitPincode', 'getLastDispatch', 'beautifulLastDispatch'])
        ->getMock();

    $dispatchMock->expects($this->once())
        ->method('checkUnitPincode')
        ->with('1234', true)
        ->willReturn(42);

    $dispatchMock->expects($this->once())
        ->method('getLastDispatch')
        ->with(42)
        ->willReturn(['id' => 100, 'unit_fullname' => 'JSDH Test']);

    $dispatchMock->expects($this->once())
        ->method('beautifulLastDispatch')
        ->with(['id' => 100, 'unit_fullname' => 'JSDH Test'])
        ->willReturn(['id' => 100, 'unit' => 'JSDH Test', 'event' => 'Fire']);

    $Dispatch = $dispatchMock;

    include __DIR__ . '/../../view/page/alarm.php';

    expect($Smarty->assigns)->toHaveKey('data');
    expect($Smarty->assigns['data']['unit'])->toBe('JSDH Test');
    expect($Smarty->assigns['data']['event'])->toBe('Fire');
});

test('alarm page ignores invalid legacy pincode without assigning data', function () {
    $_GET['pincode'] = 'invalid-pin';

    $APPD = AppData::getInstance();
    $DB = $this->db;
    $Smarty = $this->smarty;

    $this->db->expects($this->once())
        ->method('getResult')
        ->willReturn(null);

    include __DIR__ . '/../../view/page/alarm.php';

    expect($Smarty->assigns)->not->toHaveKey('data');
    expect($Smarty->assigns)->toHaveKey('assetsVersion');
});
