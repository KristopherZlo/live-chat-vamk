<x-app-layout page-class="page-questions-panel">
    <div class="panel">
        <div class="panel-header panel-header-between">
            <div>
                <div class="panel-title">Questions queue</div>
                <div class="panel-subtitle">Manage questions for "{{ $room->title }}"</div>
            </div>
            <div class="panel-actions">
                <a class="btn btn-sm btn-ghost" href="{{ route('admin.index') }}">
                    <i data-lucide="arrow-left"></i>
                    <span>Back to admin</span>
                </a>
                <a class="btn btn-sm btn-primary" href="{{ route('rooms.public', $room->slug) }}" target="_blank" rel="noreferrer">
                    <i data-lucide="external-link"></i>
                    <span>Open room</span>
                </a>
            </div>
        </div>
    </div>

    @include('rooms.partials.questions_panel', [
        'room' => $room,
        'queueQuestions' => $queueQuestions,
        'queueStatusCounts' => $queueStatusCounts,
        'isOwner' => $isOwner,
        'queueHasMore' => $queueHasMore ?? false,
        'queueOffset' => $queueOffset ?? $queueQuestions->count(),
    ])
</x-app-layout>
