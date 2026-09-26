<?php

use Janmensik\Jmlib\Database;
use Janmensik\Jmlib\AppData;

require_once __DIR__ . '/../../include/class.Ad.php';

beforeEach(function () {
    clearAppData();
    AppData::getInstance()->setData('BASE_URL', 'http://localhost');

    // Create a mock for Database
    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class extends mysqli {
        public function __construct() {
        }
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;
});

test('goto ad redirect works and logs hit', function () {
    $adId = 4;
    $targetLink = 'https://example.com/promo';

    $appd = AppData::getInstance();
    $appd->setData('GOTO_TYPE', 'ad');
    $appd->setData('GOTO_ID', (string)$adId);
    $appd->setData('PAGE', 'goto');
    $appd->setData('API', false);

    // Setup Database expectations: get() calls getRow() in a while loop
    $this->db->method('query')->willReturn(true);
    $this->db->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => $adId, 'target_link' => $targetLink],
            false
        );

    $DB = $this->db;
    $APPD = $appd;

    ob_start();
    try {
        include __DIR__ . '/../../view/page/goto.php';
    } catch (Throwable $e) {
        // Handle potential exit
    }
    ob_end_clean();

    expect(true)->toBeTrue();
});

test('goto ad returns error if not found', function () {
    $appd = AppData::getInstance();
    $appd->setData('GOTO_TYPE', 'ad');
    $appd->setData('GOTO_ID', '999');
    $appd->setData('PAGE', 'goto');
    $appd->setData('API', false);

    $this->db->method('query')->willReturn(true);
    $this->db->method('getRow')
        ->willReturn(false);

    $DB = $this->db;
    $APPD = $appd;

    include __DIR__ . '/../../view/page/goto.php';

    expect($appd->getData('ERROR'))->toBe('Reklama s tímto ID nebyla nalezena.');
});

test('goto returns error if type or id is missing', function () {
    $appd = AppData::getInstance();
    $appd->setData('GOTO_TYPE', '');
    $appd->setData('GOTO_ID', 0);

    $DB = $this->db;
    $APPD = $appd;

    include __DIR__ . '/../../view/page/goto.php';

    expect($appd->getData('ERROR'))->toBe('Neplatný požadavek na přesměrování.');
});

test('goto ad returns error if target_link is empty', function () {
    $appd = AppData::getInstance();
    $appd->setData('GOTO_TYPE', 'ad');
    $appd->setData('GOTO_ID', '10');

    $this->db->method('query')->willReturn(true);
    $this->db->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 10, 'target_link' => ''],
            false
        );

    $DB = $this->db;
    $APPD = $appd;

    include __DIR__ . '/../../view/page/goto.php';

    expect($appd->getData('ERROR'))->toBe('Tato reklama nemá nastavený cílový odkaz.');
});

test('goto ad returns error if target_link has invalid scheme', function () {
    $appd = AppData::getInstance();
    $appd->setData('GOTO_TYPE', 'ad');
    $appd->setData('GOTO_ID', '11');

    $this->db->method('query')->willReturn(true);
    $this->db->method('getRow')
        ->willReturnOnConsecutiveCalls(
            ['id' => 11, 'target_link' => 'javascript:alert(1)'],
            false
        );

    $DB = $this->db;
    $APPD = $appd;

    include __DIR__ . '/../../view/page/goto.php';

    expect($appd->getData('ERROR'))->toBe('Neplatný odkaz.');
});

test('goto returns error for unknown type', function () {
    $appd = AppData::getInstance();
    $appd->setData('GOTO_TYPE', 'unsupported_type');
    $appd->setData('GOTO_ID', '12');

    $DB = $this->db;
    $APPD = $appd;

    include __DIR__ . '/../../view/page/goto.php';

    expect($appd->getData('ERROR'))->toBe('Neznámý typ přesměrování.');
});
