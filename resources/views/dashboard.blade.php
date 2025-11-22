<x-app-layout>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="layout-dashboard"></i>
                <span>Your rooms</span>
            </div>
            <a href="{{ route('rooms.create') }}" class="btn btn-sm btn-primary">Create room</a>
        </div>

        <div class="panel-body">
            <p class="message-meta">Hi {{ Auth::user()->name }} — manage your live rooms below.</p>

            @if($rooms->isEmpty())
                <div class="text-muted">No rooms yet.</div>
            @else
                <div class="table">
                    <div class="table-row table-head">
                        <div>Title</div>
                        <div>Status</div>
                        <div>Public link</div>
                        <div>Actions</div>
                    </div>
                    @foreach($rooms as $room)
                        <div class="table-row">
                            <div class="table-cell">{{ $room->title }}</div>
                            <div class="table-cell">
                                <span class="status-pill status-{{ $room->status }}">{{ $room->status }}</span>
                            </div>
                            <div class="table-cell">
                                <a href="{{ route('rooms.public', $room->slug) }}" class="btn btn-sm btn-ghost" target="_blank" rel="noreferrer">Open</a>
                            </div>
                            <div class="table-cell">
                                <a href="{{ route('rooms.public', $room->slug) }}" class="btn btn-sm btn-primary" target="_blank" rel="noreferrer">View live</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
