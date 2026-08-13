<?php

namespace SubKit\Models;

final class CheckoutResult
{
    public function __construct(
        public readonly string $url,
        public readonly bool $directlySubscribed,
    ) {}
}
