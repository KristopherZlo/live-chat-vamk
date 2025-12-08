<?php

namespace App\Http\Controllers;

use App\Models\UpdatePost;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UpdatePostController extends Controller
{
    public function index()
    {
        $posts = UpdatePost::query()
            ->type(UpdatePost::TYPE_BLOG)
            ->published()
            ->latestPublished()
            ->paginate(9);

        $releaseEntries = UpdatePost::query()
            ->type(UpdatePost::TYPE_WHATS_NEW)
            ->published()
            ->latestPublished()
            ->get();
        $releaseHistory = collect();
        $minVersion = '1.2.0';

        if ($releaseEntries->isEmpty()) {
            $configReleases = collect(config('whatsnew.releases', []))
                ->map(function ($release, $version) {
                    $sections = is_array($release['sections'] ?? null) ? $release['sections'] : [];
                    $firstSection = $sections[0] ?? [];
                    $excerpt = $firstSection['items'][0] ?? ($firstSection['text'] ?? ($release['excerpt'] ?? null));
                    $date = $release['date'] ?? null;
                    $bodyHtml = '';

                    foreach ($sections as $section) {
                        $title = $section['title'] ?? '';
                        $items = $section['items'] ?? null;
                        $text = $section['text'] ?? null;

                        if ($title) {
                            $bodyHtml .= '<h4>'.e($title).'</h4>';
                        }

                        if (is_array($items) && count($items)) {
                            $bodyHtml .= '<ul>';
                            foreach ($items as $item) {
                                $bodyHtml .= '<li>'.e($item).'</li>';
                            }
                            $bodyHtml .= '</ul>';
                        } elseif ($text) {
                            $bodyHtml .= '<p>'.e($text).'</p>';
                        }
                    }

                    if (! $bodyHtml && ! empty($release['body'])) {
                        $bodyHtml = Str::markdown($release['body'], ['html_input' => 'strip']);
                    }

                    return [
                        'version' => $version,
                        'title' => $release['title'] ?? ('Version '.$version),
                        'excerpt' => $excerpt,
                        'cover_url' => !empty($release['image']) ? asset($release['image']) : null,
                        'date' => $date,
                        'date_human' => $date ? Carbon::parse($date)->format('M d, Y') : null,
                        'is_config' => true,
                        'body_html' => $bodyHtml,
                    ];
                })
                ->values();
            $releaseHistory = $configReleases
                ->sortByDesc(fn ($item) => $item['date'] ?? '')
                ->values();
        } else {
            $releaseHistory = $releaseEntries->map(function (UpdatePost $post) {
                $date = $post->published_at?->format('Y-m-d') ?? $post->created_at?->format('Y-m-d');

                return [
                    'version' => $post->version,
                    'title' => $post->title,
                    'excerpt' => $post->excerpt ?: Str::limit(strip_tags($post->body ?? ''), 200),
                    'cover_url' => $post->cover_url,
                    'date' => $date,
                    'date_human' => $date ? Carbon::parse($date)->format('M d, Y') : null,
                    'is_config' => false,
                    'body_html' => $post->body
                        ? Str::markdown($post->body, ['html_input' => 'strip'])
                        : null,
                ];
            });
        }

        $releaseHistory = $releaseHistory
            ->filter(function ($item) use ($minVersion) {
                if (empty($item['version'])) {
                    return true;
                }

                return version_compare($item['version'], $minVersion, '>=');
            })
            ->unique('version')
            ->values();

        $latestRelease = $releaseHistory->first();

        return view('updates.index', [
            'posts' => $posts,
            'latestRelease' => $latestRelease,
            'releaseHistory' => $releaseHistory,
        ]);
    }

    public function show(UpdatePost $post)
    {
        if ($post->type !== UpdatePost::TYPE_BLOG || !$post->is_live) {
            abort(404);
        }

        return view('updates.show', [
            'post' => $post,
        ]);
    }
}
