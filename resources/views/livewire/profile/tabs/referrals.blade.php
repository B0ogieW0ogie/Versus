<div class="mt-4 space-y-4">
    <section data-onboarding-target="referral" class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
        <h3 class="text-sm font-semibold text-white">{{ __('profile.referrals_link_heading') }}</h3>
        <div class="mt-3 flex items-center gap-2"
             x-data="{ url: '{{ $referralUrl }}', copied: false }">
            <input readonly value="{{ $referralUrl }}"
                   class="flex-1 text-xs bg-navy-800 border border-white/10 rounded-md px-2 py-1.5 text-white/80">
            <button type="button"
                    @click="navigator.clipboard.writeText(url).then(() => { copied = true; setTimeout(() => copied = false, 1500); })"
                    class="text-xs font-semibold bg-vote-purple-to hover:opacity-90 rounded-md px-3 py-1.5 text-white">
                <span x-show="!copied">{{ __('profile.referrals_copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('profile.referrals_copied') }}</span>
            </button>
        </div>
    </section>

    <section class="rounded-xl border border-white/5 bg-white/[0.03] p-4">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-white">{{ __('profile.referrals_list_heading') }}</h3>
            <div class="text-right">
                <div class="text-[10px] uppercase tracking-wider text-white/50">{{ __('profile.referrals_earned') }}</div>
                <div class="text-base font-bold text-vote-purple-to">{{ number_format($referralEarned, 0) }}</div>
            </div>
        </div>

        @if ($referrals->isEmpty())
            <p class="mt-3 text-xs text-white/55">{{ __('profile.referrals_empty') }}</p>
        @else
            <ul class="mt-3 divide-y divide-white/5">
                @foreach ($referrals as $referral)
                    <li class="py-2 flex justify-between text-xs">
                        <span class="text-white/90"><x-user-name :user="$referral" /></span>
                        <span class="text-white/50">
                            {{ __('profile.referrals_joined', ['when' => $referral->created_at->diffForHumans()]) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
