<?php

namespace App\Domain\Settings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key/value application settings, cached per key. Use get()/set() rather than
 * querying directly so the cache stays consistent.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public $timestamps = true;

    private const CACHE_PREFIX = 'setting:';

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = Cache::rememberForever(self::CACHE_PREFIX.$key, fn () => static::query()->where('key', $key)->value('value'));

        return $value ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_PREFIX.$key);
    }

    /**
     * The effective booking-slip terms as an array of clauses — the admin
     * override if present, otherwise the config default.
     *
     * @return array<int, string>
     */
    public static function bookingTerms(): array
    {
        $override = self::get('booking_terms');

        if (filled($override)) {
            return collect(preg_split('/\r\n|\r|\n/', $override))
                ->map(fn ($line) => trim($line))
                ->filter()
                ->values()
                ->all();
        }

        return config('car4sales.booking_terms', []);
    }

    /** The effective booking terms as newline-joined text (for editing). */
    public static function bookingTermsText(): string
    {
        return implode("\n", self::bookingTerms());
    }
}
