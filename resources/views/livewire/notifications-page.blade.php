<div data-page="notifications" class="min-h-screen bg-navy-900 px-4 pb-24 pt-6 text-white sm:pb-10 lg:px-0">
    <h1 class="text-2xl font-bold">{{ __('notifications.title') }}</h1>

    <ul class="mt-5 space-y-2">
        @forelse ($items as $item)
            <li wire:key="notification-{{ $item['id'] }}">
                <a href="{{ $item['url'] }}"
                   @class([
                       'flex items-start gap-3 rounded-2xl border px-4 py-3 transition hover:bg-white/[0.06]',
                       'border-indigo-500/40 bg-indigo-500/10' => $item['fresh'],
                       'border-white/10 bg-white/[0.03]' => ! $item['fresh'],
                   ])>
                    <span @class(['mt-2 h-2 w-2 shrink-0 rounded-full', 'bg-indigo-400' => $item['fresh'], 'bg-transparent' => ! $item['fresh']])></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm text-white/90">{{ $item['message'] }}</span>
                        <span class="mt-1 block text-xs text-white/50">{{ $item['time'] }}</span>
                    </span>
                </a>
            </li>
        @empty
            <li class="rounded-2xl border border-white/10 bg-white/[0.03] p-8 text-center text-sm text-white/60">{{ __('notifications.empty') }}</li>
        @endforelse
    </ul>

    @if ($hasMore)
        <button type="button" wire:click="loadMore" class="mt-6 w-full rounded-xl border border-white/15 py-3 text-sm font-semibold hover:bg-white/5">{{ __('feed.load_more') }}</button>
    @endif
</div>
