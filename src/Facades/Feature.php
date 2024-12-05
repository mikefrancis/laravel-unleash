<?php

namespace MikeFrancis\LaravelUnleash\Facades;

use Illuminate\Support\Facades\Facade;

class Feature extends Facade
{
    public static function enabled(string $feature, ...$args): bool
    {
        return static::isFeatureEnabled($feature, ...$args);
    }

    public static function disabled(string $feature, ...$args): bool
    {
        return static::isFeatureDisabled($feature, ...$args);
    }

    public static function all(): array
    {
        return static::getFeatures();
    }

    public static function get(string $name)
    {
        return static::getFeature($name);
    }

    protected static function getFacadeAccessor()
    {
        return 'unleash';
    }
}
