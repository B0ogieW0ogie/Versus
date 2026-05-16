<?php

namespace App\Filament\Admin\Resources\Battles\Pages;

use App\Filament\Admin\Resources\Battles\BattleResource;
use App\Support\BattleDurationPreset;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Carbon;

class CreateBattle extends CreateRecord
{
    protected static string $resource = BattleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! ($data['deferred_start'] ?? false)) {
            $data['opens_at'] = now();
        }

        $opensAt = Carbon::parse($data['opens_at']);
        $preset = $data['duration_preset'] ?? BattleDurationPreset::DEFAULT;
        $data['closes_at'] = BattleDurationPreset::closesAt($preset, $opensAt);

        unset($data['deferred_start'], $data['duration_preset']);

        return $data;
    }
}
