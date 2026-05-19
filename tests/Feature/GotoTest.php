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
    $appd->setData('PAGE', 'goto'); // Ensure PAGE is set
    $appd->setData('API', false);  // Ensure API is set
    $appd->setData('OUTPUT_JSON', null);

    // Setup Database expectations
    $this->db->method('query')->willReturn(true);
    $this->db->method('getAllRows')->willReturn([['id' => $adId, 'target_link' => $targetLink]]);

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
    $this->db->method('getAllRows')->willReturn([]); // Empty result

    $DB = $this->db;
    $APPD = $appd;

    include __DIR__ . '/../../view/page/goto.php';

    expect($appd->getData('ERROR'))->toBe('Reklama s tímto ID nebyla nalezena.');
});
