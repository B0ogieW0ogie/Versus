<?php

namespace App\Filament\Admin\Resources\Battles\Schemas;

use App\Models\Battle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BattleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->label('Название')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                        if ($state !== null && blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Слаг')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->label('Описание')
                    ->columnSpanFull(),
                TextInput::make('side_a_label')
                    ->label('Сторона A')
                    ->required(),
                TextInput::make('side_b_label')
                    ->label('Сторона B')
                    ->required(),
                FileUpload::make('side_a_image')->label('Изображение стороны A')->image(),
                FileUpload::make('side_b_image')->label('Изображение стороны B')->image(),
                Select::make('status')
                    ->label('Статус')
                    ->options([
                        Battle::STATUS_DRAFT => 'Черновик',
                        Battle::STATUS_ACTIVE => 'Активен',
                        Battle::STATUS_CLOSED => 'Закрыт',
                        Battle::STATUS_SETTLED => 'Завершён',
                    ])
                    ->required()
                    ->default(Battle::STATUS_DRAFT),
                DateTimePicker::make('opens_at')->label('Открытие')->seconds(false),
                DateTimePicker::make('closes_at')->label('Закрытие')->seconds(false),
                Select::make('winning_side')
                    ->label('Победившая сторона')
                    ->options([
                        Battle::SIDE_A => 'Сторона A',
                        Battle::SIDE_B => 'Сторона B',
                    ])
                    ->disabled(),
                TextInput::make('total_pool')
                    ->label('Общий банк')
                    ->numeric()
                    ->disabled()
                    ->default(0),
                DateTimePicker::make('settled_at')->label('Завершён в')->disabled(),
            ]);
    }
}
