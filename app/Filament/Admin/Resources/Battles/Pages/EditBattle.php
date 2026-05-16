<?php

namespace App\Filament\Admin\Resources\Battles\Pages;

use App\Actions\Battles\AddBattlePoolAction;
use App\Actions\Battles\SettleBattleAction;
use App\Filament\Admin\Resources\Battles\BattleResource;
use App\Models\Battle;
use App\Support\BattleDurationPreset;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditBattle extends EditRecord
{
    protected static string $resource = BattleResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['deferred_start'] = $this->record->opens_at?->isFuture() ?? false;
        $data['duration_preset'] = BattleDurationPreset::detect(
            $this->record->opens_at ?? $this->record->created_at,
            $this->record->closes_at,
        );

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! ($data['deferred_start'] ?? false)) {
            $data['opens_at'] = $this->record->opens_at?->isFuture()
                ? now()
                : ($this->record->opens_at ?? now());
        }

        $opensAt = Carbon::parse($data['opens_at']);
        $preset = $data['duration_preset'] ?? BattleDurationPreset::DEFAULT;
        $data['closes_at'] = BattleDurationPreset::closesAt($preset, $opensAt);

        unset($data['deferred_start'], $data['duration_preset']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addPool')
                ->label('Пополнить пул')
                ->icon('heroicon-o-banknotes')
                ->color('warning')
                ->visible(fn (Battle $record): bool => $record->status !== Battle::STATUS_SETTLED)
                ->form([
                    TextInput::make('amount')
                        ->label('Сумма')
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),
                    TextInput::make('note')
                        ->label('Комментарий')
                        ->maxLength(255),
                ])
                ->action(function (Battle $record, array $data, AddBattlePoolAction $add): void {
                    try {
                        $battle = $add($record, (float) $data['amount'], $data['note'] ?? null);

                        Notification::make()
                            ->title('Пул пополнен')
                            ->body('Новый банк: '.number_format((float) $battle->total_pool, 2, '.', ' '))
                            ->success()
                            ->send();
                    } catch (ValidationException $e) {
                        Notification::make()
                            ->title('Не удалось пополнить пул')
                            ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Не удалось пополнить пул')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();

                        return;
                    }

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]));
                }),
            Action::make('settle')
                ->label('Завершить сейчас')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (Battle $record) => $record->status !== Battle::STATUS_SETTLED)
                ->action(function (Battle $record, SettleBattleAction $settle): void {
                    try {
                        if ($record->status === Battle::STATUS_ACTIVE) {
                            $record->status = Battle::STATUS_CLOSED;
                            $record->save();
                        }

                        $settle($record);

                        Notification::make()
                            ->title('Баттл завершён')
                            ->success()
                            ->send();
                    } catch (Throwable $e) {
                        Notification::make()
                            ->title('Не удалось завершить баттл')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $record]));
                }),
            DeleteAction::make(),
        ];
    }
}
