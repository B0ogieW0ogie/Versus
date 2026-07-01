@props(['user'])

{{-- Single source of truth for nickname colour. Admins read orange everywhere;
     everyone else (newbies) keeps the base colour passed by the calling view. --}}
<span {{ $attributes->class(['text-orange-400' => (bool) ($user->is_admin ?? false)]) }}>{{ $user->name }}</span>
