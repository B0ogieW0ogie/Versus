<?php

namespace App\Filament\Admin\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name_en')
                ->label('Название (EN)')
                ->required()
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                    if ($state !== null && blank($get('slug'))) {
                        $set('slug', Str::slug($state));
                    }
                }),
            TextInput::make('name_ru')
                ->label('Название (RU)')
                ->required(),
            TextInput::make('slug')
                ->label('Слаг')
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('sort_order')
                ->label('Порядок')
                ->numeric()
                ->default(0)
                ->required(),
        ]);
    }
}
