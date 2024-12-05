<?php

namespace MikeFrancis\LaravelUnleash\Middleware;

use Closure;
use Illuminate\Http\Request;
use MikeFrancis\LaravelUnleash\Unleash;

class RefreshFeatures
{
    public float $ttlThresholdFactor = 0.75; // when ttl has reached 75%, refresh the cache

    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    public function terminate()
    {
        $unleash = app(Unleash::class);
        if (!$unleash instanceof Unleash) {
            return;
        }
        if (time() + ($unleash->getCacheTTL() * $this->ttlThresholdFactor) > $unleash->getExpires()) {
            $unleash->refreshCache();
        }
    }
}
