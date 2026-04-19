<?php

namespace App\Filament\Admin\Resources\Battles\Pages;

use App\Actions\Battles\SettleBattleAction;
use App\Filament\Admin\Resources\Battles\BattleResource;
use App\Models\Battle;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditBattle extends EditRecord
{
    protected static string $resource = BattleResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
