<x-app-layout>
    <x-slot name="header">
        <i data-lucide="user-cog"></i>
        <span>{{ __('Profile settings') }}</span>
    </x-slot>

    @php
        $customSoundLabels = [
            'droplet-new-question-sound' => 'Droplet',
            'harmon-level-up-new-question-sound' => 'Level Up',
            'pop-new-question-sound' => 'Pop',
            'xilophone-new-question-sound' => 'Xylophone',
        ];
        $queueSoundOptions = collect(\Illuminate\Support\Facades\File::files(public_path('audio')))
            ->filter(fn ($file) => $file->isFile() && strtolower($file->getExtension()) === 'mp3')
            ->map(function ($file) use ($customSoundLabels) {
                $basename = $file->getBasename();
                $stem = pathinfo($basename, PATHINFO_FILENAME);
                if ($stem === 'new-question-sound') {
                    return [
                        'label' => 'Default',
                        'url' => asset('audio/' . $basename),
                        'sort' => '0-default',
                    ];
                }
                $label = $customSoundLabels[$stem]
                    ?? \Illuminate\Support\Str::of($stem)
                        ->replace('-new-question-sound', '')
                        ->replace('-', ' ')
                        ->trim()
                        ->headline();
                return [
                    'label' => $label,
                    'url' => asset('audio/' . $basename),
                    'sort' => '1-' . strtolower($label),
                ];
            })
            ->sortBy('sort')
            ->values();
    @endphp

    <div class="profile-page">
        <div class="profile-container">
            @include('profile.partials.update-profile-information-form')
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <div class="panel-title">Question sound</div>
                        <div class="panel-subtitle">Choose the sound for new questions.</div>
                    </div>
                </div>
                <div class="panel-body px-6 py-5 space-y-4">
                    @if($queueSoundOptions->isEmpty())
                        <p class="panel-subtitle">No sound files found in public/audio.</p>
                    @else
                        <div class="space-y-1">
                            <label class="input-label" for="queueSoundSelect">Sound</label>
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="field-select">
                                    <select
                                        id="queueSoundSelect"
                                        class="field-control field-control-select"
                                        data-queue-sound-select
                                        data-queue-sound-notice="Sound selection saved."
                                    >
                                        @foreach ($queueSoundOptions as $option)
                                            <option value="{{ $option['url'] }}">{{ $option['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <button class="btn btn-primary btn-sm" type="button" data-queue-sound-preview>
                                    <i data-lucide="play"></i>
                                    <span>Play</span>
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </section>
            @include('profile.partials.update-password-form')
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
