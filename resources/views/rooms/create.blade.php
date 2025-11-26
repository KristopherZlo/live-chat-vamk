<x-app-layout>
    <section class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="plus-circle"></i>
                <span>Create a room</span>
            </div>
            <a class="btn btn-sm btn-ghost" href="{{ route('dashboard') }}">
                <i data-lucide="layout-dashboard"></i>
                <span>Back</span>
            </a>
        </div>
        <div class="panel-body px-6 py-5 space-y-5">
            <form method="POST" action="{{ route('rooms.store') }}" class="stack-md" id="roomForm">
                @csrf

                @if ($errors->any())
                    <div class="form-alert">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <label class="input-field">
                    <span class="input-label">Room title</span>
                    <input
                        type="text"
                        name="title"
                        class="field-control"
                        value="{{ old('title') }}"
                        placeholder="Example: Databases Q&A, Week 6"
                        required
                        autofocus
                        data-onboarding-target="room-title"
                    >
                </label>

                <label class="input-field">
                    <span class="input-label">Description</span>
                <textarea
                    name="description"
                    rows="3"
                    class="field-control"
                    placeholder="Add a short agenda or instructions (optional)"
                    data-onboarding-target="room-description"
                >{{ old('description') }}</textarea>
            </label>

                <div class="form-footer">
                    <span class="panel-subtitle">Instantly appears on your dashboard.</span>
                    <button type="submit" class="btn btn-primary" data-onboarding-target="room-submit">Create room</button>
                </div>
            </form>
        </div>
    </section>
</x-app-layout>
