@php
    use Illuminate\Support\Str;
    $publishedAt = $post->published_at?->format('M d, Y') ?? $post->created_at?->format('M d, Y');
    $coverUrl = $post->cover_url;
    $metaDescription = $post->excerpt ?: Str::limit(strip_tags($post->body ?? ''), 180);
    $bodyHtml = $post->body ? Str::markdown($post->body, ['html_input' => 'strip']) : '';
@endphp

<x-app-layout
    page-class="page-updates"
    :meta-title="$post->title"
    :meta-description="$metaDescription"
    :meta-image="$coverUrl"
>
    <div class="updates-shell">
        <article class="update-article">
            <div class="update-article__head">
                <a class="update-back" href="{{ route('updates.index') }}">
                    <i data-lucide="arrow-left"></i>
                    <span>Back to updates</span>
                </a>
                <div class="update-article__meta">
                    <span class="pill-soft">{{ $publishedAt }}</span>
                </div>
                <h1 class="update-article__title">{{ $post->title }}</h1>
            </div>
            @if($coverUrl)
                <div class="update-article__cover">
                    <img src="{{ $coverUrl }}" alt="{{ $post->title }}">
                </div>
            @endif
            <div class="update-article__body markdown-body">
                {!! $bodyHtml !!}
            </div>
        </article>
    </div>
</x-app-layout>
