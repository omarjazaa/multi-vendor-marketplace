<?php

namespace App\Models;

use App\Enums\StoreStatus;
use Database\Factories\StoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Store extends Model
{
    /** @use HasFactory<StoreFactory> */
    use HasFactory;

    /**
     * The attributes that may be assigned for a store application.
     *
     * @var list<string>
     */
    protected $fillable = [
        'vendor_profile_id',
        'name',
        'slug',
        'description',
        'status',
    ];

    /**
     * Cast the store lifecycle status to its enum.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
        ];
    }

    /**
     * Get the vendor profile that owns this store.
     */
    public function vendorProfile(): BelongsTo
    {
        return $this->belongsTo(VendorProfile::class);
    }

    /** Get products listed by this store. */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
