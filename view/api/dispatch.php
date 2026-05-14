<?php

# *******************************************************************
# NEEDS + Global
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', 'true');
$APPD->setData('PAGE', 'alarm');

# ...................................................................
# load up
require_once(__DIR__ . '/../../include/class.Dispatch.php');
require_once(__DIR__ . '/../../include/class.DeviceAuth.php');

if (!isset($Dispatch)) {
	$Dispatch = new \PozarniPoplach\Dispatch($DB);
}

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$unit_id = null;

# ...................................................................
# Access control logic
$creds = $DeviceAuth->getRequestCredentials();

if (!empty($creds['uuid']) && !empty($creds['token'])) {
    $unit_id = $DeviceAuth->validateDevice($creds['uuid'], $creds['token']);
}

# ...................................................................
# Final Check
if (empty($unit_id)) {
    http_response_code(401);
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Unauthorized'], JSON_UNESCAPED_UNICODE));
	return;
}

# *******************************************************************
# PROGRAM
# *******************************************************************

// In production we would use getLastDispatch($unit_id)
// For testing we use getRandomDispatch()
$data = $Dispatch->getRandomDispatch();

$data_parsed = $Dispatch->beautifulLastDispatch($data);

# *******************************************************************
# OUTPUT
# *******************************************************************

$APPD->setData('OUTPUT_JSON', json_encode($data_parsed, JSON_UNESCAPED_UNICODE));
