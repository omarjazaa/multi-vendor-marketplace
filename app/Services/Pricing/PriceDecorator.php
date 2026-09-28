<?php

namespace App\Services\Pricing;

/** Shared plumbing for decorators: wrap the next component in the chain. */
abstract class PriceDecorator implements PriceComponentInterface
{
    public function __construct(protected readonly PriceComponentInterface $inner) {}
}
