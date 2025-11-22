@php
    $queueCount = $queueQuestions->count();
    $historyCount = $historyQuestions->count();
@endphp

<section class="panel queue-panel mobile-panel mobile-active" data-mobile-panel="queue" id="queuePanel">
  <div class="panel-header">
    <div>
      <div class="panel-title">
        <i data-lucide="list-ordered"></i>
        <span>Question queue</span>
      </div>
      <div class="panel-subtitle">New questions from participants</div>
    </div>
    <div class="queue-header-extra">
      <button class="btn btn-sm btn-ghost history-open-btn" type="button" data-toggle-history>
        <i data-lucide="clock"></i>
        <span>Open history</span>
      </button>
      <span class="queue-action">{{ $queueCount }} open</span>
    </div>
  </div>

  <div class="panel-body">
    @if($queueQuestions->isEmpty())
      <p class="empty-state">No pending questions.</p>
    @else
      <ul class="queue-list">
        @foreach($queueQuestions as $question)
          <li class="queue-item" data-status="{{ $question->status }}">
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
              <div class="queue-controls">
                <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                  @csrf
                  <input type="hidden" name="status" value="answered">
                  <button type="submit" class="queue-action queue-action-answered">Answered</button>
                </form>
                <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                  @csrf
                  <input type="hidden" name="status" value="ignored">
                  <button type="submit" class="queue-action queue-action-ignored">Ignore</button>
                </form>
                <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                  @csrf
                  <input type="hidden" name="status" value="later">
                  <button type="submit" class="queue-action queue-action-later">Later</button>
                </form>
              </div>
              <form method="POST" action="{{ route('questions.ownerDelete', $question) }}" onsubmit="return confirm('Delete this question permanently?');">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
              </form>
            </div>
          </li>
        @endforeach
      </ul>
    @endif
  </div>

  <div class="panel-footer">
    <span>{{ $queueCount }} open questions</span>
    <span class="panel-subtitle">Set status to move to history</span>
  </div>
</section>

<section class="panel history-panel mobile-panel" data-mobile-panel="history" id="historyPanel">
  <div class="panel-header">
    <div>
      <div class="panel-title">
        <i data-lucide="archive-restore"></i>
        <span>Question history</span>
      </div>
      <div class="panel-subtitle">All questions for this room</div>
    </div>
    <span class="queue-action">{{ $historyCount }} total</span>
  </div>

  <div class="panel-body">
    @if($historyQuestions->isEmpty())
      <p class="empty-state">No history yet.</p>
    @else
      <ul class="history-list">
        @foreach($historyQuestions as $question)
          @php
              $likes = $question->ratings->where('rating', 1)->count();
              $dislikes = $question->ratings->where('rating', -1)->count();
          @endphp
          <li class="history-item">
            <div class="question-header">
              <div class="question-meta">
                <span class="message-author">{{ $question->participant?->display_name ?? 'Anonymous' }}</span>
                <span class="message-meta">{{ $question->created_at->format('d.m H:i') }}</span>
              </div>
              <span class="status-pill status-{{ $question->status }}">{{ ucfirst($question->status) }}</span>
            </div>
            <div class="question-text">{{ $question->content }}</div>

            @if($question->status === 'answered')
              <div class="rating">
                <span class="rating-label">Students feedback:</span>
                <span class="rating-pill rating-pill-ok">clear {{ $likes }}</span>
                <span class="rating-pill rating-pill-bad">unclear {{ $dislikes }}</span>
              </div>
            @endif

            <div class="question-actions">
              <div class="queue-controls">
                @if($question->status !== 'new')
                  <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                    @csrf
                    <input type="hidden" name="status" value="new">
                    <button type="submit" class="queue-action">Move to queue</button>
                  </form>
                @endif
              </div>
              <form method="POST" action="{{ route('questions.destroy', $question) }}" onsubmit="return confirm('Delete this record for good?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-danger">Delete</button>
              </form>
            </div>
          </li>
        @endforeach
      </ul>
    @endif
  </div>

  <div class="panel-footer">
    <span>{{ $historyCount }} total questions</span>
    <span class="panel-subtitle">Restore to queue if needed</span>
  </div>
</section>
