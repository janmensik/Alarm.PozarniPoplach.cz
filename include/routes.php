<?php

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

# Device Activation (Mobile)
$router->match('GET|POST', '/activate', function () use ($Smarty, $DB) {
    $APPD = AppData::getInstance();
    $APPD->setData('PAGE', 'activate');

    include('./view/page/activate.php');
});

# *******************************************************************

# API
$router->mount('/api', function () use ($router, $DB) {

    $router->get('/dispatch', function () use ($DB) {
        include('./view/api/dispatch.php');
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
