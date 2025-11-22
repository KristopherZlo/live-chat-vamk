<section class="panel">
    <div class="panel-header">
        <div>
            <div class="panel-title">{{ __('Delete Account') }}</div>
            <div class="panel-subtitle">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}</div>
        </div>
    </div>

    <div class="panel-body px-6 py-5 space-y-5">
        <button
            type="button"
            class="btn btn-danger"
            x-data=""
            x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
        >
            {{ __('Delete Account') }}
        </button>

        <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
            <form method="post" action="{{ route('profile.destroy') }}" class="space-y-6 p-6">
                @csrf
                @method('delete')

                <h2 class="text-lg font-semibold">{{ __('Are you sure you want to delete your account?') }}</h2>
                <p class="panel-subtitle">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}</p>

                <div class="space-y-1">
                    <x-input-label for="password" value="{{ __('Password') }}" class="sr-only" />
                    <x-text-input
                        id="password"
                        name="password"
                        type="password"
                        placeholder="{{ __('Password') }}"
                    />
                    <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1" />
                </div>

                <div class="flex flex-wrap justify-end gap-3">
                    <button type="button" class="btn btn-ghost" x-on:click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </button>

                    <button type="submit" class="btn btn-danger">
                        {{ __('Delete Account') }}
                    </button>
                </div>
            </form>
        </x-modal>
    </div>
</section>
