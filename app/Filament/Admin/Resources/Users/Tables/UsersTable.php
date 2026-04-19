<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('balance')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('referral_code')->label('Ref code')->searchable()->copyable(),
                TextColumn::make('referrer.email')->label('Referred by')->toggleable(),
                IconColumn::make('is_admin')->boolean(),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('grant')
                    ->label('Grant tokens')
                    ->icon('heroicon-o-banknotes')
                    ->schema([
                        TextInput::make('amount')
                            ->numeric()
                            ->minValue(0.01)
                            ->required()
                            ->label('Amount'),
                        TextInput::make('note')
                            ->label('Note')
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
                            ->title('Tokens granted')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
