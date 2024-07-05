<?php

namespace MikeFrancis\LaravelUnleash\Strategies;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MikeFrancis\LaravelUnleash\Strategies\Contracts\Strategy;

class ApplicationHostnameStrategy implements Strategy
{
    public function isEnabled(array $params, Request $request): bool
    {
        $hostNamesString = Arr::get($params, 'hostNames', '');

        $hostNames = explode(',', $hostNamesString);

        return $hostNamesString && in_array($request->getHost(), $hostNames);
    }
}
