<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 py-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white">{{ __('profile.settings_title') }}</h1>
            <a href="{{ route('profile.edit') }}"
               class="text-xs text-white/60 hover:text-white">← {{ __('profile.title') }}</a>
        </div>

        <section data-settings="language" class="flex items-center justify-between gap-4 rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            <h2 class="text-lg font-medium text-white">{{ __('nav.language') }}</h2>
            <livewire:locale-switcher />
        </section>

        <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            @include('profile.partials.update-password-form')
        </section>

        <section data-settings="logout" class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full rounded-xl border border-white/15 py-3 text-sm font-semibold text-white hover:bg-white/10">{{ __('nav.logout') }}</button>
            </form>
        </section>

        <section class="rounded-xl border border-red-500/10 bg-red-500/[0.04] p-4 sm:p-6">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
