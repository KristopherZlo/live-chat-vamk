@php
    $queueCount = $queueQuestions->count();
@endphp

<section
  class="panel queue-panel mobile-panel mobile-active"
  data-mobile-panel="queue"
  id="queuePanel"
  data-room-id="{{ $room->id }}"
  data-viewer-id="{{ auth()->id() ?? 'guest' }}"
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
          <option value="new" selected>New ({{ $queueStatusCounts['new'] ?? 0 }})</option>
          <option value="all">All ({{ $queueStatusCounts['all'] ?? 0 }})</option>
          <option value="answered">Answered ({{ $queueStatusCounts['answered'] ?? 0 }})</option>
          <option value="ignored">Ignored ({{ $queueStatusCounts['ignored'] ?? 0 }})</option>
          <option value="later">Later ({{ $queueStatusCounts['later'] ?? 0 }})</option>
        </select>
      </div>
      @auth
        <button class="btn btn-sm btn-ghost queue-pip-btn" type="button" data-queue-pip aria-label="Picture in picture">
          <i data-lucide="picture-in-picture"></i>
        </button>
      @endauth
      <span class="queue-count-badge">{{ $queueCount }} questions</span>
    </div>
  </div>

  <div class="panel-body">
    @if($queueQuestions->isEmpty())
      <p class="empty-state">No pending questions.</p>
    @else
      <ul class="queue-list">
        @foreach($queueQuestions as $question)
          <li
            class="queue-item {{ $question->status === 'new' ? 'queue-item-new' : '' }}"
            data-question-id="{{ $question->id }}"
            data-status="{{ $question->status }}"
          >
            <div class="question-header">
              <div class="question-meta">
                <span class="message-author">{{ $question->participant?->display_name ?? 'Anonymous' }}</span>
                <span class="message-meta">{{ $question->created_at->format('H:i') }}</span>
              </div>
              @if($question->status !== 'new')
                <span class="status-pill status-{{ $question->status }}">{{ ucfirst($question->status) }}</span>
              @endif
            </div>
            <div class="question-text">{{ $question->content }}</div>
            <div class="question-actions">
              @if($isOwner)
                <div class="queue-controls">
                  <form method="POST" action="{{ route('questions.updateStatus', $question) }}" data-remote="questions-panel">
                    @csrf
                    <input type="hidden" name="status" value="answered">
                    <button type="submit" class="queue-action queue-action-answered">Answered</button>
                  </form>
                  <form method="POST" action="{{ route('questions.updateStatus', $question) }}" data-remote="questions-panel">
                    @csrf
                    <input type="hidden" name="status" value="ignored">
                    <button type="submit" class="queue-action queue-action-ignored">Ignore</button>
                  </form>
                  <form method="POST" action="{{ route('questions.updateStatus', $question) }}" data-remote="questions-panel">
                    @csrf
                    <input type="hidden" name="status" value="later">
                    <button type="submit" class="queue-action queue-action-later">Later</button>
                  </form>
                </div>
                <div class="question-actions-secondary">
                  @if($question->participant)
                    <form
                      method="POST"
                      action="{{ route('rooms.bans.store', $room) }}"
                      data-ban-confirm="1"
                    >
                      @csrf
                      <input type="hidden" name="participant_id" value="{{ $question->participant->id }}">
                      <button class="btn btn-sm queue-ban-btn" type="submit">
                        <i data-lucide="gavel"></i>
                        <span>Ban participant</span>
                      </button>
                    </form>
                  @endif
                  <form method="POST" action="{{ route('questions.ownerDelete', $question) }}" onsubmit="return confirm('Delete this question permanently?');" data-remote="questions-panel">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm queue-delete-btn" type="submit">
                      <i data-lucide="trash-2"></i>
                      <span>Delete</span>
                    </button>
                  </form>
                </div>
              @else
                <span class="panel-subtitle">Only the host can manage the queue.</span>
              @endif
            </div>
          </li>
        @endforeach
      </ul>
      <p class="empty-state queue-filter-empty" data-queue-filter-empty hidden>No questions in this filter.</p>
    @endif
  </div>

  <div class="panel-footer">
    <span>{{ $queueCount }} total</span>
    <span class="panel-subtitle">Update status and filter to focus on what matters</span>
  </div>
</section>
