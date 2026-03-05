<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Post;
use Illuminate\Http\Response;

class BlogFeedController extends Controller
{
    public function rss(): Response
    {
        $posts = Post::published()
            ->with(['category', 'tags'])
            ->orderByDesc('published_at')
            ->limit(20)
            ->get();

        $portalName = tenant()?->name ?? config('app.name');
        $portalUrl = url('/');
        $feedUrl = route('portal.blog.feed');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">' . "\n";
        $xml .= '<channel>' . "\n";
        $xml .= '  <title>' . e($portalName) . ' — Ratgeber</title>' . "\n";
        $xml .= '  <link>' . e($portalUrl) . '/ratgeber</link>' . "\n";
        $xml .= '  <description>Ratgeber und Tipps rund um lokale Dienstleister</description>' . "\n";
        $xml .= '  <language>de</language>' . "\n";
        $xml .= '  <atom:link href="' . e($feedUrl) . '" rel="self" type="application/rss+xml"/>' . "\n";

        if ($posts->isNotEmpty()) {
            $xml .= '  <lastBuildDate>' . $posts->first()->published_at->toRfc2822String() . '</lastBuildDate>' . "\n";
        }

        foreach ($posts as $post) {
            $itemUrl = route('portal.blog.show', $post->slug);

            $xml .= '  <item>' . "\n";
            $xml .= '    <title>' . e($post->title) . '</title>' . "\n";
            $xml .= '    <link>' . e($itemUrl) . '</link>' . "\n";
            $xml .= '    <guid isPermaLink="true">' . e($itemUrl) . '</guid>' . "\n";
            $xml .= '    <pubDate>' . $post->published_at->toRfc2822String() . '</pubDate>' . "\n";

            if ($post->excerpt) {
                $xml .= '    <description>' . e($post->excerpt) . '</description>' . "\n";
            } else {
                $xml .= '    <description>' . e(\Str::limit(strip_tags($post->body), 300)) . '</description>' . "\n";
            }

            if ($post->category) {
                $xml .= '    <category>' . e($post->category->name) . '</category>' . "\n";
            }

            foreach ($post->tags as $tag) {
                $xml .= '    <category>' . e($tag->name) . '</category>' . "\n";
            }

            if ($post->featured_image_url) {
                $xml .= '    <enclosure url="' . e($post->featured_image_url) . '" type="image/jpeg"/>' . "\n";
            }

            $xml .= '  </item>' . "\n";
        }

        $xml .= '</channel>' . "\n";
        $xml .= '</rss>';

        return response($xml, 200)
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
