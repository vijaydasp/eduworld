<?php

namespace App\Services;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PostManager
{
    public function __construct(private ContentMedia $media) {}

    public function save(StorePostRequest $request, ?Post $post = null): Post
    {
        $newPaths = [];
        $oldImage = $post?->featured_image;
        try {
            $post = DB::transaction(function () use ($request, $post, &$newPaths) {
                $before = $post ? $post->only(['title', 'is_published', 'published_at', 'is_featured']) : [];
                $attributes = Arr::except($request->validated(), ['featured_upload', 'gallery', 'gallery_alt', 'video_upload', 'youtube_url', 'tags', 'remove_featured']);
                $attributes['published_at'] = ! empty($attributes['published_at'])
                    ? Carbon::parse($attributes['published_at'], config('app.timezone'))->setTimezone(config('app.timezone'))
                    : null;
                $attributes['youtube_id'] = ContentMedia::youtubeId($request->input('youtube_url'));
                if (! $post) {
                    $attributes['user_id'] = $request->user()->id;
                }
                if ($request->boolean('remove_featured')) {
                    $attributes['featured_image'] = null;
                }
                if ($request->hasFile('featured_upload')) {
                    $attributes['featured_image'] = $newPaths[] = $this->media->image($request->file('featured_upload'));
                }
                $post = $post ? tap($post)->update($attributes) : Post::create($attributes);
                $tagIds = [];
                foreach (array_slice(array_filter(array_map('trim', explode(',', $request->input('tags', '') ?? ''))), 0, 20) as $name) {
                    $name = Str::limit($name, 60, '');
                    $slug = Str::slug($name);
                    if ($slug !== '') {
                        $tagIds[] = Tag::firstOrCreate(['slug' => $slug], ['name' => $name])->id;
                    }
                }
                $post->tags()->sync($tagIds);
                foreach ($request->file('gallery', []) as $file) {
                    $path = $newPaths[] = $this->media->image($file);
                    $post->media()->create(['path' => $path, 'type' => 'image', 'mime_type' => 'image/jpeg', 'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''), 'size' => Storage::disk('local')->size($path), 'alt_text' => $request->input('gallery_alt') ?: $post->title]);
                }
                if ($request->hasFile('video_upload')) {
                    $file = $request->file('video_upload');
                    $path = $newPaths[] = $this->media->video($file);
                    $post->media()->create(['path' => $path, 'type' => 'video', 'mime_type' => 'video/mp4', 'original_name' => Str::limit(basename($file->getClientOriginalName()), 255, ''), 'size' => $file->getSize()]);
                }
                $action = ! $before ? 'post.created' : (($before['is_published'] ?? false) !== $post->is_published ? ($post->is_published ? 'post.published' : 'post.unpublished') : 'post.updated');
                Audit::record($action, $post, $before, $post->only(['title', 'is_published', 'published_at', 'is_featured']));

                return $post;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newPaths);
            throw $exception;
        }
        if ($oldImage && $oldImage !== $post->featured_image) {
            Storage::disk('local')->delete($oldImage);
        }

        return $post;
    }

    public function delete(Post $post): void
    {
        $paths = $post->media()->pluck('path')->all();
        if ($post->featured_image) {
            $paths[] = $post->featured_image;
        }
        DB::transaction(function () use ($post) {
            Audit::record('post.deleted', $post, $post->only(['title', 'is_published']));
            $post->delete();
        });
        Storage::disk('local')->delete($paths);
    }
}
