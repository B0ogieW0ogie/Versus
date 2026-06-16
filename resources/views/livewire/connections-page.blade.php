<div class="min-h-screen bg-navy-900 pb-24">
    <header class="sticky top-0 z-10 bg-navy-900/95 backdrop-blur px-4 h-12 flex items-center gap-3 border-b border-white/5">
        <a href="{{ route('profile.edit') }}" class="p-1 -ml-1 text-white/60 hover:text-white transition" aria-label="{{ __('profile.title') }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </a>
        <h1 class="text-base font-semibold text-white">{{ __('profile.' . $type) }}</h1>
    </header>

    <div class="px-4 pt-4 lg:max-w-2xl lg:mx-auto">
        <div class="rounded-xl border border-white/10 bg-white/5 divide-y divide-white/5 overflow-hidden">
            @forelse ($connections as $connection)
                {{-- Placeholder row — no data source wired up yet. --}}
            @empty
                <div class="px-4 py-8 text-center text-sm text-white/50">
                    {{ __('profile.' . $type . '_empty') }}
                </div>
            @endforelse
        </div>
    </div>
</div>
