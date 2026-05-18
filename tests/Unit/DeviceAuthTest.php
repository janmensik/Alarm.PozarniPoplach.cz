<?php

use PHPUnit\Framework\TestCase;
use Janmensik\Jmlib\Database;
use PozarniPoplach\DeviceAuth;

require_once __DIR__ . '/../../include/class.DeviceAuth.php';

class DeviceAuthTest extends TestCase
{
    private $db;
    private $deviceAuth;
    private $mysqli;

    protected function setUp(): void
    {
        $this->db = $this->createMock(Database::class);
        $this->mysqli = new class extends mysqli {
            public function __construct() {}
            public function real_escape_string(string $string): string {
                return addslashes($string);
            }
        };
        $this->db->db = $this->mysqli;

        $this->deviceAuth = new DeviceAuth($this->db);
    }

    protected function tearDown(): void
    {
        $_SERVER = [];
        $_REQUEST = [];
    }

    public function testGetRequestCredentialsReturnsNullsWhenEmpty()
    {
        $_SERVER = [];
        $_REQUEST = [];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertNull($credentials['uuid']);
        $this->assertNull($credentials['token']);
    }

    public function testGetRequestCredentialsExtractsUuidFromHttpXDeviceUuid()
    {
        $_SERVER = [
            'HTTP_X_DEVICE_UUID' => 'test-uuid-header'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-uuid-header', $credentials['uuid']);
    }

    public function testGetRequestCredentialsExtractsUuidFromRequestFallback()
    {
        $_SERVER = [];
        $_REQUEST = [
            'uuid' => 'test-uuid-request'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-uuid-request', $credentials['uuid']);
    }

    public function testGetRequestCredentialsPrefersHttpXDeviceUuidOverRequest()
    {
        $_SERVER = [
            'HTTP_X_DEVICE_UUID' => 'test-uuid-header'
        ];
        $_REQUEST = [
            'uuid' => 'test-uuid-request'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-uuid-header', $credentials['uuid']);
    }

    public function testGetRequestCredentialsExtractsTokenFromAuthorizationBearer()
    {
        $_SERVER = [
            'HTTP_AUTHORIZATION' => 'Bearer test-token-bearer'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-token-bearer', $credentials['token']);
    }

    public function testGetRequestCredentialsHandlesCaseInsensitiveBearerPrefix()
    {
        $_SERVER = [
            'HTTP_AUTHORIZATION' => 'bearer test-token-bearer-lower'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-token-bearer-lower', $credentials['token']);
    }

    public function testGetRequestCredentialsExtractsTokenFromHttpXDeviceTokenFallback()
    {
        $_SERVER = [
            'HTTP_X_DEVICE_TOKEN' => 'test-token-header'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-token-header', $credentials['token']);
    }

    public function testGetRequestCredentialsPrefersAuthorizationOverHttpXDeviceToken()
    {
        $_SERVER = [
            'HTTP_AUTHORIZATION' => 'Bearer test-token-bearer',
            'HTTP_X_DEVICE_TOKEN' => 'test-token-header'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-token-bearer', $credentials['token']);
    }

    public function testGetRequestCredentialsWorksWithCustomHeaderNamesContainingHyphens()
    {
        $_SERVER = [
            'HTTP_X_DEVICE_UUID' => 'uuid-with-hyphen',
            'HTTP_X_DEVICE_TOKEN' => 'token-with-hyphen'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('uuid-with-hyphen', $credentials['uuid']);
        $this->assertEquals('token-with-hyphen', $credentials['token']);
    }

    public function testGetRequestCredentialsExtractsUuidFromHttpDeviceUuid()
    {
        $_SERVER = [
            'HTTP_DEVICE_UUID' => 'test-device-uuid-fallback'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-device-uuid-fallback', $credentials['uuid']);
    }

    public function testGetRequestCredentialsExtractsTokenFromHttpDeviceToken()
    {
        $_SERVER = [
            'HTTP_DEVICE_TOKEN' => 'test-device-token-fallback'
        ];

        $credentials = $this->deviceAuth->getRequestCredentials();

        $this->assertEquals('test-device-token-fallback', $credentials['token']);
    }
}
