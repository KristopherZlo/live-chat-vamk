@php
    $myQuestionsCount = isset($myQuestions) ? $myQuestions->count() : 0;
@endphp

<div class="panel-header">
    <div>
        <div class="panel-title">
            <i data-lucide="help-circle"></i>
            <span>My questions</span>
        </div>
        <div class="panel-subtitle">Questions sent to the host</div>
    </div>
    <span class="queue-count-badge">{{ $myQuestionsCount }}</span>
</div>
<div class="panel-body">
    @if(isset($myQuestions) && $myQuestions->isNotEmpty())
        <ul class="questions-list">
            @foreach($myQuestions as $question)
                @php
                    $myRating = optional($question->ratings->first())->rating;
                @endphp
                <li class="question-item">
                    <div class="question-header">
                        <div class="question-meta">
                            <span class="message-meta">{{ $question->created_at->format('H:i') }}</span>
                        </div>
                        @if($room->status !== 'finished')
                            <form method="POST" action="{{ route('questions.participantDelete', $question) }}" data-remote="my-questions-panel" data-question-delete="1">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                            </form>
                        @endif
                    </div>
                    <div class="question-text">{{ $question->content }}</div>
                    @if($question->status === 'answered')
                        <div class="rating">
                            <span class="rating-label">Was this useful?</span>
                            <div class="rating-options">
                                <form method="POST" action="{{ route('questions.rate', $question) }}" data-remote="my-questions-panel">
                                    @csrf
                                    <input type="hidden" name="rating" value="1">
                                    <button class="rating-pill rating-pill-ok {{ $myRating === 1 ? 'active' : '' }}" type="submit">Yes</button>
                                </form>
                                <form method="POST" action="{{ route('questions.rate', $question) }}" data-remote="my-questions-panel">
                                    @csrf
                                    <input type="hidden" name="rating" value="-1">
                                    <button class="rating-pill rating-pill-bad {{ $myRating === -1 ? 'active' : '' }}" type="submit">No</button>
                                </form>
                            </div>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @else
        <div class="empty-state">
            You have not asked any questions yet.
        </div>
    @endif
</div>
<div class="panel-footer">
    <span>Only you can see these.</span>
    <span class="panel-subtitle">{{ $myQuestionsCount }} total</span>
</div>
