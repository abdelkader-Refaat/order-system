<?php

namespace App\Console\Commands;

use App\Support\RabbitMqEndpoint;
use Illuminate\Console\Command;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * Diagnoses why the API cannot publish while orders:consume might still work (e.g. cached config, wrong cwd).
 */
class OrdersCheckBrokerCommand extends Command
{
    protected $signature = 'orders:check-broker';

    protected $description = 'Test TCP + AMQP to RabbitMQ using the same config as the app';

    public function handle(): int
    {
        $p = RabbitMqEndpoint::connectionParams();
        $h = $p['host'];
        $port = $p['port'];
        $user = $p['user'];
        $pass = $p['password'];
        $vhost = $p['vhost'];

        $this->info("Using tcp://{$h}:{$port} (vhost: {$vhost}) — runtime host (Docker-safe even if config is cached).");

        if (app()->configurationIsCached()) {
            $this->warn('Configuration is cached (bootstrap/cache/config.php). Values were baked at cache time; run `php artisan optimize:clear` after changing .env.');
        }

        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            "tcp://{$h}:{$port}",
            $errno,
            $errstr,
            5,
            STREAM_CLIENT_CONNECT
        );

        if ($socket === false) {
            $this->error("TCP failed: [{$errno}] {$errstr}");
            $this->newLine();
            $this->line('Fixes that usually resolve "Connection refused" for <info>127.0.0.1:5672</info>:');
            $this->line('  1. Start the broker: <info>docker compose up -d rabbitmq</info> (from this project root)');
            $this->line('  2. Clear stale config: <info>php artisan optimize:clear</info>');
            $this->line('  3. If <info>php artisan orders:check-broker</info> works here but the API still fails, Herd may be using another project path or cached config — restart Herd or clear config cache again.');

            return self::FAILURE;
        }

        fclose($socket);
        $this->info('TCP connection succeeded.');

        try {
            $connection = new AMQPStreamConnection($h, $port, $user, $pass, $vhost);
            $connection->close();
            $this->info('AMQP login succeeded.');
        } catch (\Throwable $e) {
            $this->error('AMQP failed: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
