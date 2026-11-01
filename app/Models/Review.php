<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int $user_id
 * @property int $rating
 * @property string|null $title
 * @property string $body
 * @property string $status
 * @property bool $verified_purchase
 * @property Carbon|null $moderated_at
 * @property Carbon|null $created_at
 */
class Review extends Model
{
    public const PENDING = 'pending';

    public const APPROVED = 'approved';

    public const REJECTED = 'rejected';

    public const STATUSES = [
        self::PENDING => 'În așteptare',
        self::APPROVED => 'Aprobată',
        self::REJECTED => 'Respinsă',
    ];

    protected $fillable = ['product_id', 'user_id', 'rating', 'title', 'body', 'status', 'verified_purchase', 'moderated_at'];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'verified_purchase' => 'boolean',
            'moderated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Media și numărul de recenzii de pe produs se recalculează la orice schimbare.
        static::saved(fn (Review $review) => self::refreshProductStats($review->product_id));
        static::deleted(fn (Review $review) => self::refreshProductStats($review->product_id));
    }

    public static function refreshProductStats(int $productId): void
    {
        $stats = self::query()
            ->where('product_id', $productId)
            ->where('status', self::APPROVED)
            ->selectRaw('COUNT(*) as total, COALESCE(AVG(rating), 0) as average')
            ->toBase()
            ->first();

        Product::query()->whereKey($productId)->update([
            'reviews_count' => (int) data_get($stats, 'total', 0),
            'rating_avg' => round((float) data_get($stats, 'average', 0), 2),
        ]);
    }

    /** @param Builder<Review> $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', self::APPROVED);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
