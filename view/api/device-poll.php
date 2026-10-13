<?php

use Janmensik\Jmlib\AppData;

# *******************************************************************
# API: Device Poll
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', true);

require_once(__DIR__ . '/../../include/class.DeviceAuth.php');

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$raw_code = $_GET['code'] ?? null;
$device_code = is_scalar($raw_code) ? (string)$raw_code : null;

if (empty($device_code)) {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Missing device code']));
    return;
}

$status = $DeviceAuth->checkSessionStatus($device_code);

if ($status) {
    $APPD->setData('OUTPUT_JSON', json_encode([
        'success' => true,
        'status' => $status['status']
    ]));
} else {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Session expired or not found', 'status' => 'expired']));
}
