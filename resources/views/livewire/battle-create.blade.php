<div class="max-w-2xl mx-auto px-4 py-12 text-white">
    <h1 class="text-xl font-semibold">{{ __('battle.create_title') }}</h1>
    <p class="mt-3 text-sm text-white/60 leading-relaxed">{{ __('battle.create_intro') }}</p>

    <form wire:submit.prevent="store" class="mt-8 space-y-6">
        <div>
            <label for="title" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_title') }}</label>
            <input id="title" type="text" wire:model="title"
                   class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white placeholder-white/40 focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
            @error('title') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_description') }}</label>
            <textarea id="description" wire:model="description" rows="3"
                      class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white placeholder-white/40 focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="side_a_label" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_side_a') }}</label>
                <input id="side_a_label" type="text" wire:model="side_a_label"
                       class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
                @error('side_a_label') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="side_b_label" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_side_b') }}</label>
                <input id="side_b_label" type="text" wire:model="side_b_label"
                       class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
                @error('side_b_label') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="side_a_subtitle" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_side_a_sub') }}</label>
                <input id="side_a_subtitle" type="text" wire:model="side_a_subtitle"
                       class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
                @error('side_a_subtitle') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="side_b_subtitle" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_side_b_sub') }}</label>
                <input id="side_b_subtitle" type="text" wire:model="side_b_subtitle"
                       class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
                @error('side_b_subtitle') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="side_a_image" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_image_a') }}</label>
                <input id="side_a_image" type="file" accept="image/*" wire:model="side_a_image"
                       class="mt-1 block w-full text-sm text-white/80 file:mr-3 file:rounded-md file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-white"/>
                @error('side_a_image') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="side_b_image" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_image_b') }}</label>
                <input id="side_b_image" type="file" accept="image/*" wire:model="side_b_image"
                       class="mt-1 block w-full text-sm text-white/80 file:mr-3 file:rounded-md file:border-0 file:bg-white/10 file:px-3 file:py-1.5 file:text-white"/>
                @error('side_b_image') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="opens_at" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_opens_at') }}</label>
                <input id="opens_at" type="datetime-local" wire:model="opens_at"
                       class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
                @error('opens_at') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="closes_at" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_closes_at') }}</label>
                <input id="closes_at" type="datetime-local" wire:model="closes_at"
                       class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40"/>
                @error('closes_at') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div>
            <label for="category_id" class="block text-sm font-medium text-white/80">{{ __('battle.create_field_category') }}</label>
            <select id="category_id" wire:model="category_id"
                    class="mt-1 block w-full rounded-lg border border-white/15 bg-navy-900 px-3 py-2 text-sm text-white focus:border-cyan-400/60 focus:outline-none focus:ring-1 focus:ring-cyan-400/40">
                <option value="">{{ __('battle.create_category_none') }}</option>
                @foreach ($categories as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->localized_name }}</option>
                @endforeach
            </select>
            @error('category_id') <p class="mt-1 text-sm text-red-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-4">
            <button type="submit"
                    class="inline-flex items-center justify-center rounded-lg bg-gradient-to-r from-cyan-500 to-indigo-500 px-5 py-2.5 text-sm font-semibold text-white shadow hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-cyan-400/50 disabled:opacity-50"
                    wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="store">{{ __('battle.create_submit') }}</span>
                <span wire:loading wire:target="store">{{ __('battle.create_submit_loading') }}</span>
            </button>
            <a href="{{ route('battles.index') }}" class="text-sm text-white/60 hover:text-white hover:underline">{{ __('battle.back_to_battles') }}</a>
        </div>
    </form>

    @if ($showAiModal)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 px-4">
            <div class="w-full max-w-sm rounded-2xl border border-white/10 bg-navy-800 p-8 text-center shadow-xl">
                <div class="mx-auto h-10 w-10 animate-spin rounded-full border-2 border-white/20 border-t-cyan-400"></div>
                <p class="mt-6 text-sm text-white/90">{{ __('battle.ai_checking') }}</p>
            </div>
        </div>
    @endif
</div>
