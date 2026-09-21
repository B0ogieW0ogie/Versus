<?php

namespace App\Filament\Admin\Resources\News\Pages;

use App\Filament\Admin\Resources\News\NewsPostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListNewsPosts extends ListRecords
{
    protected static string $resource = NewsPostResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
