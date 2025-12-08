@php use Illuminate\Support\Str; @endphp

<x-app-layout page-class="page-updates">
    <div class="updates-shell">
        <section class="updates-hero">
            <div class="updates-hero__body">
                @if($latestRelease)
                    <div class="updates-hero__meta">
                        <span class="pill-soft">Product updates</span>
                        @if(!empty($latestRelease['version']))
                            <span class="pill-soft">v{{ $latestRelease['version'] }}</span>
                        @endif
                        <span class="pill-soft">{{ $latestRelease['date_human'] ?? $latestRelease['date'] ?? 'Fresh' }}</span>
                    </div>
                    <h1 class="updates-hero__title">Live chat release notes</h1>
                    <p class="updates-hero__text">
                        {{ $latestRelease['excerpt'] ?? 'All the latest changes to rooms, chat, and moderation in one place.' }}
                    </p>
                    <div class="updates-hero__meta">
                        <span>Newest update: {{ $latestRelease['date_human'] ?? $latestRelease['date'] ?? '—' }}</span>
                        @if(!empty($latestRelease['version']))
                            <span class="pill-soft">Version {{ $latestRelease['version'] }}</span>
                        @endif
                    </div>
                @else
                    <h1 class="updates-hero__title">Live chat release notes</h1>
                    <p class="updates-hero__text">All the latest changes to rooms, chat, and moderation in one place.</p>
                @endif
            </div>
            @if(!empty($latestRelease['cover_url']))
                <div class="updates-hero__media">
                    <img src="{{ $latestRelease['cover_url'] }}" alt="Latest release preview">
                </div>
            @endif
        </section>

        <section class="updates-list" id="updates-list">
            <div class="updates-grid">
                @foreach($posts as $post)
                    @php
                        $plainBody = strip_tags($post->body ?? '');
                        $isTruncated = Str::length($plainBody) > 140;
                    @endphp
                    <article class="update-card update-card--modal" data-update-card>
                        @if($post->cover_url)
                            <div class="update-card__cover">
                                <img src="{{ $post->cover_url }}" alt="{{ $post->title }}">
                            </div>
                        @endif
                        <div class="update-card__meta">
                            <span class="pill-soft">{{ $post->published_at?->format('M d, Y') ?? $post->created_at?->format('M d, Y') }}</span>
                            @if($post->version)
                                <span class="pill-soft">v{{ $post->version }}</span>
                            @endif
                        </div>
                        <h3 class="update-card__title">{{ $post->title }}</h3>
                        <p class="update-card__excerpt">
                            {{ $post->excerpt ?: Str::limit(strip_tags($post->body ?? ''), 140) }}
                        </p>
                        @if($isTruncated)
                            <div class="update-card__more">
                                <span>Tap to read the full update</span>
                                <i data-lucide="arrow-up-right"></i>
                            </div>
                        @endif
                        <div class="update-card__footer">
                            <span class="update-card__cta">Read update</span>
                            <i data-lucide="arrow-right"></i>
                        </div>
                        <div class="update-card__body" data-update-body hidden>
                            {!! Str::markdown($post->body ?? '', ['html_input' => 'strip']) !!}
                        </div>
                        <div
                            data-update-meta
                            hidden
                            data-title="{{ $post->title }}"
                            data-date="{{ $post->published_at?->format('M d, Y') ?? $post->created_at?->format('M d, Y') }}"
                            @if($post->version) data-version="{{ $post->version }}" @endif
                            @if($post->cover_url) data-cover="{{ $post->cover_url }}" @endif
                        ></div>
                    </article>
                @endforeach
            </div>
            <div class="pagination">
                {{ $posts->links() }}
            </div>
        </section>

        @if(!empty($releaseHistory) && count($releaseHistory))
            <section class="updates-list">
                <div class="updates-hero__meta" style="gap: 0.35rem;">
                    <span class="pill-soft">Release history</span>
                    <span class="updates-hero__text" style="margin:0;">All published “What’s new” entries</span>
                </div>
                <div class="updates-grid">
                    @foreach($releaseHistory as $release)
                        @php
                            $releaseBodyLength = isset($release['body_html'])
                                ? Str::length(strip_tags($release['body_html']))
                                : 0;
                            $releaseTruncated = $releaseBodyLength > 160;
                        @endphp
                        <article
                            class="update-card update-card--modal"
                            data-update-card
                            data-title="{{ $release['title'] ?? '' }}"
                            data-date="{{ $release['date_human'] ?? $release['date'] ?? '' }}"
                            @if(!empty($release['version'])) data-version="{{ $release['version'] }}" @endif
                            @if(!empty($release['cover_url'])) data-cover="{{ $release['cover_url'] }}" @endif
                        >
                            @if(!empty($release['cover_url']))
                                <div class="update-card__cover">
                                    <img src="{{ $release['cover_url'] }}" alt="{{ $release['title'] ?? 'Release '.$release['version'] }}">
                                </div>
                            @endif
                            <div class="update-card__meta">
                                @if(!empty($release['date_human']) || !empty($release['date']))
                                    <span class="pill-soft">{{ $release['date_human'] ?? $release['date'] }}</span>
                                @endif
                                @if(!empty($release['version']))
                                    <span class="pill-soft">v{{ $release['version'] }}</span>
                                @endif
                            </div>
                            <h3 class="update-card__title">{{ $release['title'] ?? 'Release' }}</h3>
                            @if(!empty($release['excerpt']))
                                <p class="update-card__excerpt">{{ $release['excerpt'] }}</p>
                            @endif
                            @if($releaseTruncated)
                                <div class="update-card__more">
                                    <span>Tap to read the full update</span>
                                    <i data-lucide="arrow-up-right"></i>
                                </div>
                            @endif
                            @if(!empty($release['body_html']))
                                <div class="update-card__body" data-update-body hidden>
                                    {!! $release['body_html'] !!}
                                </div>
                            @endif
                        </article>
                    @endforeach
                </div>
            </section>
        @endif
    </div>

    <div class="modal-overlay update-modal" data-update-modal hidden>
        <div class="modal-dialog" role="dialog" aria-modal="true" aria-labelledby="updateModalTitle">
            <div class="modal-header">
                <div class="modal-title-group">
                    <span class="modal-eyebrow">Update</span>
                    <h2 class="modal-title" id="updateModalTitle"></h2>
                    <p class="modal-text" data-update-modal-meta></p>
                </div>
                <button class="modal-close" type="button" data-update-modal-close aria-label="Close">
                    <i data-lucide="x"></i>
                </button>
            </div>
            <div class="modal-body whats-new-body" data-update-modal-body>
                <div class="whats-new-media" data-update-modal-cover hidden>
                    <img src="" alt="Update cover">
                </div>
                <div class="whats-new-content markdown-body" data-update-modal-content></div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" type="button" data-update-modal-close>Close</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const modal = document.querySelector('[data-update-modal]');
            const modalTitle = modal?.querySelector('#updateModalTitle');
            const modalMeta = modal?.querySelector('[data-update-modal-meta]');
            const modalCoverWrap = modal?.querySelector('[data-update-modal-cover]');
            const modalCoverImg = modalCoverWrap?.querySelector('img');
            const modalContent = modal?.querySelector('[data-update-modal-content]');
            const closeButtons = modal?.querySelectorAll('[data-update-modal-close]') || [];

            const closeModal = () => {
                if (!modal) return;
                modal.classList.remove('show');
                setTimeout(() => modal.setAttribute('hidden', 'hidden'), 140);
                document.body.classList.remove('modal-open');
            };

            const openModal = (payload) => {
                if (!modal || !payload) return;
                modal.removeAttribute('hidden');
                requestAnimationFrame(() => modal.classList.add('show'));
                document.body.classList.add('modal-open');

                if (modalTitle) modalTitle.textContent = payload.title || 'Update';
                if (modalMeta) {
                    const bits = [];
                    if (payload.date) bits.push(payload.date);
                    if (payload.version) bits.push(`v${payload.version}`);
                    modalMeta.textContent = bits.join(' • ');
                }

                if (modalCoverWrap && modalCoverImg) {
                    if (payload.cover) {
                        modalCoverImg.src = payload.cover;
                        modalCoverWrap.removeAttribute('hidden');
                    } else {
                        modalCoverWrap.setAttribute('hidden', 'hidden');
                        modalCoverImg.removeAttribute('src');
                    }
                }

                if (modalContent) {
                    modalContent.innerHTML = payload.body || payload.excerpt || 'No details available.';
                }
            };

            closeButtons.forEach((btn) => btn.addEventListener('click', closeModal));
            modal?.addEventListener('click', (event) => {
                if (event.target === modal) {
                    closeModal();
                }
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape' && modal && !modal.hasAttribute('hidden')) {
                    closeModal();
                }
            });

            document.querySelectorAll('[data-update-card]').forEach((card) => {
                card.addEventListener('click', (event) => {
                    event.preventDefault();
                    const meta = card.querySelector('[data-update-meta]');
                    const bodyEl = card.querySelector('[data-update-body]');
                    const payload = {
                        title: meta?.dataset?.title || card.querySelector('.update-card__title')?.textContent || 'Update',
                        date: meta?.dataset?.date || card.dataset.date || '',
                        version: meta?.dataset?.version || card.dataset.version || '',
                        cover: meta?.dataset?.cover || card.dataset.cover || '',
                        body: bodyEl ? bodyEl.innerHTML : '',
                        excerpt: card.querySelector('.update-card__excerpt')?.textContent || '',
                    };
                    openModal(payload);
                });
            });
        });
    </script>
</x-app-layout>
