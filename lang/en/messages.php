<?php

return [
    'broker_unavailable' => 'Message broker (RabbitMQ) is not reachable. On your machine: docker compose up -d rabbitmq. If the app runs inside Docker, use host "rabbitmq" not 127.0.0.1 — restart the app container after php artisan config:clear (stale config cache often pins 127.0.0.1).',
    'auth' => [
        'registered' => 'Registration completed successfully.',
        'logged_in' => 'Signed in successfully.',
        'logged_out' => 'Signed out successfully.',
        'profile' => 'User profile.',
        'profile_updated' => 'Profile updated successfully.',
    ],
    'order' => [
        'created' => 'Order created successfully.',
        'retrieved' => 'Order retrieved successfully.',
    ],
    'errors' => [
        'generic' => 'Something went wrong. Please try again.',
        'forbidden' => 'You do not have permission to perform this action.',
        'not_found' => 'Resource not found.',
        'validation_failed' => 'Validation failed.',
    ],
];
