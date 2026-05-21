<?php

namespace App\Filament\Admin\Resources\Battles\Actions;

use App\Actions\Battles\GenerateDemoBattleAction;
use App\Filament\Admin\Resources\Battles\BattleResource;
use App\Models\Category;
use Filament\Actions\Action as FilamentAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

final class GenerateBattleAction
{
    public static function make(): FilamentAction
    {
        return FilamentAction::make('generateBattle')
            ->label('Сгенерировать баттл')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->modalHeading('Генерация демо-баттла')
            ->modalDescription('Создаёт активный баттл с картинками-заглушками (градиент + название стороны).')
            ->schema([
                CheckboxList::make('placement')
                    ->label('Показ на главной')
                    ->options([
                        'sponsored' => 'Спонсорский слайдер',
                        'category' => 'Рельса категории',
                        'hot' => 'HOT (высокий пул)',
                    ])
                    ->columns(1)
                    ->required()
                    ->live(),
                TextInput::make('sponsor_handle')
                    ->label('Спонсор')
                    ->prefix('@')
                    ->placeholder('Apple')
                    ->visible(fn (callable $get): bool => in_array('sponsored', $get('placement') ?? [], true)),
                Select::make('category_id')
                    ->label('Категория')
                    ->options(fn () => Category::query()->orderBy('sort_order')->pluck('name_en', 'id'))
                    ->searchable()
                    ->placeholder('Случайная категория')
                    ->visible(fn (callable $get): bool => in_array('category', $get('placement') ?? [], true)),
                TextInput::make('hot_pool')
                    ->label('Начальный пул для HOT')
                    ->numeric()
                    ->minValue(1)
                    ->placeholder('Случайно 150 000 – 1 500 000')
                    ->visible(fn (callable $get): bool => in_array('hot', $get('placement') ?? [], true)),
            ])
            ->action(function (array $data, GenerateDemoBattleAction $generate): void {
                try {
                    $battle = $generate($data);

                    Notification::make()
                        ->title('Баттл создан')
                        ->body($battle->title)
                        ->success()
                        ->actions([
                            Action::make('edit')
                                ->label('Открыть')
                                ->url(BattleResource::getUrl('edit', ['record' => $battle])),
                        ])
                        ->send();
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('Не удалось создать баттл')
                        ->body(collect($e->errors())->flatten()->first() ?? $e->getMessage())
                        ->danger()
                        ->send();
                } catch (Throwable $e) {
                    Notification::make()
                        ->title('Не удалось создать баттл')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
