<?php

namespace App\Services\Trading;

use App\Contracts\Trading\StrategyAdapter;
use Illuminate\Contracts\Container\Container;

final class StrategyRegistry
{
    public function __construct(private readonly Container $container) {}

    public function activeKey(): string
    {
        $key = (string) config('strategies.active', 'smart_volume_v1');
        $this->definition($key);

        return $key;
    }

    /** @return array<string, string> */
    public function options(): array
    {
        $options = [];
        foreach ((array) config('strategies.adapters', []) as $key => $definition) {
            if ($definition['enabled'] ?? true) {
                $options[$key] = (string) ($definition['label'] ?? $key);
            }
        }

        return $options;
    }

    public function adapter(string $key): StrategyAdapter
    {
        $adapter = $this->container->make($this->definition($key)['adapter']);
        if (! $adapter instanceof StrategyAdapter) {
            throw new \InvalidArgumentException("Strategy {$key} must implement StrategyAdapter.");
        }

        return $adapter;
    }

    public function configuration(string $key, string $symbol = '', array $overrides = []): array
    {
        $definition = $this->definition($key);
        $base = isset($definition['config']) ? (array) config($definition['config'], []) : [];
        $symbols = isset($definition['symbol_overrides']) ? (array) config($definition['symbol_overrides'], []) : [];

        return array_replace($base, (array) ($definition['defaults'] ?? []), (array) ($symbols[strtoupper(trim($symbol))] ?? []), $overrides);
    }

    public function definition(string $key): array
    {
        $definition = config('strategies.adapters', [])[$key] ?? null;
        if (! is_array($definition) || ! ($definition['enabled'] ?? true) || ! isset($definition['adapter'])) {
            throw new \InvalidArgumentException("Unknown or disabled strategy: {$key}");
        }

        return $definition;
    }
}
