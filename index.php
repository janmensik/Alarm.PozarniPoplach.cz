<?php

# *******************************************************************
# NEEDS
# *******************************************************************

require_once(__DIR__ . '/inc.startup.php');

// require_once(__DIR__ . '/lib/functions/function.parseFloat.php'); # prevod "minuly mesic" na time interval
// require_once(__DIR__ . '/lib/functions/function.pagination.php'); # pagination

use Janmensik\Jmlib\Database;
// Alias AppData to global namespace for backward compatibility
class_alias(\Janmensik\Jmlib\AppData::class, 'AppData');
class_alias(\Janmensik\Jmlib\Modul::class, 'Modul');

# *******************************************************************
# GLOBAL APPDATA
# *******************************************************************
$APPD = AppData::getInstance();

$APPD->setData('BASE_URL', $_ENV['ABSOLUTE_URL']);
$APPD->setData('APP', $_ENV);
$APPD->setData('SOURCE', 'alarm');

# ...................................................................

$User = null; // No user on alarm page

# *******************************************************************
# Session and redirect handling
# *******************************************************************

session_name('pozarnipoplach_alarm');
session_start();

$APPD->loadMessages();

# *******************************************************************
# DEFINICE, INICIALIZACE
# *******************************************************************

date_default_timezone_set('Europe/Prague');

mb_internal_encoding("UTF-8");

# rucni debug (pouze pokud neni ostry provoz)
if ($_ENV['DEBUGGING'] == 1 && isset($_GET['debug'])) {
    $_ENV['DEBUGGING'] = 2;
}
$APPD->setData('DEBUG_MODE', $_ENV['DEBUGGING']);

# spusteni tridy Database
$DB = new Database($_ENV['SQL_HOST'], $_ENV['SQL_DATABASE'], $_ENV['SQL_USER'], $_ENV['SQL_PASSWORD']);
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
    // $Smarty->display($template_prefix . '.' . $APPD->getData('PAGE') . '.html');
    $Smarty->display($template_prefix . '.alarm.html');
}

$APPD->clearMessages();
