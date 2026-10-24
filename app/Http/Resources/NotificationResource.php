<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

/** @mixin DatabaseNotification */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $payload = is_array($this->data) ? $this->data : [];
        $title = $payload['title'] ?? Str::headline(class_basename($this->type));
        $body = $payload['body'] ?? $payload['message'] ?? '';

        return [
            'id' => (string) $this->id,
            'type' => (string) $this->type,
            'title' => (string) $title,
            'body' => (string) $body,
            'data' => $payload,
            'is_read' => $this->read_at !== null,
            'read_at' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
        ];
    }
}
