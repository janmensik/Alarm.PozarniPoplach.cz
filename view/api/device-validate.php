<?php

# *******************************************************************
# API: Device Validate (Check Token)
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', true);

require_once(__DIR__ . '/../../include/class.DeviceAuth.php');

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$creds = $DeviceAuth->getRequestCredentials();

if (empty($creds['uuid']) || empty($creds['token'])) {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Missing parameters']));
    return;
}

$unit_id = $DeviceAuth->validateDevice($creds['uuid'], $creds['token']);

if ($unit_id) {
    $APPD->setData('OUTPUT_JSON', json_encode([
        'success' => true,
        'unit_id' => $unit_id
    ]));
} else {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Invalid token or device']));
}
