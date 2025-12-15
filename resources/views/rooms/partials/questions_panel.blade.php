@php
    $queueTotal = $queueStatusCounts['all'] ?? $queueQuestions->count();
    $queueHasMore = $queueHasMore ?? false;
    $queueOffset = $queueOffset ?? $queueQuestions->count();
@endphp

<section
  class="panel queue-panel mobile-panel mobile-active"
  data-mobile-panel="queue"
  id="queuePanel"
  data-room-id="{{ $room->id }}"
  data-viewer-id="{{ auth()->id() ?? 'guest' }}"
  data-queue-remote="1"
  data-onboarding-target="queue-panel"
>
  <div class="panel-header">
    <div>
      <div class="panel-title">
        <i data-lucide="list-ordered"></i>
        <span>Question queue</span>
      </div>
      <div class="panel-subtitle">Filter and manage questions from participants</div>
    </div>
    <div class="queue-header-extra">
      <div class="queue-filter">
        <label class="queue-filter-label" for="queueFilter">Filter</label>
        <select id="queueFilter" class="queue-filter-select" data-queue-filter>
          <option value="new" data-count="{{ $queueStatusCounts['new'] ?? 0 }}">New ({{ $queueStatusCounts['new'] ?? 0 }})</option>
          <option value="all" data-count="{{ $queueStatusCounts['all'] ?? 0 }}" selected>All ({{ $queueStatusCounts['all'] ?? 0 }})</option>
          <option value="answered" data-count="{{ $queueStatusCounts['answered'] ?? 0 }}">Answered ({{ $queueStatusCounts['answered'] ?? 0 }})</option>
          <option value="ignored" data-count="{{ $queueStatusCounts['ignored'] ?? 0 }}">Ignored ({{ $queueStatusCounts['ignored'] ?? 0 }})</option>
          <option value="later" data-count="{{ $queueStatusCounts['later'] ?? 0 }}">Later ({{ $queueStatusCounts['later'] ?? 0 }})</option>
        </select>
      </div>
      @auth
        <button class="btn btn-sm btn-ghost queue-pip-btn" type="button" data-queue-pip aria-label="Picture in picture">
          <i data-lucide="picture-in-picture"></i>
        </button>
      @endauth
      <span class="queue-count-badge">{{ $queueTotal }} questions</span>
    </div>
  </div>

  <div class="panel-body">
    @if($queueQuestions->isEmpty())
      <p class="empty-state">No pending questions.</p>
    @else
      <ul
        class="queue-list"
        data-queue-has-more="{{ $queueHasMore ? '1' : '0' }}"
        data-queue-offset="{{ $queueOffset }}"
      >
        @include('rooms.partials.queue_items', ['queueQuestions' => $queueQuestions, 'room' => $room, 'isOwner' => $isOwner])
      </ul>
      <p class="empty-state queue-filter-empty" data-queue-filter-empty hidden>No questions in this filter.</p>
      <div class="queue-pagination">
        <div class="queue-loading" data-queue-loader hidden>Loading more questions...</div>
      </div>
    @endif
  </div>

  <div class="panel-footer">
    <span>{{ $queueTotal }} total</span>
    <span class="panel-subtitle">Update status and filter to focus on what matters</span>
  </div>
</section>
