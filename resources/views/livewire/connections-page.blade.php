<div class="min-h-screen bg-navy-900 pb-24">
    <header class="sticky top-0 z-10 bg-navy-900/95 backdrop-blur px-4 h-12 flex items-center gap-3 border-b border-white/5">
        <a href="{{ route('profile.show', $profileUser) }}" class="p-1 -ml-1 text-white/60 hover:text-white transition" aria-label="{{ __('profile.title') }}">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
            </svg>
        </a>
        <h1 class="text-base font-semibold text-white">{{ __('profile.' . $type) }}</h1>
    </header>

    <div class="px-4 pt-4 lg:max-w-2xl lg:mx-auto">
        <div class="rounded-xl border border-white/10 bg-white/5 divide-y divide-white/5 overflow-hidden">
            @forelse ($people as $person)
                @php
                    $handle = $person->username ? '@' . $person->username : '@' . __('profile.username_fallback_prefix') . $person->id;
                @endphp
                <div class="flex items-center gap-3 px-4 py-3">
                    <a href="{{ route('profile.show', $person) }}"
                       class="h-11 w-11 shrink-0 rounded-full bg-navy-700 overflow-hidden flex items-center justify-center">
                        @if ($person->avatarUrl())
                            <img src="{{ $person->avatarUrl() }}" alt="" class="w-full h-full object-cover">
                        @else
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-6 w-6 text-white/30">
                                <path d="M12 12a5 5 0 100-10 5 5 0 000 10zm0 2c-4.418 0-8 2.686-8 6v2h16v-2c0-3.314-3.582-6-8-6z"/>
                            </svg>
                        @endif
                    </a>
                    <a href="{{ route('profile.show', $person) }}" class="min-w-0 flex-1">
                        <div class="text-sm font-semibold text-white truncate">{{ $person->name }}</div>
                        <div class="text-xs text-white/50 truncate">{{ $handle }}</div>
                    </a>
                    @if ($person->id !== $viewerId)
                        <button type="button" wire:click="toggleFollow({{ $person->id }})"
                                class="text-xs font-semibold rounded-lg px-4 py-1.5 transition shrink-0
                                       {{ in_array($person->id, $followingIds, true)
                                           ? 'text-white/70 border border-white/20 hover:bg-white/5'
                                           : 'text-white border border-vote-purple-to bg-vote-purple-to/20 hover:bg-vote-purple-to/30' }}">
                            {{ in_array($person->id, $followingIds, true) ? __('profile.unfollow') : __('profile.follow') }}
                        </button>
                    @endif
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-white/50">
                    {{ __('profile.' . $type . '_empty') }}
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $people->links() }}
        </div>
    </div>
</div>
