<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Models\PostCategory;
use App\Models\PostMedia;
use App\Services\Audit;
use App\Services\PostManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'category' => ['nullable', 'integer'], 'status' => ['nullable', 'in:draft,published,scheduled'], 'featured' => ['nullable', 'in:0,1']]);
        $posts = Post::with('category')
            ->when($filters['search'] ?? null, fn ($query, $value) => $query->where('title', 'like', '%'.$value.'%'))
            ->when($filters['category'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->when(($filters['status'] ?? null) === 'draft', fn ($query) => $query->where('is_published', false))
            ->when(($filters['status'] ?? null) === 'published', fn ($query) => $query->published())
            ->when(($filters['status'] ?? null) === 'scheduled', fn ($query) => $query->where('is_published', true)->where('published_at', '>', now()))
            ->when(isset($filters['featured']), fn ($query) => $query->where('is_featured', (bool) $filters['featured']))
            ->latest()->paginate(20)->withQueryString();

        return view('admin.posts.index', ['posts' => $posts, 'categories' => PostCategory::orderBy('name')->get()]);
    }

    public function create(): View
    {
        return view('admin.posts.form', ['post' => new Post(['author_name' => 'Edu World']), 'categories' => PostCategory::orderBy('name')->get()]);
    }

    public function store(StorePostRequest $request, PostManager $manager): RedirectResponse
    {
        $post = $manager->save($request);

        return redirect()->route('admin.posts.edit', $post)->with('success', $post->publicationMessage());
    }

    public function edit(Post $post): View
    {
        $post->load(['tags', 'media']);

        return view('admin.posts.form', ['post' => $post, 'categories' => PostCategory::orderBy('name')->get()]);
    }

    public function update(StorePostRequest $request, Post $post, PostManager $manager): RedirectResponse
    {
        $post = $manager->save($request, $post);

        return back()->with('success', $post->publicationMessage());
    }

    public function destroy(Post $post, PostManager $manager): RedirectResponse
    {
        $manager->delete($post);

        return redirect()->route('admin.posts.index')->with('success', 'Post deleted.');
    }

    public function destroyMedia(Post $post, PostMedia $media): RedirectResponse
    {
        abort_unless($media->post_id === $post->id, 404);
        $path = $media->path;
        Audit::record('post.media_deleted', $post, ['media_id' => $media->id]);
        $media->delete();
        Storage::disk('local')->delete($path);

        return back()->with('success', 'Media removed.');
    }
}
