<?php

namespace App\Filament\Admin\Resources\Battles\Tables;

use App\Filament\Admin\Resources\Battles\Actions\AddPoolRecordAction;
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
                TextColumn::make('title')->label('Название')->searchable(),
                TextColumn::make('slug')->label('Слаг')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => [
                        'draft' => 'Черновик',
                        'active' => 'Активен',
                        'closed' => 'Закрыт',
                        'settled' => 'Завершён',
                    ][$state] ?? $state)
                    ->colors([
                        'gray' => 'draft',
                        'success' => 'active',
                        'warning' => 'closed',
                        'primary' => 'settled',
                    ])
                    ->searchable()
                    ->sortable(),
                TextColumn::make('side_a_label')->label('Сторона A'),
                TextColumn::make('side_b_label')->label('Сторона B'),
                TextColumn::make('total_pool')->label('Банк')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('winning_side')->label('Победитель')->badge(),
                TextColumn::make('closes_at')->label('Закрытие')->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('settled_at')->label('Завершён')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('closes_at', 'desc')
            ->recordActions([
                AddPoolRecordAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
