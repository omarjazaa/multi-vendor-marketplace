<?php

namespace App\Jobs;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\VendorReport;
use App\Queries\Vendor\VendorBestSellersQuery;
use App\Queries\Vendor\VendorDashboardQuery;
use App\Queries\Vendor\VendorRevenueOverTimeQuery;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Background Jobs & Queues (Day 25 — PRD §5 Week 4).
 *
 * Executes asynchronous analytics calculation and persists exportable report
 * artifacts in local storage without blocking the API request thread.
 */
class GenerateVendorReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    public function __construct(
        public readonly VendorReport $report
    ) {
        $this->queue = 'reports';
    }

    public function handle(
        VendorDashboardQuery $dashboardQuery,
        VendorBestSellersQuery $bestSellersQuery,
        VendorRevenueOverTimeQuery $revenueQuery
    ): void {
        $this->report->update([
            'status' => ReportStatus::PROCESSING,
        ]);

        try {
            $data = $this->buildReportData(
                $dashboardQuery,
                $bestSellersQuery,
                $revenueQuery
            );

            $filePath = sprintf(
                'reports/store_%d/report_%d_%s.json',
                $this->report->store_id,
                $this->report->id,
                now()->format('Ymd_His')
            );

            $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            Storage::disk('local')->put($filePath, $content);

            $this->report->update([
                'status' => ReportStatus::COMPLETED,
                'file_path' => $filePath,
                'error_message' => null,
                'completed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Vendor report generation failed.', [
                'report_id' => $this->report->id,
                'store_id' => $this->report->store_id,
                'error' => $exception->getMessage(),
            ]);

            $this->report->update([
                'status' => ReportStatus::FAILED,
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function buildReportData(
        VendorDashboardQuery $dashboardQuery,
        VendorBestSellersQuery $bestSellersQuery,
        VendorRevenueOverTimeQuery $revenueQuery
    ): array {
        $storeId = $this->report->store_id;
        $params = $this->report->parameters ?? [];

        return match ($this->report->type) {
            ReportType::SALES_SUMMARY => [
                'report_type' => 'sales_summary',
                'store_id' => $storeId,
                'generated_at' => now()->toIso8601String(),
                'summary' => $dashboardQuery->execute($storeId),
                'top_products' => $bestSellersQuery->execute($storeId, 10),
            ],
            ReportType::BEST_SELLERS => [
                'report_type' => 'best_sellers',
                'store_id' => $storeId,
                'generated_at' => now()->toIso8601String(),
                'limit' => (int) ($params['limit'] ?? 10),
                'best_sellers' => $bestSellersQuery->execute($storeId, (int) ($params['limit'] ?? 10)),
            ],
            ReportType::REVENUE_TREND => $this->buildRevenueTrendReport($storeId, $params, $revenueQuery),
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function buildRevenueTrendReport(int $storeId, array $params, VendorRevenueOverTimeQuery $revenueQuery): array
    {
        if (! empty($params['from'])) {
            $startDate = Carbon::parse($params['from'])->startOfDay();
            $endDate = ! empty($params['to']) ? Carbon::parse($params['to'])->endOfDay() : now()->endOfDay();
        } elseif (! empty($params['days'])) {
            $startDate = now()->subDays((int) $params['days'])->startOfDay();
            $endDate = now()->endOfDay();
        } else {
            $startDate = now()->subDays(30)->startOfDay();
            $endDate = now()->endOfDay();
        }

        return [
            'report_type' => 'revenue_trend',
            'store_id' => $storeId,
            'generated_at' => now()->toIso8601String(),
            'period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'revenue_over_time' => $revenueQuery->execute($storeId, $startDate, $endDate),
        ];
    }
}
