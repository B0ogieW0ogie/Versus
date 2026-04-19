<?php

namespace App\Filament\Admin\Resources\Transactions\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->dateTime('Y-m-d H:i:s')->sortable(),
                TextColumn::make('user.name')
                    ->label('User')
                    ->default('— system —')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('amount')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('balance_after')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('battle_id')->label('Battle')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('type')->options([
                    'signup_bonus' => 'Signup bonus',
                    'vote_stake' => 'Vote stake',
                    'vote_payout' => 'Vote payout',
                    'referral_reward' => 'Referral reward',
                    'project_fee' => 'Project fee',
                    'burn' => 'Burn',
                    'reward_pool_credit' => 'Reward pool credit',
                    'reward_pool_debit' => 'Reward pool debit',
                    'admin_grant' => 'Admin grant',
                ]),
            ]);
    }
}
