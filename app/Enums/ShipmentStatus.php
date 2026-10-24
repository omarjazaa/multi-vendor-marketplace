<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case PROCESSING = 'processing';
    case IN_TRANSIT = 'in_transit';
    case OUT_FOR_DELIVERY = 'out_for_delivery';
    case DELIVERED = 'delivered';
    case FAILED_DELIVERY = 'failed_delivery';

    /**
     * Human-readable label for tracking timelines.
     */
    public function label(): string
    {
        return match ($this) {
            self::PROCESSING => 'Processing at Warehouse',
            self::IN_TRANSIT => 'In Transit with Carrier',
            self::OUT_FOR_DELIVERY => 'Out for Delivery',
            self::DELIVERED => 'Delivered to Recipient',
            self::FAILED_DELIVERY => 'Delivery Attempt Failed',
        };
    }

    /**
     * Status icon for visual timeline representations.
     */
    public function icon(): string
    {
        return match ($this) {
            self::PROCESSING => '📦',
            self::IN_TRANSIT => '🚚',
            self::OUT_FOR_DELIVERY => '🛵',
            self::DELIVERED => '✅',
            self::FAILED_DELIVERY => '⚠️',
        };
    }
}
