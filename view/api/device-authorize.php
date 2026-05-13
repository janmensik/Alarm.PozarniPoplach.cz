<?php

# *******************************************************************
# API: Device Authorize (Final Step)
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', true);

require_once(__DIR__ . '/../../include/class.DeviceAuth.php');

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$device_code = $_GET['code'] ?? $_POST['code'] ?? null;

if (empty($device_code)) {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Missing device code']));
    return;
}

$auth_data = $DeviceAuth->authorizeDevice($device_code);

if ($auth_data) {
    $APPD->setData('OUTPUT_JSON', json_encode([
        'success' => true,
        'refresh_token' => $auth_data['refresh_token'],
        'unit_id' => $auth_data['unit_id']
    ]));
} else {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Authorization failed or session not linked']));
}
