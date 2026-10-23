<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Post extends Model
{
    use HasFactory;

    /** Compatibility with earlier post managers and compiled admin views. */
    public const PUBLICATION_TIMEZONE = 'UTC';

    protected $fillable = ['category_id', 'user_id', 'title', 'slug', 'excerpt', 'body', 'author_name', 'featured_image', 'featured_image_alt', 'youtube_id', 'cta_label', 'cta_url', 'seo_title', 'meta_description', 'is_published', 'is_featured', 'published_at'];

    protected function casts(): array
    {
        return ['is_published' => 'boolean', 'is_featured' => 'boolean', 'published_at' => 'datetime'];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true)->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function isPublic(): bool
    {
        return $this->is_published && $this->published_at && $this->published_at->lte(now());
    }

    public function getPublicationStatusAttribute(): string
    {
        if (! $this->is_published || ! $this->published_at) {
            return 'Draft';
        }

        return $this->isPublic() ? 'Published' : 'Scheduled';
    }

    public function publicationMessage(): string
    {
        return match ($this->publication_status) {
            'Published' => 'Published. This post is now visible on the public website.',
            'Scheduled' => 'Scheduled for '.$this->published_at->copy()->setTimezone(config('app.timezone'))->format('d M Y, H:i').' '.config('app.timezone').'. It will appear publicly at that time.',
            default => 'Saved as a draft. Select "Publish this post" and set a publication date to make it public.',
        };
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(PostCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function media(): HasMany
    {
        return $this->hasMany(PostMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->featured_image ? route('news.image', $this) : null;
    }

    public function getYoutubeEmbedUrlAttribute(): ?string
    {
        return $this->youtube_id && preg_match('/^[A-Za-z0-9_-]{11}$/D', $this->youtube_id)
            ? 'https://www.youtube-nocookie.com/embed/'.$this->youtube_id : null;
    }
}
