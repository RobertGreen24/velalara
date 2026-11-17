<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use RuntimeException;

final class SystemCommandService
{
    /** The only privileged entry point; no shell and no user-controlled arguments. */
    public function execute(string $action, array $data = []): array
    {
        $input = json_encode(['action' => $action] + $data, JSON_THROW_ON_ERROR);
        $result = Process::timeout(330)->input($input)->run(['/usr/bin/sudo', '-n', '/usr/local/sbin/velalara-helper']);
        $response = json_decode($result->output(), true);
        if (! $result->successful() || ! is_array($response) || isset($response['error'])) {
            throw new RuntimeException(is_array($response) && isset($response['error']) ? $response['error'] : 'Privileged operation failed. Check the server logs.');
        }
        return $response;
    }
}
