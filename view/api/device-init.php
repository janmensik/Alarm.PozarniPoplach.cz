<?php

# *******************************************************************
# API: Device Init
# *******************************************************************

$APPD = AppData::getInstance();
$APPD->setData('API', true);

require_once(__DIR__ . '/../../include/class.DeviceAuth.php');

$DeviceAuth = new \PozarniPoplach\DeviceAuth($DB);

$device_uuid = $_POST['uuid'] ?? $_GET['uuid'] ?? null;

if (empty($device_uuid)) {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Missing device UUID']));
    return;
}

$session = $DeviceAuth->initSession($device_uuid);

if ($session) {
    // Generate QR code using chillerlan/php-qrcode
    // We return it as a Data URI so it can be directly used in an <img> tag
    $options = new \chillerlan\QRCode\QROptions([
        'version'      => \chillerlan\QRCode\Common\Version::AUTO,
        'outputType'   => \chillerlan\QRCode\Output\QROutputInterface::MARKUP_SVG,
        'eccLevel'     => \chillerlan\QRCode\Common\EccLevel::L,
        'addQuietzone' => true,
        'svgViewBox'   => true, // Important for responsive scaling
    ]);

    $qrcode = (new \chillerlan\QRCode\QRCode($options))->render($session['verification_url']);

    $APPD->setData('OUTPUT_JSON', json_encode([
        'success' => true,
        'device_code' => $session['device_code'],
        'verification_url' => $session['verification_url'],
        'qr_code_data' => $qrcode, // This is a Data URI (e.g. data:image/svg+xml;base64,...)
        'expires_at' => $session['expires_at']
    ]));
} else {
    $APPD->setData('OUTPUT_JSON', json_encode(['success' => false, 'error' => 'Failed to initialize session']));
}
