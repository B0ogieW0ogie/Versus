<?php

namespace App\Filament\Admin\Resources\Categories\Tables;

use App\Models\Category;
use Filament\Actions\DeleteAction;
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
                TextColumn::make('battles_count')->label('Батлы')->counts('battles')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription(fn (Category $record): string => self::deleteWarning($record)),
            ]);
    }

    /**
     * The battles.category_id foreign key is nullOnDelete, so deleting a category
     * silently un-categorises its battles rather than failing.
     */
    private static function deleteWarning(Category $record): string
    {
        $battlesCount = $record->battles()->count();

        if ($battlesCount === 0) {
            return 'Это действие необратимо.';
        }

        return "Батлов в этой категории: {$battlesCount}. Они не будут удалены, но останутся без категории и исчезнут из подборок на главной. Это действие необратимо.";
    }
}
