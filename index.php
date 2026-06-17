<?php

# *******************************************************************
# NEEDS
# *******************************************************************

require_once(__DIR__ . '/inc.startup.php');

// require_once(__DIR__ . '/lib/functions/function.parseFloat.php'); # prevod "minuly mesic" na time interval
// require_once(__DIR__ . '/lib/functions/function.pagination.php'); # pagination

use Janmensik\Jmlib\Database;
use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Modul;

# *******************************************************************
# GLOBAL APPDATA
# *******************************************************************
$APPD = AppData::getInstance();

$APPD->setData('BASE_URL', getenv('ABSOLUTE_URL'));

// Only allow explicitly safe, non-sensitive variables
$allow_list = [
    'LOCALE',
    'DEFAULT_ITEMS_PER_PAGE',
    'DEFAULT_ITEMS_PER_PAGE_DOTS',
    'DEFAULT_ALARM_SHOWN',
    'MASTER_EMAIL',
    'ABSOLUTE_URL',
    'APP_NAME',
    'APP_VERSION',
    'APP_BRAND',
    'APP_SHORTNAME',
    'APP_URL',
    'MAPBOX_API_KEY',
    'GOOGLE_MAPS_API_KEY',
];

$filtered_app_data = [];
foreach ($allow_list as $key) {
    if (isset($_ENV[$key])) {
        $filtered_app_data[$key] = $_ENV[$key];
    } elseif (isset($_SERVER[$key])) {
        $filtered_app_data[$key] = $_SERVER[$key];
    }
}

$APPD->setData('APP', $filtered_app_data);
$APPD->setData('SOURCE', 'alarm');

# ...................................................................

$User = null; // No user on alarm page

# *******************************************************************
# Session and redirect handling
# *******************************************************************

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header("Strict-Transport-Security: max-age=63072000; includeSubDomains; preload");
header("Referrer-Policy: no-referrer");
header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: 0");
// header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://maps.googleapis.com https://api.mapbox.com; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://kit.fontawesome.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; connect-src 'self';");

$isSecure = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_name('pozarnipoplach_alarm');
session_start();

$APPD->loadMessages();

# *******************************************************************
# DEFINICE, INICIALIZACE
# *******************************************************************

date_default_timezone_set('Europe/Prague');

mb_internal_encoding("UTF-8");

# rucni debug (pouze pokud neni ostry provoz)
if (getenv('DEBUGGING') == 1 && isset($_GET['debug'])) {
    // Note: putenv doesn't persist across requests but helps in current scope
    putenv('DEBUGGING=2');
}
$APPD->setData('DEBUG_MODE', getenv('DEBUGGING'));

# spusteni tridy Database
$DB = new Database(getenv('SQL_HOST'), getenv('SQL_DATABASE'), getenv('SQL_USER'), getenv('SQL_PASSWORD'));
$DB->query('SET CHARACTER SET utf8;');

require_once(__DIR__ . '/inc.smarty.php');

# *******************************************************************
# router
# *******************************************************************

$router = new \Bramus\Router\Router();

require_once(__DIR__ . "/include/routes.php");

# Run router on routes
$router->run();

# *******************************************************************
# FINAL assign and Smarty template run
# *******************************************************************

$Smarty->assign('SESSION', $_SESSION);
$Smarty->assign('MESSAGES', $APPD->getMessages());

# common access - short names
$Smarty->assign('PAGE', $APPD->getData('PAGE'));
$Smarty->assign('BASE_URL', $APPD->getData('BASE_URL'));
$Smarty->assign('APP_VERSION', $APPD->getData('APP_VERSION'));
$Smarty->assign('ERROR', $APPD->getData('ERROR'));

# all APPD vars.
$Smarty->assign('APPD', $APPD->getData());

$Smarty->assign('FILTERS', $APPD->getFilters($APPD->getData('PAGE')));

$Smarty->assign('DEBUG_sql_queries', $DB->messages);

# prefix
if ($APPD->getData('TYPE') == 'controller') {
    $template_prefix = 'ctrl';
} else {
    $template_prefix = 'page';
}

if ($APPD->getData('API')) {
    header('Content-Type: application/json');
    header('Content-Encoding: UTF-8');
    header('Content-language: cs');
    if ($APPD->getData('OUTPUT_JSON')) {
        echo ($APPD->getData('OUTPUT_JSON'));
    }
} else {
    $Smarty->display($template_prefix . '.' . ($APPD->getData('PAGE') ?: 'alarm') . '.html');
}

$APPD->clearMessages();
