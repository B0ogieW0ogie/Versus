<?php

namespace App\Filament\Admin\Resources\Categories\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('slug')->label('Слаг')->searchable(),
                TextColumn::make('name_en')->label('EN')->searchable(),
                TextColumn::make('name_ru')->label('RU')->searchable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
