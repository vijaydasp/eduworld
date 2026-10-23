<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request): View
    {
        $page = Page::where('slug', trim($request->path(), '/'))->firstOrFail();

        return view('pages.legal', compact('page'));
    }
}
