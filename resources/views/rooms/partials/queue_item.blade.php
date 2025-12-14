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
  @php
    $studentRating = optional($question->ratings->first())->rating;
  @endphp
  @if($question->status === 'answered' && $studentRating !== null)
    <div class="question-feedback" aria-label="Student feedback">
      <span class="question-feedback__label">Student feedback:</span>
      <span class="feedback-pill {{ $studentRating === 1 ? 'feedback-pill-ok' : 'feedback-pill-bad' }}">
        {{ $studentRating === 1 ? 'Helpful' : 'Not helpful' }}
      </span>
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
