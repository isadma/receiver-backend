<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Box extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_CLOSED = 'closed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'shop_slug',
        'box_number',
        'status',
    ];

    public function shopName(): string
    {
        return match ($this->shop_slug) {
            'amazon' => 'Amazon',
            'noon' => 'Noon',
            'namshi' => 'Namshi',
            default => $this->shop_slug,
        };
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public static function requireOpen(string $shopSlug, string $boxNumber): self
    {
        $box = self::query()
            ->where('shop_slug', $shopSlug)
            ->where('box_number', $boxNumber)
            ->first();

        if ($box === null) {
            abort(422, 'Сначала выберите короб.');
        }

        if (! $box->isOpen()) {
            abort(422, 'Этот короб закрыт.');
        }

        return $box;
    }

    public static function findByShopAndNumber(string $shopSlug, string $boxNumber): ?self
    {
        return self::query()
            ->where('shop_slug', $shopSlug)
            ->where('box_number', $boxNumber)
            ->first();
    }
}
