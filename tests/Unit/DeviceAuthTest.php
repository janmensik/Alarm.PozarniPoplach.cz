<?php

namespace Tests\Unit;

use Janmensik\Jmlib\Database;
use PozarniPoplach\DeviceAuth;

require_once __DIR__ . '/../../include/class.DeviceAuth.php';

beforeEach(function () {
    $this->db = $this->createMock(Database::class);
    $this->mysqli = new class {
        public function real_escape_string(string $string): string {
            return addslashes($string);
        }
    };
    $this->db->db = $this->mysqli;

    $this->deviceAuth = new DeviceAuth($this->db);

    unset($_SERVER['HTTP_AUTHORIZATION']);
    unset($_SERVER['HTTP_X_DEVICE_TOKEN']);
    unset($_SERVER['HTTP_X_DEVICE_UUID']);
    $_GET = [];
    $_POST = [];
    $_REQUEST = [];
});

test('initSession executes cleanup, generates valid code, and returns session data', function () {
    $_ENV['ABSOLUTE_URL'] = 'https://alarm.pozarnipoplach.cz';

    $cleanupExecuted = false;
    $insertExecuted = false;

    $this->db->expects($this->exactly(2))
        ->method('query')
        ->willReturnCallback(function ($query) use (&$cleanupExecuted, &$insertExecuted) {
            if (str_contains($query, 'DELETE FROM alarm_device_session WHERE expires_at < NOW()')) {
                $cleanupExecuted = true;
                return true;
            }
            if (str_contains($query, 'INSERT INTO alarm_device_session')) {
                $insertExecuted = true;
                return true;
            }
            return false;
        });

    $result = $this->deviceAuth->initSession('test-device-uuid-123');

    expect($cleanupExecuted)->toBeTrue();
    expect($insertExecuted)->toBeTrue();
    expect($result)->not->toBeNull();
    expect($result)->toHaveKeys(['device_code', 'expires_at', 'verification_url']);
    expect(strlen($result['device_code']))->toBe(8);
    // User-friendly alphabet: no 0, O, 1, l, I
    expect($result['device_code'])->toMatch('/^[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}$/');
    expect($result['verification_url'])->toBe('https://alarm.pozarnipoplach.cz/activate?code=' . $result['device_code']);
});

test('initSession returns null when insert query fails', function () {
    $this->db->expects($this->exactly(2))
        ->method('query')
        ->willReturnOnConsecutiveCalls(true, false);

    $result = $this->deviceAuth->initSession('test-device-uuid-123');
    expect($result)->toBeNull();
});

test('checkSessionStatus returns session row when found', function () {
    $sessionRow = ['status' => 'pending', 'unit_id' => null, 'device_uuid' => 'uuid-abc'];

    $this->db->expects($this->once())
        ->method('query')
        ->with($this->stringContains('SELECT status, unit_id, device_uuid FROM alarm_device_session'))
        ->willReturn('result-resource');

    $this->db->expects($this->once())
        ->method('getRow')
        ->with('result-resource')
        ->willReturn($sessionRow);

    $result = $this->deviceAuth->checkSessionStatus('CODE1234');
    expect($result)->toBe($sessionRow);
});

test('checkSessionStatus returns null when session is not found or expired', function () {
    $this->db->expects($this->once())
        ->method('query')
        ->willReturn('result-resource');

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(null);

    $result = $this->deviceAuth->checkSessionStatus('EXPIRED1');
    expect($result)->toBeNull();
});

test('linkSessionToUnit updates session status and returns true', function () {
    $this->db->expects($this->once())
        ->method('query')
        ->with($this->stringContains('UPDATE alarm_device_session'))
        ->willReturn(true);

    $success = $this->deviceAuth->linkSessionToUnit('CODE1234', 42, 'Firehouse Kiosk');
    expect($success)->toBeTrue();
});

test('authorizeDevice returns null if session is not linked or has no unit_id', function () {
    $this->db->expects($this->once())
        ->method('query')
        ->willReturn('res');

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(['status' => 'pending', 'unit_id' => null, 'device_uuid' => 'uuid-1', 'device_name' => null]);

    $result = $this->deviceAuth->authorizeDevice('PENDING1');
    expect($result)->toBeNull();
});

