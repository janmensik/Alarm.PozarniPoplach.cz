<?php

use Janmensik\Jmlib\AppData;

# *******************************************************************
# routes
# *******************************************************************

# 404
$router->set404(function () {
    header('HTTP/1.1 404 Not Found');
    $APPD = AppData::getInstance();
    $APPD->setData('PAGE', '404');
});

# *******************************************************************
/*
# login
$router->post('/login', function () use ($Smarty, $DB) {
    include('./view/page/login.php');
});
*/

# *******************************************************************

# index (dashboard)
$router->get('/', function () use ($Smarty, $DB) {
    $APPD = AppData::getInstance();
    $APPD->setData('PAGE', 'alarm');

    include('./view/page/alarm.php');
});

# Device Activation — DEPRECATED: now handled by admin.pozarnipoplach.cz/activate/XXXX
# Redirect old /activate links (QR codes, bookmarks) to the authenticated admin flow
$redirectToAdminActivation = function ($code = null) {
    $code = $code ?: ($_GET['code'] ?? '');
    $code = preg_replace('/[^A-Za-z0-9]/', '', (string)$code);
    $adminUrl = rtrim($_ENV['ADMIN_URL'] ?? 'https://admin.pozarnipoplach.cz', '/');
    $target = $adminUrl . '/activate' . ($code !== '' ? '/' . strtoupper($code) : '');
    header('Location: ' . $target, true, 301);
    header('Connection: close');
    exit();
};
$router->get('/activate', $redirectToAdminActivation);
$router->get('/activate/([A-Za-z0-9]{4,16})', $redirectToAdminActivation);

# Redirection Service (Goto)
$router->get('/goto/(\w+)/(\d+)', function ($type, $id) use ($DB) {
    $APPD = AppData::getInstance();
    $APPD->setData('PAGE', 'goto');
    $APPD->setData('GOTO_TYPE', $type);
    $APPD->setData('GOTO_ID', $id);

    include('./view/page/goto.php');
});

# *******************************************************************

# API
$router->mount('/api', function () use ($router, $DB) {

    $router->get('/dispatch', function () use ($DB) {
        include('./view/api/dispatch.php');
    });

    $router->get('/version', function () {
        include('./view/api/version.php');
    });

    $router->get('/calendar', function () use ($DB) {
        include('./view/api/calendar.php');
    });

    # Device Auth Flow
    $router->mount('/auth/device', function () use ($router, $DB) {
        $router->match('GET|POST', '/init', function () use ($DB) {
            include('./view/api/device-init.php');
        });
        $router->get('/poll', function () use ($DB) {
            include('./view/api/device-poll.php');
        });
        $router->match('GET|POST', '/authorize', function () use ($DB) {
            include('./view/api/device-authorize.php');
        });
        $router->match('GET|POST', '/validate', function () use ($DB) {
            include('./view/api/device-validate.php');
        });
    });
});
