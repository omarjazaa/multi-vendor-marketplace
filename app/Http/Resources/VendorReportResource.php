<?php

namespace App\Http\Resources;

use App\Models\VendorReport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin VendorReport */
class VendorReportResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'type' => $this->type?->value ?? (string) $this->type,
            'status' => $this->status?->value ?? (string) $this->status,
            'parameters' => $this->parameters ?? [],
            'file_path' => $this->file_path,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'error_message' => $this->error_message,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
