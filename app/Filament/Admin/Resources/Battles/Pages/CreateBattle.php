<?php

namespace App\Filament\Admin\Resources\Battles\Pages;

use App\Filament\Admin\Resources\Battles\BattleResource;
use Filament\Resources\Pages\CreateRecord;

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

        unset($data['deferred_start']);

        return $data;
    }
}
