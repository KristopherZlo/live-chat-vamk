<li
  class="queue-item {{ $question->status === 'new' ? 'queue-item-new' : '' }}"
  data-question-id="{{ $question->id }}"
  data-status="{{ $question->status }}"
>
  @php
    $statusLabel = match ($question->status) {
        'answered' => 'Done',
        'ignored' => 'Hidden',
        'later' => 'Later',
        default => 'New',
    };
  @endphp
  <div class="question-header">
    <div class="question-meta">
      <span class="message-author">{{ $question->participant?->display_name ?? 'Anonymous' }}</span>
      <span class="message-meta">{{ $question->created_at->format('H:i') }}</span>
    </div>
    <div class="question-header-actions">
      <span class="status-pill status-{{ $question->status }}">{{ $statusLabel }}</span>
      @if($isOwner)
        <div class="question-menu">
          <button type="button" class="question-menu-trigger" data-question-menu-trigger aria-label="Question actions" aria-expanded="false">
            <i data-lucide="more-horizontal"></i>
          </button>
          <div class="question-menu-actions" data-question-menu hidden>
            @if($question->participant)
              <form
                method="POST"
                action="{{ route('rooms.bans.store', $room) }}"
                class="msg-action-form"
                data-ban-confirm="1"
              >
                @csrf
                <input type="hidden" name="participant_id" value="{{ $question->participant->id }}">
                <button class="msg-action msg-action-ban" type="submit">
                  <i data-lucide="gavel"></i>
                  <span>Ban participant</span>
                </button>
              </form>
            @endif
            <form method="POST" action="{{ route('questions.ownerDelete', $question) }}" class="msg-action-form" data-remote="questions-panel" data-question-delete="1">
              @csrf
              @method('DELETE')
              <button class="msg-action msg-action-delete" type="submit">
                <i data-lucide="trash-2"></i>
                <span>Delete</span>
              </button>
            </form>
          </div>
        </div>
      @endif
    </div>
  </div>
  <div class="question-text">{{ $question->content }}</div>
  @php
    $studentRating = optional($question->ratings->first())->rating;
    $isAnswered = $question->status === 'answered';
    $hasFeedback = $isAnswered && $studentRating !== null;
  @endphp
  <div class="question-footer">
    @if($hasFeedback)
      <div class="question-feedback" aria-label="Student feedback">
        <span class="question-feedback__label">Student feedback:</span>
        <span class="feedback-pill {{ $studentRating === 1 ? 'feedback-pill-ok' : 'feedback-pill-bad' }}">
          {{ $studentRating === 1 ? 'Helpful' : 'Not helpful' }}
        </span>
      </div>
    @elseif($isAnswered)
      <div class="question-feedback question-feedback-empty" aria-label="Student feedback">
        <span class="question-feedback__label">Feedback:</span>
        <span class="question-feedback__value">no feedback</span>
      </div>
    @endif
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
      @else
        <span class="panel-subtitle">Only the host can manage the queue.</span>
      @endif
    </div>
  </div>
</li>
