<?php

namespace App\Models;

use App\Enums\ReportStatus;
use App\Enums\ReportType;
use Database\Factories\VendorReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorReport extends Model
{
    /** @use HasFactory<VendorReportFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'store_id',
        'type',
        'status',
        'parameters',
        'file_path',
        'error_message',
        'completed_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => ReportType::class,
            'status' => ReportStatus::class,
            'parameters' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    /** The store this report belongs to. */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
