<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = ['title', 'slug', 'body', 'seo_title', 'meta_description', 'review_required'];

    protected function casts(): array
    {
        return ['review_required' => 'boolean'];
    }

    public function getRenderedBodyAttribute(): string
    {
        return strtr($this->body, [
            '[[company_name]]' => (string) Setting::getValue('company_name', 'Edu World'),
            '[[email]]' => (string) Setting::getValue('email', 'info@eduworld.bh'),
            '[[phone]]' => (string) Setting::getValue('phone', '+973 33640001'),
            '[[address]]' => (string) Setting::getValue('address', 'Segaya, Manama, Kingdom of Bahrain'),
        ]);
    }
}
