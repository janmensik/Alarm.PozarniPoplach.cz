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
# 1. Try new Device Auth (UUID + Token)
if (!empty($_GET['uuid']) && !empty($_GET['token'])) {
    $unit_id = $DeviceAuth->validateDevice($_GET['uuid'], $_GET['token']);
}

# 2. Fallback to legacy Pincode
if (empty($unit_id) && !empty($_GET['pincode'])) {
	$unit_id = $Dispatch->checkUnitPincode($_GET['pincode'], true); // PINCODE is hashed (SHA1)
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
