<?php

use Janmensik\Jmlib\AppData;

# *******************************************************************
# NEEDS + Global
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', 'true');
$APPD->setData('PAGE', 'calendar');

# ...................................................................
# load up
require_once(__DIR__ . '/../../include/class.DeviceAuth.php');
require_once(__DIR__ . '/../../include/class.Calendar.php');
require_once(__DIR__ . '/../../include/class.Unit.php');

if (!isset($DeviceAuth)) {
    $DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);
}

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

# load unit data for potential use in calendar filtering or output
if (!isset($Unit)) {
    $Unit = new \PozarniPoplach\Unit($DB);
}
$unit_data = $Unit->getId($unit_id);


# does the unit have a calendar URL set?
if (empty($unit_data) || empty($unit_data['calendar_url'])) {
    http_response_code(404);
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'No calendar found for this unit'], JSON_UNESCAPED_UNICODE));
    return;
}

# fetch and output calendar events
if (!isset($Calendar)) {
    $Calendar = new \PozarniPoplach\Calendar($unit_data['calendar_url']);
}

$events = $Calendar->getCalendar(null, 3, '+3 months'); // Get next 3 events within the next 3 months


# *******************************************************************
# OUTPUT
# *******************************************************************

$APPD->setData('OUTPUT_JSON', json_encode($events, JSON_UNESCAPED_UNICODE));
