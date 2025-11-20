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
                        <form id="chat-form" method="POST" action="{{ route('rooms.messages.store', $room) }}">
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
                <div id="questions-panel" class="bg-white shadow-sm rounded-lg p-4">
                    @if($isOwner)
                        @include('rooms.partials.questions_panel')
                    @else
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
                const questionsPanel = document.getElementById('questions-panel');
                const questionsPanelUrl = @json($isOwner ? route('rooms.questionsPanel', $room) : null);

                // === helper: перезагрузка правой панели вопросов ===
                async function reloadQuestionsPanel() {
                    if (!questionsPanel || !questionsPanelUrl) return;

                    try {
                        const response = await fetch(questionsPanelUrl, {
                            headers: {
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        });

                        if (!response.ok) {
                            console.error('Не удалось обновить панель вопросов', response.status);
                            return;
                        }

                        const html = await response.text();
                        questionsPanel.innerHTML = html;
                    } catch (e) {
                        console.error('Ошибка сети при обновлении панели вопросов', e);
                    }
                }

                // === Подписка на канал комнаты ===
                if (window.Echo) {
                    const channelName = 'room.' + roomId;
                    console.log('Подписываюсь на канал', channelName);

                    window.Echo.channel(channelName)
                        .listen('MessageSent', (e) => {
                            console.log('MessageSent EVENT', e);

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
                        })
                        .listen('QuestionCreated', (e) => {
                            console.log('QuestionCreated EVENT', e);
                            reloadQuestionsPanel();
                        })
                        .listen('QuestionUpdated', (e) => {
                            console.log('QuestionUpdated EVENT', e);
                            reloadQuestionsPanel();
                        });
                } else {
                    console.warn('window.Echo не инициализирован');
                }

                // === Отправка формы без перезагрузки ===
                const chatForm = document.getElementById('chat-form');
                if (chatForm) {
                    chatForm.addEventListener('submit', async (event) => {
                        event.preventDefault();

                        const formData = new FormData(chatForm);
                        const url = chatForm.action;

                        try {
                            const response = await fetch(url, {
                                method: 'POST',
                                headers: {
                                    'X-Requested-With': 'XMLHttpRequest',
                                    'X-CSRF-TOKEN': formData.get('_token'),
                                },
                                body: formData,
                            });

                            if (!response.ok) {
                                console.error('Ошибка при отправке сообщения', response.status);
                                return;
                            }

                            const textarea = chatForm.querySelector('textarea[name="content"]');
                            if (textarea) {
                                textarea.value = '';
                            }
                            const questionCheckbox = chatForm.querySelector('input[name="as_question"]');
                            if (questionCheckbox) {
                                questionCheckbox.checked = false;
                            }

                            // само сообщение прилетит через MessageSent
                        } catch (e) {
                            console.error('Ошибка сети при отправке сообщения', e);
                        }
                    });
                }
            });
        </script>
    @endpush
</x-app-layout>
