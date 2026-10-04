<?php

use Bramus\Router\Router;

/**
 * Legacy /activate URLs must redirect (301) to admin.pozarnipoplach.cz/activate/XXXX.
 * The route closure calls exit(), so execution is verified in an isolated PHP process:
 * if the redirect fires, the script ends before reaching the "NO_REDIRECT" marker.
 */
function runLegacyActivateRequest(string $uri, array $get = []): string
{
    $root = realpath(__DIR__ . '/../..');
    $script = tempnam(sys_get_temp_dir(), 'act');

    file_put_contents($script, '<?php
require ' . var_export($root . '/vendor/autoload.php', true) . ';
$_ENV["ADMIN_URL"] = "https://admin.pozarnipoplach.cz/";
$_GET = ' . var_export($get, true) . ';
$_SERVER["REQUEST_METHOD"] = "GET";
$_SERVER["REQUEST_URI"] = ' . var_export($uri, true) . ';
$_SERVER["SCRIPT_NAME"] = "/index.php";
$Smarty = null;
$DB = null;
$router = new \Bramus\Router\Router();
$router->set404(function () { echo "NOT_FOUND"; });
require ' . var_export($root . '/include/routes.php', true) . ';
$router->run();
echo "NO_REDIRECT";
');

    $output = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' 2>&1');
    @unlink($script);

    return $output;
}

test('activate redirect routes are registered as GET only', function () {
    $router = new Router();
    $Smarty = null;
    $DB = null;
    require __DIR__ . '/../../include/routes.php';

    $routes = (new ReflectionClass($router))->getProperty('afterRoutes')->getValue($router);

    $patterns = array_column($routes['GET'], 'pattern');
    expect($patterns)->toContain('/activate');
    expect($patterns)->toContain('/activate/([A-Za-z0-9]{4,16})');
    $postPatterns = array_column($routes['POST'] ?? [], 'pattern');
    expect($postPatterns)->not->toContain('/activate');
});

test('redirect target is built from ADMIN_URL with a 301 status', function () {
    $source = file_get_contents(__DIR__ . '/../../include/routes.php');

    expect($source)->toContain("\$_ENV['ADMIN_URL']");
    expect($source)->toContain("'/activate' . (\$code !== '' ? '/' . strtoupper(\$code) : '')");
    expect($source)->toContain("header('Location: ' . \$target, true, 301)");
});

test('legacy /activate/CODE path redirects instead of falling through', function () {
    $out = runLegacyActivateRequest('/activate/abcd2345');

    expect($out)->not->toContain('NO_REDIRECT');
    expect($out)->not->toContain('NOT_FOUND');
});

test('legacy /activate?code=CODE query string redirects instead of falling through', function () {
    $out = runLegacyActivateRequest('/activate?code=xyz9876q', ['code' => 'xyz9876q']);

    expect($out)->not->toContain('NO_REDIRECT');
    expect($out)->not->toContain('NOT_FOUND');
});

test('legacy activate page controller and template were removed', function () {
    expect(file_exists(__DIR__ . '/../../view/page/activate.php'))->toBeFalse();
    expect(file_exists(__DIR__ . '/../../tpl/page.activate.html'))->toBeFalse();
});
