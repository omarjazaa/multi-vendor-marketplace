<?php

namespace App\Services\Payments;

/**
 * Unified Payment Gateway contract (PRD §3.1 & Day 27).
 *
 * Combines strategy execution (pay) with refund handling (refund) through
 * polymorphic resolution, fulfilling the Interface Segregation Principle by
 * composing small focused interfaces.
 */
interface PaymentGatewayInterface extends PaymentStrategyInterface, RefundablePaymentInterface {}
