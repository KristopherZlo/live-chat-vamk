<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = [
        'key',
        'value',
    ];

    /**
     * Retrieve a value for the given key.
     */
    public static function getValue(string $key, mixed $default = null): mixed
    {
        $cacheKey = "settings:{$key}";

        $value = Cache::rememberForever($cacheKey, function () use ($key) {
            return static::query()->where('key', $key)->value('value');
        });

        return $value ?? $default;
    }

    /**
     * Store or update a value for the given key.
     */
    public static function setValue(string $key, mixed $value): Setting
    {
        Cache::forget("settings:{$key}");

        return static::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $value],
        );
    }
}
