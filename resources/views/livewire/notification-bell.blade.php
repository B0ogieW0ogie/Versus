<div class="relative"
     wire:poll.visible.60s="refreshCount"
     x-data
     x-on:click.outside="$wire.open && $wire.toggle()"
     x-on:keydown.escape.window="$wire.open && $wire.toggle()">
    <button type="button"
            wire:click="toggle"
            class="relative p-2 rounded-full text-white/60 hover:text-white hover:bg-white/5 transition"
            aria-label="{{ __('notifications.title') }}">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
             stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>
        @if ($unreadCount > 0)
            <span class="absolute -top-0.5 -right-0.5 min-w-[1.1rem] h-[1.1rem] px-1 rounded-full bg-red-500 text-white text-[10px] font-bold flex items-center justify-center">
                {{ $unreadCount > 99 ? '99+' : $unreadCount }}
            </span>
        @endif
    </button>

    @if ($open)
        <div class="absolute right-0 mt-2 w-80 rounded-xl bg-navy-800 border border-white/10 shadow-xl z-50 overflow-hidden">
            <div class="flex items-center justify-between px-4 py-2.5 border-b border-white/10">
                <span class="text-sm font-semibold text-white">{{ __('notifications.title') }}</span>
                {{-- sound toggle added in Task 6 --}}
            </div>

            @if ($items->isEmpty())
                <p class="px-4 py-6 text-sm text-white/50 text-center">{{ __('notifications.empty') }}</p>
            @else
                <div class="max-h-96 overflow-y-auto divide-y divide-white/5">
                    @foreach ($items as $item)
                        <a href="{{ $item['url'] }}" wire:key="notification-{{ $item['id'] }}"
                           class="block px-4 py-3 hover:bg-white/5 transition {{ $item['fresh'] ? 'bg-white/[0.04]' : '' }}">
                            <p class="text-sm text-white/90 leading-snug">{{ $item['message'] }}</p>
                            <p class="mt-0.5 text-xs text-white/40">{{ $item['time'] }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    @endif
</div>
