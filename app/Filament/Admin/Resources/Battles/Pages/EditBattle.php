<?php

namespace App\Filament\Admin\Resources\Battles\Pages;

use App\Actions\Battles\SettleBattleAction;
use App\Filament\Admin\Resources\Battles\Actions\AddPoolRecordAction;
use App\Filament\Admin\Resources\Battles\BattleResource;
use App\Models\Battle;
use App\Support\BattleDurationPreset;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Carbon;
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
            AddPoolRecordAction::make(
                fn (Battle $battle) => $this->redirect(static::getResource()::getUrl('edit', ['record' => $battle])),
            ),
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
