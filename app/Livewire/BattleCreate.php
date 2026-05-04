<?php

namespace App\Livewire;

use App\Actions\Battles\CreateUserBattleAction;
use App\Models\Battle;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BattleCreate extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $description = '';

    public string $side_a_label = '';

    public string $side_b_label = '';

    public string $side_a_subtitle = '';

    public string $side_b_subtitle = '';

    /** @var mixed */
    public $side_a_image = null;

    /** @var mixed */
    public $side_b_image = null;

    public ?string $opens_at = null;

    public ?string $closes_at = null;

    public string $category_id = '';

    public ?int $createdBattleId = null;

    public bool $showAiModal = false;

    public function store(CreateUserBattleAction $action): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->redirectRoute('login');

            return;
        }

        $closesRules = filled($this->opens_at)
            ? ['required', 'date', 'after:opens_at', 'after:now']
            : ['required', 'date', 'after:now'];

        $this->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'side_a_label' => ['required', 'string', 'max:255'],
            'side_b_label' => ['required', 'string', 'max:255'],
            'side_a_subtitle' => ['nullable', 'string', 'max:120'],
            'side_b_subtitle' => ['nullable', 'string', 'max:120'],
            'side_a_image' => ['nullable', 'image', 'max:2048'],
            'side_b_image' => ['nullable', 'image', 'max:2048'],
            'opens_at' => ['nullable', 'date'],
            'closes_at' => $closesRules,
            'category_id' => ['nullable', 'string'],
        ]);

        $categoryId = $this->category_id === '' ? null : (int) $this->category_id;
        if ($categoryId !== null && ! Category::query()->whereKey($categoryId)->exists()) {
            $this->addError('category_id', __('validation.exists', ['attribute' => 'category']));

            return;
        }

        $opensAt = filled($this->opens_at) ? Carbon::parse($this->opens_at) : null;
        $closesAt = Carbon::parse((string) $this->closes_at);

        $imageA = null;
        if ($this->side_a_image) {
            $stored = $this->side_a_image->store('battles/sides', 'public');
            $imageA = Storage::disk('public')->url($stored);
        }
        $imageB = null;
        if ($this->side_b_image) {
            $stored = $this->side_b_image->store('battles/sides', 'public');
            $imageB = Storage::disk('public')->url($stored);
        }

        $battle = $action($user, [
            'title' => $this->title,
            'description' => $this->description === '' ? null : $this->description,
            'side_a_label' => $this->side_a_label,
            'side_b_label' => $this->side_b_label,
            'side_a_subtitle' => $this->side_a_subtitle === '' ? null : $this->side_a_subtitle,
            'side_b_subtitle' => $this->side_b_subtitle === '' ? null : $this->side_b_subtitle,
            'side_a_image' => $imageA,
            'side_b_image' => $imageB,
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'category_id' => $categoryId,
        ]);

        $this->createdBattleId = $battle->id;
        $this->showAiModal = true;
        $id = $battle->id;
        $this->js('setTimeout(() => $wire.completeAiScreening('.$id.'), 5000)');
    }

    public function completeAiScreening(int $battleId): mixed
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            abort(401);
        }

        $battle = Battle::query()->findOrFail($battleId);

        abort_unless((int) $battle->created_by_id === (int) $user->id, 403);

        if ($battle->ai_screened_at === null) {
            $battle->forceFill(['ai_screened_at' => now()])->save();
        }

        return $this->redirect(route('battles.show', $battle), navigate: true);
    }

    public function render(): View
    {
        $categories = Category::query()->orderBy('sort_order')->get();

        return view('livewire.battle-create', [
            'categories' => $categories,
        ]);
    }
}
