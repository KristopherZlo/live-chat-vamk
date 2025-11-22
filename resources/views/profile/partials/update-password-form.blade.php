<section class="panel">
    <div class="panel-header">
        <div>
            <div class="panel-title">{{ __('Update Password') }}</div>
            <div class="panel-subtitle">{{ __('Ensure your account is using a long, random password to stay secure.') }}</div>
        </div>
    </div>

    <div class="panel-body px-6 py-5 space-y-6">
        <form method="post" action="{{ route('password.update') }}" class="space-y-6">
            @csrf
            @method('put')

            <div class="space-y-1">
                <x-input-label for="update_password_current_password" :value="__('Current Password')" />
                <x-text-input id="update_password_current_password" name="current_password" type="password" autocomplete="current-password" />
                <x-input-error class="mt-1" :messages="$errors->updatePassword->get('current_password')" />
            </div>

            <div class="space-y-1">
                <x-input-label for="update_password_password" :value="__('New Password')" />
                <x-text-input id="update_password_password" name="password" type="password" autocomplete="new-password" />
                <x-input-error class="mt-1" :messages="$errors->updatePassword->get('password')" />
            </div>

            <div class="space-y-1">
                <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" />
                <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" />
                <x-input-error class="mt-1" :messages="$errors->updatePassword->get('password_confirmation')" />
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>

                @if (session('status') === 'password-updated')
                    <p class="panel-subtitle text-ok">{{ __('Saved.') }}</p>
                @endif
            </div>
        </form>
    </div>
</section>
