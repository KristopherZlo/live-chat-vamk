{{-- Панель владельца: очередь + история вопросов --}}

<h3 class="font-semibold mb-2">Очередь вопросов</h3>

@if($queueQuestions->isEmpty())
    <p class="text-sm text-gray-600">Пока нет новых вопросов.</p>
@else
    <ul class="space-y-3">
        @foreach($queueQuestions as $question)
            <li class="border rounded p-2">
                <div class="text-xs text-gray-500 mb-1">
                    От:
                    @if($question->participant)
                        <span class="font-semibold">
                            {{ $question->participant->display_name }}
                        </span>
                    @else
                        <span class="italic">аноним</span>
                    @endif
                    <span class="ml-2">
                        {{ $question->created_at->format('H:i') }}
                    </span>
                    <span class="ml-2 text-gray-400">
                        статус: {{ $question->status }}
                    </span>
                </div>

                <div class="mb-2 text-sm">
                    {{ $question->content }}
                </div>

                <div class="flex flex-wrap gap-1 text-xs">
                    {{-- смена статуса --}}
                    <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                        @csrf
                        <input type="hidden" name="status" value="answered">
                        <button type="submit"
                                class="px-2 py-1 bg-green-600 text-white rounded">
                            Ответил
                        </button>
                    </form>

                    <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                        @csrf
                        <input type="hidden" name="status" value="ignored">
                        <button type="submit"
                                class="px-2 py-1 bg-gray-500 text-white rounded">
                            Игнор
                        </button>
                    </form>

                    <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                        @csrf
                        <input type="hidden" name="status" value="later">
                        <button type="submit"
                                class="px-2 py-1 bg-yellow-500 text-white rounded">
                            Отвечу позже
                        </button>
                    </form>

                    {{-- скрыть из очереди, но оставить в истории --}}
                    <form method="POST" action="{{ route('questions.ownerDelete', $question) }}"
                          onsubmit="return confirm('Скрыть вопрос из очереди? В истории он останется.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-2 py-1 bg-red-600 text-white rounded">
                            Скрыть
                        </button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
@endif

<h3 class="font-semibold mt-4 mb-2">История вопросов</h3>

@if($historyQuestions->isEmpty())
    <p class="text-sm text-gray-600">История пуста.</p>
@else
    <ul class="space-y-3 max-h-64 overflow-y-auto text-sm">
        @foreach($historyQuestions as $question)
            @php
                $likes = $question->ratings->where('rating', 1)->count();
                $dislikes = $question->ratings->where('rating', -1)->count();
            @endphp

            <li class="border rounded p-2">
                <div class="text-xs text-gray-500 mb-1">
                    От:
                    @if($question->participant)
                        <span class="font-semibold">
                            {{ $question->participant->display_name }}
                        </span>
                    @else
                        <span class="italic">аноним</span>
                    @endif

                    <span class="ml-2">
                        {{ $question->created_at->format('d.m H:i') }}
                    </span>

                    <span class="ml-2 text-gray-400">
                        статус: {{ $question->status }}
                    </span>

                    @if($question->deleted_by_owner_at)
                        <span class="ml-2 text-red-500">
                            скрыт владельцем
                        </span>
                    @endif

                    @if($question->deleted_by_participant_at)
                        <span class="ml-2 text-orange-500">
                            скрыт участником
                        </span>
                    @endif
                </div>

                <div class="mb-2">
                    {{ $question->content }}
                </div>

                @if($question->status === 'answered')
                    <div class="mb-2 text-xs text-gray-600">
                        Оценка ответа:
                        <span class="ml-1">👍 {{ $likes }}</span>
                        <span class="ml-1">👎 {{ $dislikes }}</span>
                    </div>
                @endif

                <div class="flex flex-wrap gap-1 text-xs">
                    {{-- вернуть в очередь, если не new --}}
                    @if($question->status !== 'new')
                        <form method="POST" action="{{ route('questions.updateStatus', $question) }}">
                            @csrf
                            <input type="hidden" name="status" value="new">
                            <button type="submit"
                                    class="px-2 py-1 bg-blue-600 text-white rounded">
                                Вернуть в очередь
                            </button>
                        </form>
                    @endif

                    {{-- удалить навсегда --}}
                    <form method="POST" action="{{ route('questions.destroy', $question) }}"
                          onsubmit="return confirm('Удалить вопрос навсегда? Это действие необратимо.');">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-2 py-1 bg-red-700 text-white rounded">
                            Удалить навсегда
                        </button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
@endif
