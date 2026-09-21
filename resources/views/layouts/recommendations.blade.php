{{-- Desktop left column: profile recommendations. Same place on every page; only the content refreshes. --}}
@inject('recommended', 'App\Services\Recommendations\RecommendedProfiles')
@php $groups = $recommended->groups(auth()->user()); @endphp

<section data-recommendations class="rounded-2xl border border-white/10 bg-white/[0.03] p-4 text-white">
    <h2 class="text-lg font-bold">{{ __('challenges.may_like_title') }}</h2>
    <p class="mt-1 text-sm text-white/60">{{ __('challenges.may_like_subtitle') }}</p>

    @forelse ($groups as $group)
        <div class="mt-4 rounded-xl border border-white/10 bg-white/[0.03] p-3">
            <span class="inline-flex rounded-full bg-indigo-600/30 px-2.5 py-0.5 text-xs font-semibold text-indigo-200"># {{ __('challenges.category_'.$group['category']) }}</span>
            <ul class="mt-3 grid grid-cols-3 gap-2">
                @foreach ($group['users'] as $user)
                    <li>
                        <a href="{{ route('profile.show', $user) }}" class="flex flex-col items-center gap-1 text-center hover:text-white">
                            @if ($user->avatarUrl())
                                <img src="{{ $user->avatarUrl() }}" alt="" class="h-12 w-12 rounded-full object-cover ring-2 ring-white/10">
                            @else
                                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-indigo-600 text-lg font-bold ring-2 ring-white/10">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                            @endif
                            <span class="w-full truncate text-xs text-white/70">{{ $user->username ?? $user->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
        <p class="mt-6 text-sm text-white/40">{{ __('challenges.may_like_empty') }}</p>
    @endforelse
</section>
