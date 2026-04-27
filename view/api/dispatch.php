<?php

# *******************************************************************
# NEEDS + Global
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', 'true');
$APPD->setData('PAGE', 'alarm-dispatch');

# ...................................................................
# load up
require_once(__DIR__ . '/../../include/class.Dispatch.php');

if (!isset($Dispatch)) {
	$Dispatch = new \PozarniPoplach\Dispatch($DB);
}

# ...................................................................
# Custom access control - based on unit pincode
if (!empty($_GET['pincode'])) {
	$unit_id = $Dispatch->checkUnitPincode($_GET['pincode'], true); // PINCODE is hashed (SHA1)
}

if (empty($unit_id)) {
	header('Location: ' . $APPD->getData('BASE_URL') . '/alarm-login');
	header("Connection: close");
	exit();
}

# *******************************************************************
# PROGRAM
# *******************************************************************

//$data = $Dispatch->getLastDispatch($unit_id);
$data = $Dispatch->getRandomDispatch();

$data_parsed = $Dispatch->beautifulLastDispatch($data);

# *******************************************************************
# OUTPUT
# *******************************************************************

$APPD->setData('OUTPUT_JSON', json_encode($data_parsed, JSON_UNESCAPED_UNICODE));
