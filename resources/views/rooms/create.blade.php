<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Создать комнату') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    @if ($errors->any())
                        <div class="mb-4 text-red-600">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('rooms.store') }}">
                        @csrf

                        <div class="mb-4">
                            <label class="block mb-1">Название комнаты</label>
                            <input type="text" name="title"
                                   class="w-full border-gray-300 rounded"
                                   required>
                        </div>

                        <div class="mb-4">
                            <label class="block mb-1">Описание (опционально)</label>
                            <textarea name="description"
                                      class="w-full border-gray-300 rounded"
                                      rows="3"></textarea>
                        </div>

                        <div class="mb-4 flex items-center">
                            <input type="checkbox" name="is_public_read" value="1"
                                   class="mr-2" checked>
                            <span>Разрешить чтение завершённого чата по ссылке</span>
                        </div>

                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 text-white rounded">
                            Создать
                        </button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
