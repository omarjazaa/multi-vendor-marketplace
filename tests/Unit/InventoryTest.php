<?php

namespace Tests\Unit;

use App\Models\Inventory;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    public function test_stock_flags_follow_the_low_stock_threshold(): void
    {
        $this->assertTrue($this->inventory(0, 5)->is_out_of_stock);
        $this->assertFalse($this->inventory(0, 5)->is_low_stock);

        $this->assertTrue($this->inventory(1, 5)->is_low_stock);
        $this->assertFalse($this->inventory(1, 5)->is_out_of_stock);

        $this->assertTrue($this->inventory(5, 5)->is_low_stock);

        $this->assertFalse($this->inventory(6, 5)->is_low_stock);
        $this->assertFalse($this->inventory(6, 5)->is_out_of_stock);

        $this->assertFalse($this->inventory(1, 0)->is_low_stock);
        $this->assertTrue($this->inventory(0, 0)->is_out_of_stock);
    }

    private function inventory(int $quantity, int $threshold): Inventory
    {
        return new Inventory(['quantity' => $quantity, 'low_stock_threshold' => $threshold]);
    }
}
