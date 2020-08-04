<?php

namespace NotificationChannels\Sailthru;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

class SailthruServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register()
    {
        $this->app->singleton(
            SailthruClient::class,
            function (Application $app) {
                return new SailthruClient(
                    $app['config']->get('services.sailthru.api_key'),
                    $app['config']->get('services.sailthru.secret')
                );
            }
        );
    }

    /**
     * {@inheritdoc}
     */
    public function provides()
    {
        return [SailthruClient::class];
    }
}
