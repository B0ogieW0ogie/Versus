<div class="mt-3">
    @if ($comments->isEmpty())
        <div class="rounded-xl border border-dashed border-white/10 p-6 text-center text-sm text-white/50">
            {{ __('profile.comments_empty') }}
        </div>
    @else
        <ul class="space-y-2">
            @foreach ($comments as $comment)
                <li class="rounded-xl border border-white/5 bg-white/[0.03] p-3">
                    <p class="text-sm text-white/90 whitespace-pre-line">{{ $comment->body }}</p>
                    <div class="mt-2 text-[11px] text-white/50 flex justify-between">
                        <a href="{{ route('battles.show', $comment->battle) }}" class="hover:text-white">
                            {{ __('profile.comments_on') }}
                            <span class="text-white/70">{{ $comment->battle->title }}</span>
                        </a>
                        <span>{{ $comment->created_at->diffForHumans(['parts' => 1, 'short' => true]) }}</span>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-3">
            {{ $comments->links() }}
        </div>
    @endif
</div>
