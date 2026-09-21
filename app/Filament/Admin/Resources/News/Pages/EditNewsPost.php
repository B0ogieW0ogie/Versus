<?php

namespace App\Filament\Admin\Resources\News\Pages;

use App\Filament\Admin\Resources\News\NewsPostResource;
use Filament\Resources\Pages\EditRecord;

class EditNewsPost extends EditRecord
{
    protected static string $resource = NewsPostResource::class;
}
