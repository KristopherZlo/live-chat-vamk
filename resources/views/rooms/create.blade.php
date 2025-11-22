<x-app-layout>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="plus-circle"></i>
                <span>Create a room</span>
            </div>
            <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}">Back to dashboard</a>
        </div>

        <div class="panel-body stack">
            @if ($errors->any())
                <div class="alert text-danger">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('rooms.store') }}" class="stack">
                @csrf
                <label class="form-field">
                    <span class="input-label">Room title</span>
                    <input type="text" name="title" class="text-input" required>
                </label>

                <label class="form-field">
                    <span class="input-label">Description (optional)</span>
                    <textarea name="description" rows="3" class="text-input"></textarea>
                </label>

                <label class="form-field inline">
                    <input type="checkbox" name="is_public_read" value="1" class="chat-checkbox" checked>
                    <span>Let people read the room without logging in</span>
                </label>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Create room</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
