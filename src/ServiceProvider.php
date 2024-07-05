<?php

namespace MikeFrancis\LaravelUnleash;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider as IlluminateServiceProvider;
use GuzzleHttp\ClientInterface;

class ServiceProvider extends IlluminateServiceProvider
{
    /**
     * Register bindings in the container.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom($this->getConfigPath(), 'unleash');
        $this->app->when(Unleash::class)->needs(ClientInterface::class)->give(Client::class);
        $this->app->singleton('unleash', function ($app) {
            return $app->make(Unleash::class);
        });
    }

    /**
     * Perform post-registration booting of services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes(
            [
                $this->getConfigPath() => config_path('unleash.php'),
            ]
        );

        Blade::if(
            'featureEnabled',
            function (string $feature) {
                $client = app(Client::class);
                $unleash = app(Unleash::class, ['client' => $client]);
                assert($unleash instanceof Unleash);

                return $unleash->isFeatureEnabled($feature);
            }
        );

        Blade::if(
            'featureDisabled',
            function (string $feature) {
                $client = app(Client::class);
                $unleash = app(Unleash::class, ['client' => $client]);
                assert($unleash instanceof Unleash);

                return !$unleash->isFeatureEnabled($feature);
            }
        );
    }

    /**
     * Get the path to the config.
     */
    private function getConfigPath(): string
    {
        return __DIR__ . '/../config/unleash.php';
    }
}
