<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', ['pages' => Page::orderBy('title')->get()]);
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $values = $request->validate(['title' => ['required', 'string', 'max:255'], 'body' => ['required', 'string', 'max:100000'], 'seo_title' => ['nullable', 'string', 'max:70'], 'meta_description' => ['nullable', 'string', 'max:170'], 'review_required' => ['sometimes', 'boolean']]);
        $values['review_required'] = $request->boolean('review_required');
        $before = $page->only(['title', 'review_required']);
        $page->update($values);
        Audit::record('page.updated', $page, $before, $page->only(['title', 'review_required']));

        return back()->with('success', 'Page updated.');
    }
}
