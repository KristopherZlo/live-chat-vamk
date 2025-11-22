<section class="panel">
    <div class="panel-header">
        <div>
            <div class="panel-title">{{ __('Profile Information') }}</div>
            <div class="panel-subtitle">{{ __("Update your account's profile information and email address.") }}</div>
        </div>
    </div>

    <div class="panel-body px-6 py-5 space-y-6">
        <form id="send-verification" method="post" action="{{ route('verification.send') }}">
            @csrf
        </form>

        <form method="post" action="{{ route('profile.update') }}" class="space-y-6">
            @csrf
            @method('patch')

            <div class="space-y-1">
                <x-input-label for="name" :value="__('Name')" />
                <x-text-input id="name" name="name" type="text" :value="old('name', $user->name)" required autofocus autocomplete="name" />
                <x-input-error class="mt-1" :messages="$errors->get('name')" />
            </div>

            <div class="space-y-1">
                <x-input-label for="email" :value="__('Email')" />
                <x-text-input id="email" name="email" type="email" :value="old('email', $user->email)" required autocomplete="username" />
                <x-input-error class="mt-1" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div class="space-y-2">
                        <p class="panel-subtitle">{{ __('Your email address is unverified.') }}</p>
                        <button type="submit" form="send-verification" class="btn btn-ghost btn-sm">{{ __('Click here to re-send the verification email.') }}</button>

                        @if (session('status') === 'verification-link-sent')
                            <p class="panel-subtitle text-ok">{{ __('A new verification link has been sent to your email address.') }}</p>
                        @endif
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>

                @if (session('status') === 'profile-updated')
                    <p class="panel-subtitle text-ok">{{ __('Saved.') }}</p>
                @endif
            </div>
        </form>
    </div>
</section>
