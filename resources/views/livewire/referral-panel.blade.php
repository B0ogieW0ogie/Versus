<div class="py-12">
    <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
        <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100">Your referral link</h2>
            <div class="mt-4 flex items-center gap-3" x-data="{ url: '{{ $referralUrl }}', copied: false }">
                <input readonly value="{{ $referralUrl }}"
                       class="flex-1 rounded border-gray-300 dark:bg-gray-900 dark:border-gray-700 text-sm">
                <button type="button"
                        @click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
                        class="rounded-md bg-indigo-600 px-4 py-2 text-white font-semibold hover:bg-indigo-500">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                Share it to earn {{ (int) (config('versus.referral.winner_cut') * 100) }}% of every win from the people you bring in
                (paid from the shared reward pool).
            </p>
        </section>

        <section class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Your referrals</h3>
                <div class="text-right">
                    <p class="text-xs text-gray-500 dark:text-gray-400 uppercase tracking-wide">Earned</p>
                    <p class="text-lg font-bold text-indigo-600">{{ number_format($earned, 2) }}</p>
                </div>
            </div>

            @if ($referrals->isEmpty())
                <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No one has joined through your link yet.</p>
            @else
                <ul class="mt-4 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($referrals as $referral)
                        <li class="py-2 flex justify-between text-sm">
                            <span class="text-gray-900 dark:text-gray-100">{{ $referral->name }}</span>
                            <span class="text-gray-500 dark:text-gray-400">joined {{ $referral->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</div>
