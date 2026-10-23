<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function getValue(string $key, mixed $default = null): mixed
    {
        $values = Cache::remember('website.settings', 300, fn () => static::pluck('value', 'key')->all());

        return $values[$key] ?? $default;
    }

    public static function whatsapp(string $message = ''): string
    {
        return 'https://wa.me/'.preg_replace('/\D/', '', static::getValue('whatsapp', '97333640001')).'?text='.rawurlencode($message);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('website.settings'));
        static::deleted(fn () => Cache::forget('website.settings'));
    }
}
