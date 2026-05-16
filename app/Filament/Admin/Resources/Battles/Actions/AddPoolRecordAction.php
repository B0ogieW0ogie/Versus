<?php

namespace App\Filament\Admin\Resources\Battles\Actions;

use App\Actions\Battles\AddBattlePoolAction;
use App\Models\Battle;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

final class AddPoolRecordAction
{
    /**
     * @param  (callable(Battle): void)|null  $afterSuccess
     */
    public static function make(?callable $afterSuccess = null): Action
    {
        return Action::make('addPool')
            ->label('Пополнить пул')
            ->icon('heroicon-o-banknotes')
            ->color('warning')
            ->visible(fn (Battle $record): bool => $record->status !== Battle::STATUS_SETTLED)
            ->schema([
                TextInput::make('amount')
                    ->label('Сумма')
                    ->numeric()
                    ->minValue(0.01)
                    ->required(),
                TextInput::make('note')
                    ->label('Комментарий')
                    ->maxLength(255),
            ])
            ->action(function (Battle $record, array $data, AddBattlePoolAction $add) use ($afterSuccess): void {
                $battle = self::run($record, $data, $add);

                if ($battle !== null && $afterSuccess !== null) {
                    $afterSuccess($battle);
                }
            });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function run(Battle $record, array $data, AddBattlePoolAction $add): ?Battle
    {
        try {
            $battle = $add($record, (float) $data['amount'], $data['note'] ?? null);

            Notification::make()
                ->title('Пул пополнен')
                ->body('Новый банк: '.number_format((float) $battle->total_pool, 2, '.', ' '))
                ->success()
                ->send();

            return $battle;
        } catch (ValidationException $e) {
            Notification::make()
                ->title('Не удалось пополнить пул')
                ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                ->danger()
                ->send();
        } catch (Throwable $e) {
            Notification::make()
                ->title('Не удалось пополнить пул')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }

        return null;
    }
}
