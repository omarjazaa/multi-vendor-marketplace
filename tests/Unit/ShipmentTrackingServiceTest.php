<?php

namespace Tests\Unit;

use App\Enums\ShipmentStatus;
use App\Models\OrderItem;
use App\Models\Shipment;
use App\Services\ShipmentTrackingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentTrackingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_deterministic_tracking_numbers_with_default_prefix(): void
    {
        $service = new ShipmentTrackingService;

        $trackingNumber = $service->generate(12, 34);

        $this->assertSame('TRK-12-34', $trackingNumber);
    }

    public function test_it_uses_configured_tracking_prefix(): void
    {
        config(['marketplace.shipments.tracking_prefix' => 'EXP']);

        $service = new ShipmentTrackingService;

        $this->assertSame('EXP-99-101', $service->generate(99, 101));
    }

    public function test_constructor_prefix_overrides_configuration(): void
    {
        config(['marketplace.shipments.tracking_prefix' => 'CONFIG']);

        $service = new ShipmentTrackingService('CUSTOM');

        $this->assertSame('CUSTOM-5-10', $service->generate(5, 10));
    }

    public function test_it_records_and_finds_shipment_by_order_item(): void
    {
        $item = OrderItem::factory()->create();
        $service = new ShipmentTrackingService;

        $this->assertNull($service->findByOrderItem($item));

        $shipment = $service->record((int) $item->order_id, (int) $item->id, 'TRK-TEST-1');

        $this->assertInstanceOf(Shipment::class, $shipment);
        $this->assertSame('TRK-TEST-1', $shipment->tracking_number);
        $this->assertSame(ShipmentStatus::PROCESSING, $shipment->status);
        $this->assertNotNull($shipment->shipped_at);

        $found = $service->findByOrderItem($item);
        $this->assertNotNull($found);
        $this->assertSame($shipment->id, $found->id);
        $this->assertSame($item->id, $found->orderItem->id);
        $this->assertSame($item->order_id, $found->order->id);
    }
}
