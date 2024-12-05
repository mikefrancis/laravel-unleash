<?php

namespace MikeFrancis\LaravelUnleash\Strategies;

use Illuminate\Http\Request;
use MikeFrancis\LaravelUnleash\Strategies\Contracts\Strategy;

class DefaultStrategy implements Strategy
{
    /**
     * @unused-param $params
     * @unused-param $request
     */
    public function isEnabled(array $params, Request $request): bool
    {
        return true;
    }
}
