<?php

namespace Tests\Feature;

use Bramus\Router\Router;
use Janmensik\Jmlib\AppData;
use Janmensik\Jmlib\Database;
use ReflectionClass;

beforeEach(function () {
    clearAppData();

    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class {
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    $this->smarty = new class {
        public array $assigns = [];
        public function assign($key, $value = null): void {}
        public function display($template): void {}
    };
});

test('routes sets 404 page for unmatched URLs', function () {
    $router = new Router();
    $router->setBasePath('/');
    $APPD = AppData::getInstance();
    $DB = $this->db;
    $Smarty = $this->smarty;

    require __DIR__ . '/../../include/routes.php';

    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/non-existent-route-999';

    ob_start();
    $router->run();
    ob_end_clean();

    expect($APPD->getData('PAGE'))->toBe('404');
});

test('routes defines all expected public and API endpoints', function () {
    $router = new Router();
    $APPD = AppData::getInstance();
    $DB = $this->db;
    $Smarty = $this->smarty;

    require __DIR__ . '/../../include/routes.php';

    $refl = new ReflectionClass($router);
    $prop = $refl->getProperty('afterRoutes');
    $afterRoutes = $prop->getValue($router);

    $routes = [];
    foreach ($afterRoutes as $method => $routeList) {
        foreach ($routeList as $item) {
            $routes[$method][] = $item['pattern'];
        }
    }

    // Public pages
    expect($routes['GET'])->toContain('/');
    expect($routes['GET'])->toContain('/activate');
    expect($routes['POST'])->toContain('/activate');
    expect($routes['GET'])->toContain('/goto/(\w+)/(\d+)');

    // API endpoints
    expect($routes['GET'])->toContain('/api/dispatch');
    expect($routes['GET'])->toContain('/api/version');
    expect($routes['GET'])->toContain('/api/calendar');

    // Device auth flow
    expect($routes['GET'])->toContain('/api/auth/device/init');
    expect($routes['POST'])->toContain('/api/auth/device/init');
    expect($routes['GET'])->toContain('/api/auth/device/poll');
    expect($routes['GET'])->toContain('/api/auth/device/authorize');
    expect($routes['POST'])->toContain('/api/auth/device/authorize');
    expect($routes['GET'])->toContain('/api/auth/device/validate');
    expect($routes['POST'])->toContain('/api/auth/device/validate');
});

