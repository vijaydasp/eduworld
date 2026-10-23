<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PostCategory;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', ['categories' => PostCategory::withCount('posts')->orderBy('name')->get()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $category = PostCategory::create($this->validated($request));
        Audit::record('category.created', $category);

        return back()->with('success', 'Category added.');
    }

    public function update(Request $request, PostCategory $category): RedirectResponse
    {
        $before = $category->only(['name', 'slug']);
        $category->update($this->validated($request, $category));
        Audit::record('category.updated', $category, $before, $category->only(['name', 'slug']));

        return back()->with('success', 'Category updated.');
    }

    public function destroy(PostCategory $category): RedirectResponse
    {
        if ($category->posts()->exists()) {
            return back()->withErrors(['category' => 'Move this category’s posts to another category before deleting it.']);
        }
        Audit::record('category.deleted', $category, $category->only(['name', 'slug']));
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    private function validated(Request $request, ?PostCategory $category = null): array
    {
        $slug = $request->input('slug') ?: $request->input('name', '');
        $request->merge(['slug' => is_string($slug) ? Str::slug($slug) : '']);

        return $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['required', 'string', 'max:120', Rule::unique('post_categories', 'slug')->ignore($category)]]);
    }
}
