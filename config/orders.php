<?php

$envBool = static function (mixed $value, bool $default): bool {
    if ($value === null || $value === '') {
        return $default;
    }

    if (is_bool($value)) {
        return $value;
    }

    if (! is_string($value)) {
        return $default;
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

    return $parsed ?? $default;
};

return [

    /*
    | If true, order creation skips RabbitMQ publish (API works without a broker).
    | The worker will have nothing to consume until you disable this and run RabbitMQ.
    */
    'skip_queue_publish' => $envBool(env('ORDERS_SKIP_QUEUE_PUBLISH'), false),

    /*
    | When false, connection/publish errors bubble up (500 if the broker is down).
    | When true, failures are logged and the request still succeeds (order stays pending).
    */
    'graceful_queue_failure' => $envBool(env('ORDERS_GRACEFUL_QUEUE_FAILURE'), false),

    /*
    | When true, web SAPI (e.g. php-fpm / Herd) can republish via `php artisan orders:publish`
    | after AMQPIOException — CLI often reaches Docker on 127.0.0.1 when FPM does not.
    | Not used for cli, cli-server (artisan serve), or phpdbg.
    */
    'fallback_cli_publish' => $envBool(env('ORDERS_PUBLISH_FALLBACK_CLI'), true),

    /*
    | Local only: if RabbitMQ publish fails or is skipped, mark the order processed in the same
    | request (simulates the worker). Set false when you want to test real AMQP + orders:consume.
    */
    'dev_mark_processed_without_broker' => $envBool(env('ORDERS_LOCAL_MARK_PROCESSED_WITHOUT_BROKER'), true),

];
