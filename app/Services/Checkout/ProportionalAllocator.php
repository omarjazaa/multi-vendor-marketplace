<?php

namespace App\Services\Checkout;

/**
 * Splits a cart-wide money amount (the discount or the tax total) across
 * the per-vendor order subtotals proportionally, using the largest-remainder
 * method: every part is floored to whole cents, then the leftover cents are
 * handed out biggest-remainder-first so the parts always sum back to the
 * cart-wide amount in exact cents. Rounding each order independently could
 * otherwise invent or lose a cent when one cart becomes many.
 */
class ProportionalAllocator
{
    /**
     * @param  float  $amount  cart-wide money amount to distribute
     * @param  list<float>  $weights  per-order subtotal shares, in order
     * @return list<float> per-order parts in the same order; their exact-cent
     *                     sum equals the amount (both rounded to 2dp)
     */
    public function split(float $amount, array $weights): array
    {
        $shares = array_map(
            fn (float $weight): int => $this->toCents($weight),
            array_values($weights),
        );

        return array_map(
            fn (int $cents): float => $cents / 100,
            $this->apportion($this->toCents($amount), $shares),
        );
    }

    /**
     * Largest-remainder apportionment in integer cents.
     *
     * All arithmetic stays in integers so float noise can never unbalance the
     * result: each position gets floor(amount × weight ÷ total weight), then
     * the leftover cents (always fewer than the number of positions) go one
     * apiece to the positions with the biggest remainders — ties broken by
     * the larger weight, then by earlier position, keeping the split stable.
     *
     * @param  list<int>  $weights  per-position weights in cents
     * @return list<int>
     */
    private function apportion(int $amountCents, array $weights): array
    {
        $positions = array_keys($weights);
        $parts = array_fill(0, count($weights), 0);
        $totalWeight = array_sum($weights);

        if ($amountCents <= 0 || $totalWeight <= 0) {
            return $parts;
        }

        $remainders = [];

        foreach ($weights as $index => $weight) {
            $exact = $amountCents * $weight;
            $parts[$index] = intdiv($exact, $totalWeight);
            $remainders[$index] = $exact % $totalWeight;
        }

        $leftover = $amountCents - array_sum($parts);

        usort(
            $positions,
            fn (int $left, int $right): int => ($remainders[$right] <=> $remainders[$left])
                ?: ($weights[$right] <=> $weights[$left])
                ?: ($left <=> $right),
        );

        // Every floor discarded less than one cent, so the leftover is always
        // smaller than the number of positions: one cent each is enough.
        for ($i = 0; $i < $leftover; $i++) {
            $parts[$positions[$i]] += 1;
        }

        return $parts;
    }

    /** Ordinary round-half-up conversion, matching the API's 2dp money format. */
    private function toCents(float $amount): int
    {
        return (int) round($amount * 100);
    }
}
