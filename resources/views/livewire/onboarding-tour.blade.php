<div>
@if ($active)
    @php
        $bodyKey = 'onboarding.steps.'.$step.'.body';
        $body = $step === 3 ? __($bodyKey, ['amount' => $bonusAmount]) : __($bodyKey);
    @endphp
    <div class="fixed inset-0 z-[70] pointer-events-none" wire:key="onboarding-root-{{ $step }}">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-[2px] pointer-events-auto" @click.stop.prevent></div>

        <div class="absolute z-[71] w-[min(calc(100vw-2rem),20rem)] rounded-2xl border border-white/15 bg-navy-900 p-4 shadow-2xl pointer-events-auto"
             wire:key="onboarding-card-{{ $step }}"
             x-data
             x-init="
                $nextTick(() => {
                    const keys = ['avatar','bio','referral','balance','homeBattles','fabCreate','feed'];
                    const key = keys[{{ $step }}];
                    const target = document.querySelector('[data-onboarding-target=\"' + key + '\"]');
                    const card = $el;
                    if (! target) {
                        card.style.top = '6rem';
                        card.style.left = '1rem';
                        return;
                    }
                    const r = target.getBoundingClientRect();
                    const gap = 12;
                    const h = card.offsetHeight || 220;
                    let top = r.bottom + gap;
                    if (top + h > window.innerHeight - 16) {
                        top = Math.max(16, r.top - h - gap);
                    }
                    let left = r.left + (r.width / 2) - (card.offsetWidth / 2);
                    left = Math.min(Math.max(16, left), window.innerWidth - (card.offsetWidth || 320) - 16);
                    card.style.top = top + 'px';
                    card.style.left = left + 'px';
                });
             ">
            <p class="text-sm text-white/90 leading-relaxed">{{ $body }}</p>

            <div class="mt-4 flex justify-end gap-2">
                @if ($step === 2)
                    <button type="button" wire:click="copyReferralAndAdvance"
                            class="px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-vote-blue-from to-vote-purple-to shadow-vote-blue hover:opacity-95 transition">
                        {{ __('onboarding.copy') }}
                    </button>
                @else
                    <button type="button" wire:click="advance"
                            class="px-4 py-2.5 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-vote-blue-from to-vote-purple-to shadow-vote-blue hover:opacity-95 transition">
                        {{ __('onboarding.next') }}
                    </button>
                @endif
            </div>
        </div>
    </div>
@endif
</div>
