<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected const CACHE_KEY = 'settings.all';

    public const MEMO_KEY = 'settings.memo';

    protected static function all_settings(): array
    {
        return app(self::MEMO_KEY);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all_settings()[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        static::quenDem();
    }

    public static function quenDem(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::MEMO_KEY);
    }

    public static function taiTatCa(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all(),
        );
    }
}
