<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            Комната: {{ $room->title }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8">

            @if (session('status'))
                <div class="mb-4 text-green-600">
                    {{ session('status') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 text-red-600">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                {{-- Чат --}}
                <div class="md:col-span-2 bg-white shadow-sm rounded-lg p-4 flex flex-col h-[70vh]">
                    <div class="flex-1 overflow-y-auto mb-4 border-b pb-2 messages-container">
                        @forelse($messages as $message)
                            <div class="mb-2">
                                <div class="text-sm text-gray-500">
                                    @if($message->user && $message->user_id === $room->user_id)
                                        <span class="font-semibold text-blue-700">
                                            {{ $message->user->name }} (host)
                                        </span>
                                    @elseif($message->participant)
                                        <span class="font-semibold">
                                            {{ $message->participant->display_name }}
                                        </span>
                                    @else
                                        <span class="italic text-gray-400">Система</span>
                                    @endif

                                    <span class="ml-2 text-xs text-gray-400">
                                        {{ $message->created_at->format('H:i') }}
                                    </span>
                                </div>
                                <div>{{ $message->content }}</div>
                            </div>
                        @empty
                            <p class="text-gray-500">Пока нет сообщений.</p>
                        @endforelse
                    </div>

                    @if($room->status !== 'finished')
                        <form method="POST" action="{{ route('rooms.messages.store', $room) }}">
                            @csrf
                            <div class="mb-2">
                                <textarea name="content"
                                          class="w-full border-gray-300 rounded"
                                          rows="3"
                                          placeholder="Напиши сообщение..."
                                          required></textarea>
                            </div>
                            <div class="flex items-center justify-between">
                                <label class="flex items-center">
                                    <input type="checkbox" name="as_question" value="1" class="mr-2">
                                    <span>Также отправить как вопрос создателю</span>
                                </label>
                                <button type="submit"
                                        class="px-4 py-2 bg-blue-600 text-white rounded">
                                    Отправить
                                </button>
                            </div>
                        </form>
                    @else
                        <p class="text-gray-500 mt-2">
                            Чат завершён. Новые сообщения отправить нельзя.
                        </p>
                    @endif

                    {{-- Мои вопросы (только для гостя) --}}
                    @if(!$isOwner && isset($myQuestions) && $myQuestions->isNotEmpty())
                        <div class="mt-4 border-t pt-2">
                            <h3 class="font-semibold text-sm mb-2">Мои вопросы</h3>
                            <ul class="space-y-2 text-sm">
                                @foreach($myQuestions as $question)
                                    @php
                                        $myRating = optional($question->ratings->first())->rating;
                                    @endphp

                                    <li class="border rounded p-2">
                                        <div class="text-xs text-gray-500 mb-1">
                                            {{ $question->created_at->format('H:i') }}
                                            <span class="ml-2 text-gray-400">
                                                статус: {{ $question->status }}
                                            </span>
                                        </div>
                                        <div class="mb-2">
                                            {{ $question->content }}
                                        </div>

                                        <div class="flex items-center gap-2 flex-wrap">

                                            @if($room->status !== 'finished')
                                                {{-- удалить вопрос --}}
                                                <form method="POST"
                                                      action="{{ route('questions.participantDelete', $question) }}"
                                                      onsubmit="return confirm('Удалить этот вопрос?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                            class="px-2 py-1 bg-red-600 text-white rounded text-xs">
                                                        Удалить
                                                    </button>
                                                </form>
                                            @endif

                                            {{-- лайк/дизлайк ответа, если вопрос отвечён --}}
                                            @if($question->status === 'answered')
                                                <span class="text-xs text-gray-500 ml-2">
                                                    Оцени ответ:
                                                </span>

                                                <form method="POST" action="{{ route('questions.rate', $question) }}">
                                                    @csrf
                                                    <input type="hidden" name="rating" value="1">
                                                    <button type="submit"
                                                            class="px-2 py-1 rounded text-xs
                                                                {{ $myRating === 1 ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-800' }}">
                                                        👍
                                                    </button>
                                                </form>

                                                <form method="POST" action="{{ route('questions.rate', $question) }}">
                                                    @csrf
                                                    <input type="hidden" name="rating" value="-1">
                                                    <button type="submit"
                                                            class="px-2 py-1 rounded text-xs
                                                                {{ $myRating === -1 ? 'bg-red-600 text-white' : 'bg-gray-200 text-gray-800' }}">
                                                        👎
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                {{-- Правая панель владельца / инфо --}}
                <div class="bg-white shadow-sm rounded-lg p-4">
                    @if($isOwner)
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
                                                    скрыт из очереди
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
                    @else
                        {{-- блок для гостя --}}
                        <h3 class="font-semibold mb-2">Информация</h3>
                        <p class="text-sm text-gray-600">
                            Ты пишешь как анонимный участник:
                            @if($participant && $participant->display_name)
                                <span class="font-semibold">{{ $participant->display_name }}</span>
                            @else
                                <span class="italic text-gray-500">гость</span>
                            @endif
                        </p>
                    @endif
                </div>
            </div>

        </div>
    </div>
    @push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const roomId = {{ $room->id }};

        if (!window.Echo) {
            console.warn('Echo не инициализирован');
            return;
        }

        console.log('Подписываюсь на канал room.' + roomId);

        window.Echo.channel('room.' + roomId)
            .listen('MessageSent', (e) => {
                console.log('Поймали событие MessageSent', e);

                const container = document.querySelector('.messages-container');
                if (!container) return;

                const wrapper = document.createElement('div');
                wrapper.classList.add('mb-2');

                const isOwner = e.author.type === 'owner';
                const time = new Date(e.created_at).toLocaleTimeString([], {
                    hour: '2-digit',
                    minute: '2-digit',
                });

                wrapper.innerHTML = `
                    <div class="text-sm text-gray-500">
                        <span class="font-semibold ${isOwner ? 'text-blue-700' : ''}">
                            ${e.author.name}${isOwner ? ' (host)' : ''}
                        </span>
                        <span class="ml-2 text-xs text-gray-400">
                            ${time}
                        </span>
                    </div>
                    <div>${e.content}</div>
                `;

                container.appendChild(wrapper);
                container.scrollTop = container.scrollHeight;
            });
    });
</script>
@endpush
</x-app-layout>
