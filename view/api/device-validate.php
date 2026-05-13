<?php

# *******************************************************************
# API: Device Validate (Check Token)
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', true);

require_once(__DIR__ . '/../../include/class.DeviceAuth.php');

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$device_uuid = $_GET['uuid'] ?? $_POST['uuid'] ?? null;
$refresh_token = $_GET['token'] ?? $_POST['token'] ?? null;

if (empty($device_uuid) || empty($refresh_token)) {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Missing parameters']));
    return;
}

$unit_id = $DeviceAuth->validateDevice($device_uuid, $refresh_token);

if ($unit_id) {
    $APPD->setData('OUTPUT_JSON', json_encode([
        'success' => true,
        'unit_id' => $unit_id
    ]));
} else {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Invalid token or device']));
}
