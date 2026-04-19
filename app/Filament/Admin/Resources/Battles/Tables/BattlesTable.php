<?php

namespace App\Filament\Admin\Resources\Battles\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BattlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'warning' => 'closed',
                        'primary' => 'settled',
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('side_a_label')->label('Side A'),
                TextColumn::make('side_b_label')->label('Side B'),
                TextColumn::make('total_pool')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('winning_side')->label('Winner')->badge(),
                TextColumn::make('closes_at')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('settled_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('closes_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
