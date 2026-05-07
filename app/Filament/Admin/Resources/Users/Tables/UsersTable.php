<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Actions\Users\SetAdminStatusAction;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),
                TextColumn::make('name')->label('Имя')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('balance')->label('Баланс')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('referral_code')->label('Реф. код')->searchable()->copyable(),
                TextColumn::make('referrer.email')->label('Пригласил')->toggleable(),
                IconColumn::make('is_admin')->label('Админ')->boolean(),
                TextColumn::make('created_at')->label('Создан')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('grant')
                    ->label('Начислить токены')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->label('Сумма'),
                        TextInput::make('note')
                            ->label('Комментарий')
                            ->maxLength(255),
                    ])
                    ->action(function (User $record, array $data): void {
                        DB::transaction(function () use ($record, $data) {
                            $amount = (float) $data['amount'];
                            $record->balance = (float) $record->balance + $amount;
                            $record->save();

                            Transaction::create([
                                'user_id' => $record->id,
                                'type' => Transaction::TYPE_ADMIN_GRANT,
                                'amount' => $amount,
                                'balance_after' => $record->balance,
                                'meta' => isset($data['note']) && $data['note'] !== ''
                                    ? ['note' => $data['note']]
                                    : null,
                            ]);
                        });

                        Notification::make()
                            ->title('Токены начислены')
                            ->success()
                            ->send();
                    }),
                Action::make('makeAdmin')
                    ->label('Сделать админом')
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn (User $record): bool => ! $record->is_admin)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        app(SetAdminStatusAction::class)($record, true);

                        Notification::make()
                            ->title('Права администратора выданы')
                            ->success()
                            ->send();
                    }),
                Action::make('removeAdmin')
                    ->label('Убрать админа')
                    ->icon('heroicon-o-shield-exclamation')
                    ->color('danger')
                    ->visible(fn (User $record): bool => $record->is_admin)
                    ->disabled(fn (User $record): bool => Auth::id() === $record->id)
                    ->tooltip(fn (User $record): ?string => Auth::id() === $record->id
                        ? 'Нельзя снять права с текущего пользователя'
                        : null)
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        app(SetAdminStatusAction::class)($record, false);

                        Notification::make()
                            ->title('Права администратора сняты')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
