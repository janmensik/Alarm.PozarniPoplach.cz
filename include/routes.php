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

# logout
$router->get('/logout', function () use ($Smarty, $DB, $User) {
    include('./view/page/logout.php');
});

# *******************************************************************

# login
$router->get('/login', function () use ($Smarty, $DB) {
    $APPD = AppData::getInstance();
    $APPD->setData('PAGE', 'login');
});

# *******************************************************************

# login
$router->post('/login', function () use ($Smarty, $DB) {
    include('./view/page/login.php');
});

# *******************************************************************

# settings - users edit
$router->get('/' . $APPD->data['CONFIG']['settings_url'], function () use ($Smarty, $DB, $User) {
    include('./view/page/settings.php');
});
$router->post('/' . $APPD->data['CONFIG']['settings_url'], function () use ($Smarty, $DB, $User) {
    include('./view/page/settings.php');
});

# *******************************************************************

# mail schedule - list
$router->get('/' . $APPD->data['CONFIG']['mail_schedule_url'], function () use ($Smarty, $DB, $User) {
    include('./view/page/mail-schedule.php');
});

# *******************************************************************

# index (dashboard)
$router->get('/', function () use ($Smarty, $DB, $User) {
    include('./view/page/dashboard.php');
});

# *******************************************************************

# API
$router->mount('/api', function () use ($router, $DB, $User, $Smarty) {

    $router->get('/alarm', function () use ($DB, $User) {
        include('./view/api/alarm-dispatch.php');
    });
});

# *******************************************************************
# ALARM
# *******************************************************************

$router->mount('/alarm', function () use ($router, $DB, $Smarty, $APPD) {

    $router->get('/' . $APPD->data['CONFIG']['alarm_url'], function () use ($Smarty, $DB) {
        include('./view/page/alarm-dispatch.php');
    });

    # *******************************************************************

    $router->mount('/api', function () use ($router, $DB) {

        $router->get('/dispatch', function () use ($DB) {
            include('./view/api/dispatch.php');
        });
    });
});
