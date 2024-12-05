<?php

namespace MikeFrancis\LaravelUnleash\Facades;

use Illuminate\Support\Facades\Facade;

class Unleash extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'unleash';
    }
}
