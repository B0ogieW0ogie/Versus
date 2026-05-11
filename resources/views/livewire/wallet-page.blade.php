<div class="min-h-screen bg-navy-900 pb-24">
    <header class="sticky top-0 z-10 bg-navy-900/95 backdrop-blur px-4 h-12 flex items-center border-b border-white/5">
        <h1 class="text-base font-semibold text-white">{{ __('transactions.title') }}</h1>
    </header>

    <div class="px-4 pt-4 lg:max-w-2xl lg:mx-auto">
        <p class="text-sm text-white/55 mb-4">{{ __('transactions.subtitle') }}</p>

        <div class="rounded-xl border border-white/10 bg-white/5 divide-y divide-white/5 overflow-hidden">
            @forelse ($transactions as $tx)
                <div class="flex items-start justify-between gap-4 px-4 py-3">
                    <div class="min-w-0">
                        <div class="text-sm font-medium text-white">{{ $tx->userFacingLabel() }}</div>
                        <div class="text-xs text-white/45 mt-0.5">
                            {{ $tx->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') }}
                        </div>
                    </div>
                    <div class="shrink-0 text-sm font-semibold tabular-nums
                        {{ (float) $tx->amount >= 0 ? 'text-emerald-400' : 'text-rose-400' }}">
                        @if ((float) $tx->amount >= 0)
                            {{ __('transactions.amount_positive', ['amount' => number_format((float) $tx->amount, 0, '.', ' ')]) }}
                        @else
                            {{ __('transactions.amount_negative', ['amount' => number_format((float) $tx->amount, 0, '.', ' ')]) }}
                        @endif
                        <span class="text-white/50 font-normal text-xs ml-1">{{ __('sidebar.tokens') }}</span>
                    </div>
                </div>
            @empty
                <div class="px-4 py-8 text-center text-sm text-white/50">
                    {{ __('transactions.empty') }}
                </div>
            @endforelse
        </div>

        <div class="mt-6">
            {{ $transactions->links() }}
        </div>
    </div>
</div>
