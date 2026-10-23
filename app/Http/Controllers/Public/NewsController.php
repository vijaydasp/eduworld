<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class NewsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['category' => ['nullable', 'string', 'max:120'], 'tag' => ['nullable', 'string', 'max:80']]);
        $posts = Post::published()->with(['category', 'tags'])
            ->when($filters['category'] ?? null, fn ($query, $slug) => $query->whereHas('category', fn ($category) => $category->where('slug', $slug)))
            ->when($filters['tag'] ?? null, fn ($query, $slug) => $query->whereHas('tags', fn ($tag) => $tag->where('slug', $slug)))
            ->latest('published_at')->paginate(9)->withQueryString();

        return view('news.index', ['posts' => $posts, 'categories' => PostCategory::orderBy('id')->get()]);
    }

    public function show(string $slug): View
    {
        $post = Post::published()->with(['category', 'tags', 'media'])->where('slug', $slug)->firstOrFail();

        return view('news.show', compact('post'));
    }

    public function image(Post $post): BinaryFileResponse
    {
        abort_unless($post->isPublic() || Gate::allows('manage-content'), 404);
        abort_unless($post->featured_image && Storage::disk('local')->exists($post->featured_image), 404);

        return response()->file(Storage::disk('local')->path($post->featured_image), ['Content-Type' => 'image/jpeg', 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }

    public function media(PostMedia $media): BinaryFileResponse
    {
        abort_unless($media->post->isPublic() || Gate::allows('manage-content'), 404);
        abort_unless(Storage::disk('local')->exists($media->path), 404);

        return response()->file(Storage::disk('local')->path($media->path), ['Content-Type' => $media->mime_type, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
