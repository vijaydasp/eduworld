<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Http\Response;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function home(): View
    {
        return view('pages.home', ['posts' => Post::published()->with('category')->latest('published_at')->limit(3)->get(), 'services' => config('service-content')]);
    }

    public function services(): View
    {
        return view('pages.services', ['services' => config('service-content')]);
    }

    public function sitemap(): Response
    {
        $urls = collect(['home', 'services', 'apply', 'news', 'contact', 'pay', 'privacy-policy', 'terms', 'payment-terms', 'refund-policy', 'disclaimer'])->map(fn ($route) => ['loc' => route($route), 'lastmod' => null]);
        foreach (Post::published()->select(['slug', 'updated_at'])->cursor() as $post) {
            $urls->push(['loc' => route('news.show', $post->slug), 'lastmod' => $post->updated_at->toAtomString()]);
        }

        return response()->view('seo.sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /apply/received\nDisallow: /pay/receipt\nSitemap: ".route('sitemap')."\n")->header('Content-Type', 'text/plain');
    }
}
