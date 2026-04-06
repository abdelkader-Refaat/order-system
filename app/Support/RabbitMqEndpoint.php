<?php

namespace App\Support;

/**
 * Resolves RabbitMQ connection values at runtime (not only from cached config).
 *
 * Inside Docker, 127.0.0.1 is the container itself — the broker is the Compose service {@code rabbitmq}.
 * Stale {@code bootstrap/cache/config.php} from the host often freezes 127.0.0.1; this class corrects that.
 */
final class RabbitMqEndpoint
{
    /**
     * @return array{host: string, port: int, user: string, password: string, vhost: string, queue: string}
     */
    public static function connectionParams(): array
    {
        $rabbit = config('queue.connections.rabbitmq');
        $first = $rabbit['hosts'][0] ?? [];

        $host = $first['host'] ?? '127.0.0.1';
        $host = is_string($host) && $host !== '' ? $host : '127.0.0.1';

        if (file_exists('/.dockerenv') && in_array($host, ['127.0.0.1', 'localhost'], true)) {
            $host = 'rabbitmq';
        }

        return [
            'host' => $host,
            'port' => (int) ($first['port'] ?? 5672),
            'user' => (string) ($first['user'] ?? 'guest'),
            'password' => (string) ($first['password'] ?? 'guest'),
            'vhost' => (string) ($first['vhost'] ?? '/'),
            'queue' => is_string($rabbit['queue'] ?? null) && ($rabbit['queue'] ?? '') !== ''
                ? $rabbit['queue']
                : 'orders_queue',
        ];
    }
}
