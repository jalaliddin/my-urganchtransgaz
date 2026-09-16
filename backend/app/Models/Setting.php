<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

#[Fillable(['key', 'value', 'group'])]
class Setting extends Model
{
    private const CACHE_KEY = 'settings.all';

    /**
     * Read one setting, falling back to $default (typically the
     * matching config() value) when it has never been set — so an
     * admin who never opens the settings page changes nothing.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $values = self::allCached();

        return array_key_exists($key, $values) ? $values[$key] : $default;
    }

    /**
     * Write one setting under the given group and refresh the cache.
     */
    public static function set(string $key, mixed $value, string $group): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value, 'group' => $group]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Cached as a plain array, not a Collection — caching an Eloquent/
     * Support object through the database cache driver risks an
     * "incomplete object" unserialize error if the class isn't fully
     * autoloaded yet at read time; a plain array sidesteps that entirely.
     *
     * @return array<string, mixed>
     */
    private static function allCached(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addHour(),
            fn () => self::query()->pluck('value', 'key')->all()
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }
}
