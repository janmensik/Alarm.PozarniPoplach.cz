<?php

# *******************************************************************
# Page: Activate (Mobile Authorization)
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('PAGE', 'activate');

require_once(__DIR__ . '/../../include/class.DeviceAuth.php');
$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$Smarty->assign('csrf_token', $_SESSION['csrf_token']);

$device_code = $_GET['code'] ?? $_POST['code'] ?? $_POST['manual_code'] ?? null;
$session = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Neplatný CSRF token. Zkuste to prosím znovu.';
        $device_code = null; // Clear code to prevent processing
    }
}

if (!empty($device_code)) {
    // Normalize code (uppercase, trim)
    $device_code = strtoupper(trim($device_code));
    $session = $DeviceAuth->checkSessionStatus($device_code);

    if (!$session || $session['status'] !== 'pending') {
        $error = "Neplatný nebo prošlý aktivační kód.";
        $session = null; // Clear session if invalid
    }
}

// Handle Authorization form submission
if ($session && !empty($_POST['unit_id'])) {
    if ($DeviceAuth->linkSessionToUnit($device_code, intval($_POST['unit_id']), $_POST['device_name'] ?? null)) {
        $Smarty->assign('success', true);
    } else {
        $error = 'Nepodařilo se autorizovat zařízení.';
    }
}

// Load list of units for the selection
$units = $DB->getAllRows($DB->query('SELECT id, fullname FROM unit ORDER BY fullname ASC'));

$Smarty->assign('units', $units);
$Smarty->assign('device_code', $device_code);
$Smarty->assign('session_data', $session);
$Smarty->assign('error', $error);

// If no session and no success, we are in "Manual Entry" mode
$Smarty->assign('manual_mode', !$session && !isset($Smarty->tpl_vars['success']));

$Smarty->display('page.activate.html');
exit();
