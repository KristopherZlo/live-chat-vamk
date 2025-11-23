<x-app-layout>
    <div class="panel">
        <div class="panel-header">
            <div class="panel-title">
                <i data-lucide="radio"></i>
                <span>Welcome to Ghost Room</span>
            </div>
        </div>
        <div class="panel-body">
            <p class="text-muted">Start by creating a room and sharing the public link with your students.</p>
            <a class="btn btn-primary" href="{{ route('register') }}">Get started</a>
        </div>
    </div>
</x-app-layout>
