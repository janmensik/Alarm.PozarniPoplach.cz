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
require_once(__DIR__ . '/../../include/class.Ad.php');

if (!isset($Dispatch)) {
    $Dispatch = new \PozarniPoplach\Dispatch($DB);
}

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$unit_id = null;

# ...................................................................
# Access control logic
$credentials = $DeviceAuth->getRequestCredentials();

if (!empty($credentials['uuid']) && !empty($credentials['token'])) {
    $unit_id = $DeviceAuth->validateDevice($credentials['uuid'], $credentials['token']);
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
$data = $Dispatch->getLastDispatch($unit_id);

# only show dispatch if it is not older than DEFAULT_ALARM_SHOWN minutes (peacetime)
if (!empty($data) && isset($data['dispatched_at_ts']) && (time() - $data['dispatched_at_ts'] <= ($_ENV['DEFAULT_ALARM_SHOWN'] ?? 60) * 60)) {
    $data_parsed = $Dispatch->beautifulLastDispatch($data);
    $data_parsed['dispatch_status'] = 'alarm';
} else {
    // Peacetime mode - still try to provide unit name
    if (!empty($data['unit_fullname'])) {
        $unit_name = $data['unit_fullname'];
    } else {
        // Fallback: load unit name directly if no dispatches exist
        $unit = $DB->getRow($DB->query("SELECT fullname FROM unit WHERE id = " . (int)$unit_id . " LIMIT 1"));
        if ($unit) {
            $unit_name = $unit['fullname'];
        }
    }

    # lets load ad
    if (!isset($Ad)) {
        $Ad = new \PozarniPoplach\Ad($DB);
    }
    $ad = $Ad->getAdForDevice($credentials['uuid'], $unit_id);

    $data_parsed = [
        'dispatch_status' => 'peacetime',
        'unit' => $unit_name,
        'ad' => $ad
    ];
}

# *******************************************************************
# OUTPUT
# *******************************************************************

$APPD->setData('OUTPUT_JSON', json_encode($data_parsed, JSON_UNESCAPED_UNICODE));
