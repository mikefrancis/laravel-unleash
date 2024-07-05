<?php

namespace MikeFrancis\LaravelUnleash;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use JsonException;
use MikeFrancis\LaravelUnleash\Strategies\Contracts\DynamicStrategy;
use MikeFrancis\LaravelUnleash\Strategies\Contracts\Strategy;

class Unleash
{
    public const DEFAULT_CACHE_TTL = 15;

    protected $client;
    protected $cache;
    protected $config;
    protected $request;
    protected $features;
    protected $expires;

    public function __construct(Client $client, Cache $cache, Config $config, Request $request)
    {
        $this->client = $client;
        $this->cache = $cache;
        $this->config = $config;
        $this->request = $request;
    }

    public function getFeatures(): array
    {
        if ($this->isFresh()) {
            return $this->features;
        }

        if (!$this->config->get('unleash.isEnabled')) {
            return [];
        }

        try {
            if ($this->config->get('unleash.cache.isEnabled')) {
                $data = $this->getCachedFeatures();
            } else {
                $data = $this->fetchFeatures();
            }
        } catch (TransferException | JsonException) {
            if ($this->config->get('unleash.cache.failover') === true) {
                $data = $this->cache->get('unleash.failover', []);
            }
        }

        $this->features = Arr::get($data, 'features', []);
        $this->expires = Arr::get($data, 'expires', $this->getExpires());

        return $this->features;
    }

    public function getFeature(string $name)
    {
        $features = $this->getFeatures();

        return Arr::first(
            $features,
            function (array $unleashFeature) use ($name) {
                return $name === $unleashFeature['name'];
            }
        );
    }

    public function isFeatureEnabled(string $name, ...$args): bool
    {
        $feature = $this->getFeature($name);
        $isEnabled = Arr::get($feature, 'enabled', false);

        if (!$isEnabled) {
            return false;
        }

        $strategies = Arr::get($feature, 'strategies', []);
        $allStrategies = $this->config->get('unleash.strategies', []);

        if (count($strategies) === 0) {
            return $isEnabled;
        }

        foreach ($strategies as $strategyData) {
            $className = $strategyData['name'];

            if (!array_key_exists($className, $allStrategies)) {
                continue;
            }

            if (is_callable($allStrategies[$className])) {
                $strategy = $allStrategies[$className]();
            } else {
                $strategy = new $allStrategies[$className]();
            }

            if (!$strategy instanceof Strategy && !$strategy instanceof DynamicStrategy) {
                throw new \Exception($className . ' does not implement base Strategy/DynamicStrategy.');
            }

            $params = Arr::get($strategyData, 'parameters', []);

            if ($strategy->isEnabled($params, $this->request, ...$args)) { // @phan-suppress-current-line PhanParamTooManyUnpack
                return true;
            }
        }

        return false;
    }

    public function isFeatureDisabled(string $name, ...$args): bool
    {
        return !$this->isFeatureEnabled($name, ...$args);
    }

    public function refreshCache()
    {
        if ($this->config->get('unleash.isEnabled') && $this->config->get('unleash.cache.isEnabled')) {
            $this->fetchFeatures();
        }
    }

    protected function isFresh(): bool
    {
        return $this->expires > time();
    }

    protected function getCachedFeatures(): array
    {
        return $this->cache->get('unleash.cache', function () {return $this->fetchFeatures();});
    }

    public function getCacheTTL(): int
    {
        return $this->config->get('unleash.cache.ttl', self::DEFAULT_CACHE_TTL);
    }

    protected function setExpires(): int
    {
        return $this->expires = $this->getCacheTTL() + time();
    }

    public function getExpires(): int
    {
        return $this->expires ?? $this->setExpires();
    }

    protected function fetchFeatures(): array
    {
        $response = $this->client->get($this->config->get('unleash.featuresEndpoint'));

        $data = (array) json_decode((string)$response->getBody(), true, 512, JSON_BIGINT_AS_STRING + JSON_THROW_ON_ERROR);

        $data['expires'] = $this->setExpires();

        $this->cache->set('unleash.cache', $data, $this->getCacheTTL());
        $this->cache->forever('unleash.failover', $data);

        $this->features = Arr::get($data, 'features', []);

        return $data;
    }
}
