<?php

namespace App\Http\Controllers\Api;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Enums\StoreStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\VendorReportRequest;
use App\Http\Traits\ApiResponse;
use App\Jobs\GenerateVendorReportJob;
use App\Models\Store;
use App\Models\VendorReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Background Jobs & Queues (Day 25 — PRD §5 Week 4).
 *
 * Manages asynchronous vendor report generation requests, job dispatching,
 * progress tracking, and file downloads.
 */
class VendorReportController extends Controller
{
    use ApiResponse;

    /**
     * List analytics reports requested by the authenticated vendor.
     */
    public function index(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return $this->errorResponse('You must have an approved store to view analytics reports.', [], 403);
        }

        $query = $store->reports()->latest('id');

        if ($request->filled('status') && $status = ReportStatus::tryFrom((string) $request->query('status'))) {
            $query->where('status', $status);
        }

        if ($request->filled('type') && $type = ReportType::tryFrom((string) $request->query('type'))) {
            $query->where('type', $type);
        }

        $reports = $query->paginate((int) $request->query('per_page', 15));

        return $this->successResponse([
            'reports' => $reports,
        ]);
    }

    /**
     * Queue a new asynchronous analytics report generation job.
     */
    public function store(VendorReportRequest $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return $this->errorResponse('You must have an approved store to request reports.', [], 403);
        }

        /** @var VendorReport $report */
        $report = $store->reports()->create([
            'type' => $request->validated('type'),
            'status' => ReportStatus::PENDING,
            'parameters' => $request->validated('parameters') ?? [],
        ]);

        GenerateVendorReportJob::dispatch($report);

        return $this->successResponse([
            'report' => $report,
        ], 'Report generation dispatched successfully.', 202);
    }

    /**
     * Inspect the status and metadata of a specific report.
     */
    public function show(Request $request, VendorReport $report): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store || $report->store_id !== $store->id) {
            return $this->errorResponse('You are not authorized to view this report.', [], 403);
        }

        return $this->successResponse([
            'report' => $report,
        ]);
    }

    /**
     * Download the completed report file.
     */
    public function download(Request $request, VendorReport $report): JsonResponse|StreamedResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store || $report->store_id !== $store->id) {
            return $this->errorResponse('You are not authorized to download this report.', [], 403);
        }

        if ($report->status !== ReportStatus::COMPLETED || empty($report->file_path)) {
            return $this->errorResponse('The report is not ready for download.', [
                'status' => $report->status->value,
            ], 409);
        }

        if (! Storage::disk('local')->exists($report->file_path)) {
            return $this->errorResponse('The generated report file could not be found.', [], 404);
        }

        return Storage::disk('local')->download(
            $report->file_path,
            basename($report->file_path),
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Resolve the authenticated vendor's approved store.
     */
    private function resolveVendorStore(Request $request): ?Store
    {
        $store = $request->user()?->vendorProfile?->store;

        if (! $store || $store->status !== StoreStatus::APPROVED) {
            return null;
        }

        return $store;
    }
}
