@php
    $queueTotal = $queueStatusCounts['all'] ?? $queueQuestions->count();
    $queueNewCount = $queueStatusCounts['new'] ?? 0;
    $queueHasMore = $queueHasMore ?? false;
    $queueOffset = $queueOffset ?? $queueQuestions->count();
@endphp

<section
  class="panel queue-panel mobile-panel mobile-active {{ $queueNewCount > 0 ? 'has-new' : '' }}"
  data-mobile-panel="queue"
  id="queuePanel"
  data-room-slug="{{ $room->slug }}"
  data-viewer-id="{{ auth()->id() ?? 'guest' }}"
  data-queue-remote="1"
  data-onboarding-target="queue-panel"
>
  <button type="button" class="panel-collapse-handle" data-panel-expand="queue">Question queue</button>
  <div class="panel-header queue-panel-header">
    <div class="panel-title">
      <i data-lucide="list-ordered"></i>
      <span>Question queue</span>
    </div>
    <div class="queue-header-extra">
      @auth
        <button class="btn btn-sm btn-ghost queue-pip-btn" type="button" data-queue-pip aria-label="Picture in picture">
          <i data-lucide="picture-in-picture"></i>
        </button>
      @endauth
      <span class="queue-count-badge">{{ $queueTotal }}</span>
    </div>
  </div>

  <div class="queue-filter-row">
    <div class="queue-filter-tabs" role="tablist" aria-label="Queue filters">
      <button class="queue-filter-tab is-active" type="button" data-queue-filter-tab="new">New</button>
      <button class="queue-filter-tab" type="button" data-queue-filter-tab="answered">Done</button>
      <button class="queue-filter-tab" type="button" data-queue-filter-tab="later">Later</button>
      <button class="queue-filter-tab" type="button" data-queue-filter-tab="ignored">Hidden</button>
    </div>
    <span class="panel-subtitle">Questions sent to the host</span>
    <label class="visually-hidden" for="queueFilterSelect">Filter questions</label>
    <select class="visually-hidden" id="queueFilterSelect" data-queue-filter>
      <option value="new" selected>New ({{ $queueStatusCounts['new'] ?? 0 }})</option>
      <option value="answered">Done ({{ $queueStatusCounts['answered'] ?? 0 }})</option>
      <option value="later">Later ({{ $queueStatusCounts['later'] ?? 0 }})</option>
      <option value="ignored">Hidden ({{ $queueStatusCounts['ignored'] ?? 0 }})</option>
    </select>
  </div>

  <div class="panel-body">
    @if($queueQuestions->isEmpty())
      <div class="empty-state">
        <div class="empty-state-icon">
          <i data-lucide="list-ordered"></i>
        </div>
        <div class="empty-state-text">No pending questions.</div>
      </div>
    @else
      <ul
        class="queue-list"
        data-queue-has-more="{{ $queueHasMore ? '1' : '0' }}"
        data-queue-offset="{{ $queueOffset }}"
      >
        @include('rooms.partials.queue_items', ['queueQuestions' => $queueQuestions, 'room' => $room, 'isOwner' => $isOwner])
      </ul>
      <div class="empty-state queue-filter-empty" data-queue-filter-empty hidden>
        <div class="empty-state-icon">
          <i data-lucide="list-ordered"></i>
        </div>
        <div class="empty-state-text">No questions in this filter.</div>
      </div>
      <div class="queue-pagination">
        <div class="queue-loading" data-queue-loader hidden role="status" aria-label="Loading">
          <div class="loader-5" aria-hidden="true"><span></span></div>
        </div>
      </div>
    @endif
  </div>

  <div class="panel-footer">
    <span>{{ $queueTotal }} total</span>
    <span class="panel-subtitle">Update status and filter to focus on what matters</span>
  </div>
</section>