test('authorizeDevice persists authorization and returns refresh token and unit_id', function () {
    $this->db->expects($this->exactly(3))
        ->method('query')
        ->willReturnCallback(function ($query) {
            if (str_contains($query, 'SELECT status, unit_id')) {
                return 'select-res';
            }
            if (str_contains($query, 'INSERT INTO alarm_device_authorized')) {
                return true;
            }
            if (str_contains($query, 'DELETE FROM alarm_device_session')) {
                return true;
            }
            return false;
        });

    $this->db->expects($this->once())
        ->method('getRow')
        ->with('select-res')
        ->willReturn([
            'status' => 'linked',
            'unit_id' => 99,
            'device_uuid' => 'uuid-kiosk-99',
            'device_name' => 'Main Display'
        ]);

    $result = $this->deviceAuth->authorizeDevice('LINKED01');

    expect($result)->not->toBeNull();
    expect($result['unit_id'])->toBe(99);
    expect($result['refresh_token'])->toBeString();
    expect(strlen($result['refresh_token']))->toBe(64); // 32 random bytes in hex
});

test('getRequestCredentials extracts credentials from Bearer authorization and X-Device-UUID headers', function () {
    $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer secret-bearer-token-123';
    $_SERVER['HTTP_X_DEVICE_UUID'] = 'uuid-from-header';

    $creds = $this->deviceAuth->getRequestCredentials();

    expect($creds['token'])->toBe('secret-bearer-token-123');
    expect($creds['uuid'])->toBe('uuid-from-header');
});

test('getRequestCredentials extracts credentials from X-Device-Token and query parameter uuid', function () {
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'custom-device-token-456';
    $_GET['uuid'] = 'uuid-from-query';

    $creds = $this->deviceAuth->getRequestCredentials();

    expect($creds['token'])->toBe('custom-device-token-456');
    expect($creds['uuid'])->toBe('uuid-from-query');
});

test('getRequestCredentials extracts uuid from POST when header is missing', function () {
    $_SERVER['HTTP_X_DEVICE_TOKEN'] = 'token-789';
    $_POST['uuid'] = 'uuid-from-post';

    $creds = $this->deviceAuth->getRequestCredentials();

    expect($creds['token'])->toBe('token-789');
    expect($creds['uuid'])->toBe('uuid-from-post');
});

test('validateDevice returns null when device is not found', function () {
    $this->db->expects($this->once())
        ->method('query')
        ->willReturn('res');

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn(null);

    $result = $this->deviceAuth->validateDevice('unknown-uuid', 'raw-token');
    expect($result)->toBeNull();
});

test('validateDevice returns null when token hash does not match', function () {
    $this->db->expects($this->once())
        ->method('query')
        ->willReturn('res');

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn([
            'unit_id' => 10,
            'refresh_token_hash' => hash('sha256', 'actual-valid-token'),
            'last_seen_ts' => time()
        ]);

    $result = $this->deviceAuth->validateDevice('uuid-10', 'wrong-token');
    expect($result)->toBeNull();
});

test('validateDevice validates token and throttles last_seen update if seen recently', function () {
    $token = 'valid-token-123';
    $recentTs = time() - 60; // 60 seconds ago (< 300 seconds)

    $this->db->expects($this->once())
        ->method('query')
        ->with($this->stringContains('SELECT unit_id, refresh_token_hash'))
        ->willReturn('res');

    $this->db->expects($this->once())
        ->method('getRow')
        ->willReturn([
            'unit_id' => 15,
            'refresh_token_hash' => hash('sha256', $token),
            'last_seen_ts' => $recentTs
        ]);

    // Should NOT call UPDATE since it was seen 60 seconds ago (< 300s throttle)
    $result = $this->deviceAuth->validateDevice('uuid-15', $token);
    expect($result)->toBe(15);
});

test('validateDevice validates token and updates last_seen if last seen more than 5 minutes ago', function () {
    $token = 'valid-token-456';
    $oldTs = time() - 400; // 400 seconds ago (>= 300 seconds)

    $this->db->expects($this->exactly(2))
        ->method('query')
        ->willReturnCallback(function ($query) {
            if (str_contains($query, 'SELECT unit_id, refresh_token_hash')) {
                return 'select-res';
            }
            if (str_contains($query, 'UPDATE alarm_device_authorized SET last_seen = NOW()')) {
                return true;
            }
            return false;
        });

    $this->db->expects($this->once())
        ->method('getRow')
        ->with('select-res')
        ->willReturn([
            'unit_id' => 20,
            'refresh_token_hash' => hash('sha256', $token),
            'last_seen_ts' => $oldTs
        ]);

    $result = $this->deviceAuth->validateDevice('uuid-20', $token);
    expect($result)->toBe(20);
});

