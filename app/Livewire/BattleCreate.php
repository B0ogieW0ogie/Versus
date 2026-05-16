<?php

namespace App\Livewire;

use App\Actions\Battles\CreateUserBattleAction;
use App\Models\Battle;
use App\Models\Category;
use App\Models\User;
use App\Support\BattleDurationPreset;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class BattleCreate extends Component
{
    use WithFileUploads;

    public const PIN_VS_PER_HOUR = 1000;

    public string $side_a_label = '';

    public string $side_b_label = '';

    /** @var mixed */
    public $side_a_image = null;

    /** @var mixed */
    public $side_b_image = null;

    public string $duration_preset = BattleDurationPreset::DEFAULT;

    public bool $pin_to_charts = false;

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

        $this->validate([
            'side_a_label' => ['required', 'string', 'max:255'],
            'side_b_label' => ['required', 'string', 'max:255'],
            'side_a_image' => ['nullable', 'image', 'max:2048'],
            'side_b_image' => ['nullable', 'image', 'max:2048'],
            'duration_preset' => ['required', Rule::in(BattleDurationPreset::PRESETS)],
            'pin_to_charts' => ['boolean'],
            'category_id' => ['required', 'exists:categories,id'],
        ]);

        $categoryId = (int) $this->category_id;

        $closesAt = BattleDurationPreset::closesAt($this->duration_preset, now());

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
            'side_a_label' => $this->side_a_label,
            'side_b_label' => $this->side_b_label,
            'side_a_image' => $imageA,
            'side_b_image' => $imageB,
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
            'durationPresets' => BattleDurationPreset::PRESETS,
        ]);
    }

    public function pinningTotalVs(): int
    {
        if (! $this->pin_to_charts) {
            return 0;
        }

        return BattleDurationPreset::hours($this->duration_preset) * self::PIN_VS_PER_HOUR;
    }

    public function formattedPinningTotal(): string
    {
        return number_format($this->pinningTotalVs(), 0, '.', ',');
    }
}
