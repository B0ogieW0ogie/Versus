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
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get) {
                        if ($state !== null && blank($get('slug'))) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->required()
                    ->unique(ignoreRecord: true),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('side_a_label')
                    ->label('Side A')
                    ->required(),
                TextInput::make('side_b_label')
                    ->label('Side B')
                    ->required(),
                FileUpload::make('side_a_image')->image(),
                FileUpload::make('side_b_image')->image(),
                Select::make('status')
                    ->options([
                        Battle::STATUS_DRAFT => 'Draft',
                        Battle::STATUS_ACTIVE => 'Active',
                        Battle::STATUS_CLOSED => 'Closed',
                        Battle::STATUS_SETTLED => 'Settled',
                    ])
                    ->required()
                    ->default(Battle::STATUS_DRAFT),
                DateTimePicker::make('opens_at')->seconds(false),
                DateTimePicker::make('closes_at')->seconds(false),
                Select::make('winning_side')
                    ->options([
                        Battle::SIDE_A => 'Side A',
                        Battle::SIDE_B => 'Side B',
                    ])
                    ->disabled(),
                TextInput::make('total_pool')
                    ->numeric()
                    ->disabled()
                    ->default(0),
                DateTimePicker::make('settled_at')->disabled(),
            ]);
    }
}
