<?php

namespace App\Filament\Admin\Resources\Battles\Pages;

use App\Filament\Admin\Resources\Battles\Actions\GenerateBattleAction;
use App\Filament\Admin\Resources\Battles\BattleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBattles extends ListRecords
{
    protected static string $resource = BattleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            GenerateBattleAction::make(),
            CreateAction::make(),
        ];
    }
}
