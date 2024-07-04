<?php

namespace MikeFrancis\LaravelUnleash;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\InvalidArgumentException;
use GuzzleHttp\Exception\TransferException;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use MikeFrancis\LaravelUnleash\Strategies\Contracts\DynamicStrategy;
use MikeFrancis\LaravelUnleash\Strategies\Contracts\Strategy;
use Symfony\Component\HttpFoundation\Exception\JsonException;
use function GuzzleHttp\json_decode;

class Unleash
{
    const DEFAULT_CACHE_TTL = 15;

    protected $client;
    protected $cache;
    protected $config;
    protected $request;
    protected $features;
    protected $expires;

    public function __construct(ClientInterface $client, Cache $cache, Config $config, Request $request)
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

        try {
            $this->features = $this->getCachedFeatures();

            // Always store the failover cache, in case it is turned on during failure scenarios.
            $this->cache->forever('unleash.features.failover', $this->features);

            return $this->features;
        } catch (TransferException | JsonException $e) {
            if ($this->config->get('unleash.cache.failover') === true) {
                return $this->cache->get('unleash.features.failover', []);
            }
        }

        return [];
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
                $strategy = new $allStrategies[$className];
            }

            if (!$strategy instanceof Strategy && !$strategy instanceof DynamicStrategy) {
                throw new \Exception($className . ' does not implement base Strategy/DynamicStrategy.');
            }

            $params = Arr::get($strategyData, 'parameters', []);

            if ($strategy->isEnabled($params, $this->request, ...$args)) {
                return true;
            }
        }

        return false;
    }

    public function isFeatureDisabled(string $name, ...$args): bool
    {
        return !$this->isFeatureEnabled($name, ...$args);
    }

    protected function isFresh(): bool
    {
        return $this->expires > time();
    }

    protected function getCachedFeatures(): array
    {
        if (!$this->config->get('unleash.isEnabled')) {
            return [];
        }

        if ($this->config->get('unleash.cache.isEnabled')) {
            $this->setExpires();

            return $this->cache->remember(
                'unleash',
                $this->getCacheTTL(),
                function () {
                    return $this->fetchFeatures();
                }
            );
        }

        return $this->features ?? $this->features = $this->fetchFeatures();
    }

    protected function getCacheTTL(): int
    {
        return $this->config->get('unleash.cache.ttl', self::DEFAULT_CACHE_TTL);
    }

    protected function setExpires(): void
    {
        $this->expires = $this->getCacheTTL() + time();
    }

    protected function fetchFeatures(): array
    {
        $response = $this->client->get($this->config->get('unleash.featuresEndpoint'));

        try {
            $data = json_decode((string)$response->getBody(), true, 512, \JSON_BIGINT_AS_STRING);
        } catch (InvalidArgumentException $e) {
            throw new JsonException('Could not decode unleash response body.', $e->getCode(), $e);
        }

        $this->setExpires();

        return $this->formatResponse($data);
    }

    protected function formatResponse($data): array
    {
        return Arr::get($data, 'features', []);
    }
}
