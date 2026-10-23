<?php

namespace App\Http\Requests;

use App\Services\ContentMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-content') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => is_string($this->input('slug') ?: $this->input('title', '')) ? Str::slug($this->input('slug') ?: $this->input('title', '')) : '',
            'is_published' => $this->boolean('is_published'),
            'is_featured' => $this->boolean('is_featured'),
        ]);
    }

    public function rules(): array
    {
        $image = ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'extensions:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=5000,max_height=5000'];

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($this->route('post'))],
            'category_id' => ['required', 'integer', 'exists:post_categories,id'],
            'excerpt' => ['nullable', 'string', 'max:600'],
            'body' => ['required', 'string', 'max:100000'],
            'author_name' => ['required', 'string', 'max:120'],
            'seo_title' => ['nullable', 'string', 'max:70'],
            'meta_description' => ['nullable', 'string', 'max:170'],
            'is_published' => ['required', 'boolean'],
            'is_featured' => ['required', 'boolean'],
            'published_at' => ['nullable', Rule::requiredIf($this->boolean('is_published')), 'date'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'featured_upload' => ['nullable', ...$image],
            'remove_featured' => ['sometimes', 'boolean'],
            'gallery' => ['nullable', 'array', 'max:12'],
            'gallery.*' => $image,
            'gallery_alt' => ['nullable', 'string', 'max:255'],
            'video_upload' => ['nullable', 'file', 'mimes:mp4', 'mimetypes:video/mp4', 'extensions:mp4', 'max:30720'],
            'youtube_url' => ['bail', 'nullable', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ContentMedia::youtubeId($value)) {
                    $fail('Enter a valid HTTPS youtube.com or youtu.be video link.');
                }
            }],
            'cta_label' => ['nullable', 'required_with:cta_url', 'string', 'max:80'],
            'cta_url' => ['bail', 'nullable', 'required_with:cta_label', 'string', 'max:2048', function (string $attribute, mixed $value, \Closure $fail): void {
                $parts = parse_url($value);
                $local = str_starts_with($value, '/') && ! str_starts_with($value, '//') && ! str_contains($value, '\\') && ! preg_match('/[\x00-\x20]/', $value);
                $https = filter_var($value, FILTER_VALIDATE_URL) && ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass']);
                if (! $local && ! $https) {
                    $fail('Use a local path beginning with / or a valid HTTPS link.');
                }
            }],
            'tags' => ['nullable', 'string', 'max:500'],
        ];
    }
}
