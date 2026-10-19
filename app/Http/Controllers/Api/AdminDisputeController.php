<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\OrderDisputeException;
use App\Exceptions\RefundProcessingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\FilterDisputesRequest;
use App\Http\Requests\RejectDisputeRequest;
use App\Http\Requests\ResolveDisputeRequest;
use App\Http\Resources\DisputeResource;
use App\Http\Traits\ApiResponse;
use App\Models\Dispute;
use App\Services\DisputeRefundService;
use Illuminate\Http\JsonResponse;

class AdminDisputeController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly DisputeRefundService $disputes) {}

    /** List all customer disputes across the platform (role:admin). */
    public function index(FilterDisputesRequest $request): JsonResponse
    {
        $disputes = $this->disputes->forAdmin($request->validated('status'));

        return $this->successResponse([
            'disputes' => DisputeResource::collection($disputes)->response()->getData(true),
        ]);
    }

    /** View a specific dispute detail. */
    public function show(Dispute $dispute): JsonResponse
    {
        return $this->successResponse([
            'dispute' => DisputeResource::make($dispute->load(['order.items', 'user', 'resolver'])),
        ]);
    }

    /** Resolve dispute by issuing a polymorphic gateway refund (role:admin). */
    public function refund(ResolveDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        try {
            $resolved = $this->disputes->resolveDisputeWithRefund(
                dispute: $dispute,
                admin: $request->user(),
                adminNotes: $request->validated('admin_notes'),
                force: (bool) $request->validated('force', true),
            );
        } catch (OrderDisputeException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                ['dispute' => $exception->getMessage()],
                $exception->getCode() ?: 409,
            );
        } catch (InvalidOrderTransitionException $exception) {
            return $this->errorResponse(
                'Order status transition not allowed.',
                ['status' => "An order cannot move from {$exception->fromStatus} to {$exception->toStatus}."],
                409,
            );
        } catch (RefundProcessingException $exception) {
            return $this->errorResponse(
                'Payment gateway refund failed.',
                ['gateway' => $exception->getMessage()],
                422,
            );
        }

        return $this->successResponse(
            ['dispute' => DisputeResource::make($resolved)],
            'Dispute resolved and order refunded successfully.',
        );
    }

    /** Reject a dispute with explanation (role:admin). */
    public function reject(RejectDisputeRequest $request, Dispute $dispute): JsonResponse
    {
        try {
            $rejected = $this->disputes->rejectDispute(
                dispute: $dispute,
                admin: $request->user(),
                adminNotes: $request->validated('admin_notes'),
            );
        } catch (OrderDisputeException $exception) {
            return $this->errorResponse(
                $exception->getMessage(),
                ['dispute' => $exception->getMessage()],
                $exception->getCode() ?: 409,
            );
        }

        return $this->successResponse(
            ['dispute' => DisputeResource::make($rejected)],
            'Dispute rejected.',
        );
    }
}
