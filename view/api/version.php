<?php

# *******************************************************************
# API: Version
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', true);

$files_to_track = [
    __DIR__ . '/../../tpl/page.alarm.html',
    __DIR__ . '/../../tpl/page.active.html',
    __DIR__ . '/../../tpl/app.conf',
    __DIR__ . '/../../ui/alarm.dist.css',
    __DIR__ . '/../../ui/alpine.js',
    __DIR__ . '/../../favicon.svg'
];

$version_string = '';

foreach ($files_to_track as $file) {
    if (file_exists($file)) {
        $version_string .= $file . ':' . filemtime($file) . ';';
    }
}

$hash = md5($version_string);

$APPD->setData('OUTPUT_JSON', json_encode([
    'success' => true,
    'version' => $hash
]));
