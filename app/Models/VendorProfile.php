<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VendorProfile extends Model
{
    use HasFactory;

    /**
     * The attributes that may be assigned during vendor onboarding.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'verification_status',
        'verified_at',
    ];

    /**
     * Cast status and verification timestamps to domain-friendly values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => StoreStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns this vendor profile.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the store application attached to this vendor profile.
     */
    public function store(): HasOne
    {
        return $this->hasOne(Store::class);
    }
}
