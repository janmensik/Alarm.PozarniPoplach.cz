<?php

use Janmensik\Jmlib\AppData;

beforeEach(function () {
    clearAppData();
});

test('version api returns success and a hash', function () {
    $APPD = AppData::getInstance();
    
    include __DIR__ . '/../../view/api/version.php';
    
    $output = json_decode($APPD->getData('OUTPUT_JSON'), true);
    
    expect($output['success'])->toBeTrue();
    expect($output['version'])->toBeString();
    expect(strlen($output['version']))->toBe(32); // MD5 length
});

test('version hash changes when a file is updated', function () {
    $APPD = AppData::getInstance();
    
    // First run
    include __DIR__ . '/../../view/api/version.php';
    $version1 = json_decode($APPD->getData('OUTPUT_JSON'), true)['version'];
    
    // Reset AppData for second run
    $APPD = clearAppData();
    
    // Simulate file update by changing mtime of one of the tracked files if it exists
    $file = __DIR__ . '/../../tpl/page.alarm.html';
    if (file_exists($file)) {
        $original_mtime = filemtime($file);
        // Set mtime to something else
        touch($file, $original_mtime + 1);
        
        try {
            include __DIR__ . '/../../view/api/version.php';
            $version2 = json_decode($APPD->getData('OUTPUT_JSON'), true)['version'];
            
            expect($version1)->not->toBe($version2);
        } finally {
            // Restore original mtime
            touch($file, $original_mtime);
        }
    } else {
        $this->markTestSkipped('Tracked file not found for modification test.');
    }
});
