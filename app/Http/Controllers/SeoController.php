<?php

namespace App\Http\Controllers;

use App\Models\UpdatePost;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $now = now()->toAtomString();

        $entries = [
            [
                'loc' => url('/'),
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.9',
            ],
            [
                'loc' => route('rooms.join'),
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.8',
            ],
            [
                'loc' => route('updates.index'),
                'lastmod' => $now,
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ],
            [
                'loc' => route('presentation'),
                'lastmod' => $now,
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ],
            [
                'loc' => route('privacy'),
                'lastmod' => $now,
                'changefreq' => 'yearly',
                'priority' => '0.4',
            ],
        ];

        $posts = UpdatePost::query()
            ->type(UpdatePost::TYPE_BLOG)
            ->published()
            ->latestPublished()
            ->get();

        foreach ($posts as $post) {
            $lastModified = $post->published_at ?? $post->updated_at ?? $post->created_at;

            $entries[] = [
                'loc' => route('updates.show', ['post' => $post->slug]),
                'lastmod' => $lastModified?->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.5',
            ];
        }

        return response()
            ->view('seo.sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml');
    }
}
