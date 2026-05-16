<?php

namespace App\Filament\Admin\Resources\Transactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        $typeLabels = [
            'signup_bonus' => 'Бонус за регистрацию',
            'vote_stake' => 'Ставка',
            'vote_payout' => 'Выплата выигрыша',
            'referral_reward' => 'Реферальная награда',
            'project_fee' => 'Комиссия проекта',
            'burn' => 'Сжигание',
            'reward_pool_credit' => 'Пополнение призового фонда',
            'reward_pool_debit' => 'Списание из призового фонда',
            'admin_grant' => 'Начисление администратором',
            'battle_pool_credit' => 'Пополнение пула баттла',
        ];

        return $table
            ->columns([
                TextColumn::make('created_at')->label('Дата')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('user.name')
                    ->label('Пользователь')
                    ->default('— система —')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $typeLabels[$state] ?? $state)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')->label('Сумма')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('balance_after')->label('Баланс после')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('battle_id')->label('Баттл')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->label('Тип')->options($typeLabels),
            ]);
    }
}
