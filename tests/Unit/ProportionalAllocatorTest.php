<?php

namespace Tests\Unit;

use App\Services\Checkout\ProportionalAllocator;
use Tests\TestCase;

class ProportionalAllocatorTest extends TestCase
{
    private ProportionalAllocator $allocator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->allocator = new ProportionalAllocator;
    }

    public function test_a_single_weight_receives_the_whole_amount(): void
    {
        $this->assertSame([6.75], $this->allocator->split(6.75, [134.98]));
    }

    public function test_repeating_thirds_are_resolved_by_largest_remainder(): void
    {
        // 100.00 over three equal shares leaves one cent over: it goes to the
        // first position (remainder ties break by position) and the three
        // parts still sum to exactly 100.00.
        $parts = $this->allocator->split(100.0, [1.0, 1.0, 1.0]);

        $this->assertSame([33.34, 33.33, 33.33], $parts);
        $this->assertPartsSumTo(100.0, $parts);
    }

    public function test_leftover_cents_follow_the_biggest_remainders(): void
    {
        // 10.00 split 2:1 is 6.666... and 3.333...: the leftover cent lands
        // on the bigger share.
        $parts = $this->allocator->split(10.0, [2.0, 1.0]);

        $this->assertSame([6.67, 3.33], $parts);
        $this->assertPartsSumTo(10.0, $parts);
    }

    public function test_the_two_vendor_tax_split_used_by_checkout_is_exact(): void
    {
        // The Day 16 multi-vendor scenario: 6.75 tax over 104.98 + 30.00.
        $parts = $this->allocator->split(6.75, [104.98, 30.00]);

        $this->assertSame([5.25, 1.50], $parts);
        $this->assertPartsSumTo(6.75, $parts);
    }

    public function test_a_full_discount_coupon_allocates_the_whole_subtotal(): void
    {
        // A 100% coupon discounts the cart subtotal; each vendor's share is
        // simply its own subtotal, in whole cents.
        $parts = $this->allocator->split(49.99, [20.00, 29.99]);

        $this->assertSame([20.00, 29.99], $parts);
        $this->assertPartsSumTo(49.99, $parts);
    }

    public function test_zero_amounts_and_zero_weights_allocate_nothing(): void
    {
        $this->assertSame([0.0, 0.0], $this->allocator->split(0.0, [10.0, 20.0]));
        $this->assertSame([0.0, 0.0], $this->allocator->split(5.0, [0.0, 0.0]));
    }

    public function test_awkward_weights_never_lose_a_cent(): void
    {
        $parts = $this->allocator->split(7.77, [5.55, 10.10, 2.22]);

        $this->assertCount(3, $parts);
        $this->assertPartsSumTo(7.77, $parts);
    }

    /**
     * Compare in integer cents so the assertion is exact rather than
     * float-lucky.
     *
     * @param  list<float>  $parts
     */
    private function assertPartsSumTo(float $amount, array $parts): void
    {
        $cents = array_map(fn (float $part): int => (int) round($part * 100), $parts);

        $this->assertSame((int) round($amount * 100), array_sum($cents));
    }
}
