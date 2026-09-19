<?php

namespace App\Services;

use App\Enums\StoreStatus;
use App\Events\VendorApproved;
use App\Exceptions\StoreApplicationAlreadyReviewed;
use App\Models\Store;
use App\Repositories\Contracts\StoreRepositoryInterface;
use Illuminate\Database\DatabaseManager;

class VendorApprovalService
{
    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Approve a pending store application and dispatch its domain event.
     *
     * @throws StoreApplicationAlreadyReviewed
     */
    public function approve(Store $store): Store
    {
        $approved = $this->review($store, StoreStatus::APPROVED);
        event(new VendorApproved($approved));

        return $approved;
    }

    /**
     * Reject a pending store application.
     *
     * @throws StoreApplicationAlreadyReviewed
     */
    public function reject(Store $store): Store
    {
        return $this->review($store, StoreStatus::REJECTED);
    }

    /**
     * Apply one review decision to a still-pending application.
     */
    private function review(Store $store, StoreStatus $status): Store
    {
        if ($store->status !== StoreStatus::PENDING) {
            throw new StoreApplicationAlreadyReviewed('This store application has already been reviewed.');
        }

        return $this->database->transaction(
            fn (): Store => $this->stores->updateReviewStatus($store, $status),
        );
    }
}
