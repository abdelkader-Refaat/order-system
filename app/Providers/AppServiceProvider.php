<?php

namespace App\Providers;

use App\Contracts\OrdersQueuePublisherInterface;
use App\Queue\NullOrdersQueuePublisher;
use App\Queue\RabbitMqOrdersQueuePublisher;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OrdersQueuePublisherInterface::class, function () {
            return config('orders.skip_queue_publish')
                ? new NullOrdersQueuePublisher
                : new RabbitMqOrdersQueuePublisher;
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        JsonResource::withoutWrapping();
    }
}
