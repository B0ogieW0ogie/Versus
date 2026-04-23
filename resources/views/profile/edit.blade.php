<x-app-layout>
    <div class="max-w-2xl mx-auto px-4 py-6 space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold text-white">{{ __('profile.settings_title') }}</h1>
            <a href="{{ route('profile.edit') }}"
               class="text-xs text-white/60 hover:text-white">← {{ __('profile.title') }}</a>
        </div>

        <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            @include('profile.partials.update-profile-information-form')
        </section>

        <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4 sm:p-6">
            @include('profile.partials.update-password-form')
        </section>

        <section class="rounded-xl border border-red-500/10 bg-red-500/[0.04] p-4 sm:p-6">
            @include('profile.partials.delete-user-form')
        </section>
    </div>
</x-app-layout>
