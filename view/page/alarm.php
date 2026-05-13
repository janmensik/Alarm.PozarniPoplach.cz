<?php

# *******************************************************************
# NEEDS + Global
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('PAGE', 'alarm');

# ...................................................................
# load up
require_once(__DIR__ . '/../../include/class.Dispatch.php');

if (!isset($Dispatch)) {
	$Dispatch = new \PozarniPoplach\Dispatch($DB);
}

# *******************************************************************
# PROGRAM
# *******************************************************************

// Legacy pincode support (if needed for some old devices)
if (!empty($_GET['pincode'])) {
	$unit_id = $Dispatch->checkUnitPincode($_GET['pincode'], true);
    if ($unit_id) {
        $data = $Dispatch->getLastDispatch($unit_id);
        $data_parsed = $Dispatch->beautifulLastDispatch($data);
        $Smarty->assign('data', $data_parsed);
    }
}

// In the new Device Auth flow, the frontend handles the authorized state
// and fetches data via /api/dispatch. No server-side redirect needed.
