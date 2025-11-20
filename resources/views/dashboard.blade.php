<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Мои комнаты') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4 flex justify-between items-center">
                <div class="text-gray-700">
                    Привет, {{ Auth::user()->name }}.
                </div>
                <a href="{{ route('rooms.create') }}"
                   class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700">
                    Создать новую комнату
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    @if($rooms->isEmpty())
                        <p>Пока нет комнат.</p>
                    @else
                        <table class="w-full text-left">
                            <thead>
                            <tr>
                                <th class="py-2">Название</th>
                                <th class="py-2">Статус</th>
                                <th class="py-2">Ссылка</th>
                                <th class="py-2">Действия</th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($rooms as $room)
                                <tr class="border-t">
                                    <td class="py-2">
                                        {{ $room->title }}
                                    </td>
                                    <td class="py-2">
                                        @switch($room->status)
                                            @case('active')
                                                Активен
                                                @break
                                            @case('finished')
                                                Завершён
                                                @break
                                            @default
                                                {{ $room->status }}
                                        @endswitch
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('rooms.public', $room->slug) }}"
                                           class="text-blue-600 underline"
                                           target="_blank">
                                            Открыть комнату
                                        </a>
                                    </td>
                                    <td class="py-2">
                                        <a href="{{ route('rooms.public', $room->slug) }}"
                                           class="text-sm text-blue-600 underline"
                                           target="_blank">
                                            Открыть как гость
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
