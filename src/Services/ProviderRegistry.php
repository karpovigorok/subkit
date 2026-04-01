<?php

namespace SubKit\Services;

use SubKit\Contracts\PaymentProviderContract;
use RuntimeException;

class ProviderRegistry
{
    /** @var array<string, PaymentProviderContract> */
    private array $providers = [];

    public function register(PaymentProviderContract $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    public function resolve(string $name): PaymentProviderContract
    {
        return $this->providers[$name]
            ?? throw new RuntimeException("Payment provider [{$name}] is not registered.");
    }

    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }
}
