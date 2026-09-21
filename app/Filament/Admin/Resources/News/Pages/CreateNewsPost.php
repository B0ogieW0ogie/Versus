<?php

namespace App\Filament\Admin\Resources\News\Pages;

use App\Filament\Admin\Resources\News\NewsPostResource;
use App\Models\FeedPost;
use Filament\Resources\Pages\CreateRecord;

class CreateNewsPost extends CreateRecord
{
    protected static string $resource = NewsPostResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ['type' => FeedPost::TYPE_VERSUS_NEWS, 'user_id' => null] + $data;
    }
}
