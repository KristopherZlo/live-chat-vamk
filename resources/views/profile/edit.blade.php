<x-app-layout>
    <x-slot name="header">
        <i data-lucide="user-cog"></i>
        <span>{{ __('Profile settings') }}</span>
    </x-slot>

    <div class="profile-page">
        <div class="profile-container">
            @include('profile.partials.update-profile-information-form')
            @include('profile.partials.update-password-form')
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-app-layout>
