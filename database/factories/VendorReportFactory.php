<?php

namespace Database\Factories;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use App\Models\Store;
use App\Models\VendorReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VendorReport>
 */
class VendorReportFactory extends Factory
{
    protected $model = VendorReport::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'type' => ReportType::SALES_SUMMARY,
            'status' => ReportStatus::PENDING,
            'parameters' => [],
            'file_path' => null,
            'error_message' => null,
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::COMPLETED,
            'file_path' => 'reports/sample_report.json',
            'completed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ReportStatus::FAILED,
            'error_message' => 'Report generation failed.',
        ]);
    }
}
