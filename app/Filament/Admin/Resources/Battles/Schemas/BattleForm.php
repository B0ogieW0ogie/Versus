<?php

namespace App\Filament\Admin\Resources\Battles\Schemas;

use App\Models\Battle;
use App\Models\Category;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
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
                TextInput::make('side_a_subtitle')
                    ->label('Подзаголовок A')
                    ->maxLength(120),
                TextInput::make('side_b_subtitle')
                    ->label('Подзаголовок B')
                    ->maxLength(120),
                FileUpload::make('side_a_image')
                    ->label('Изображение стороны A')
                    ->image()
                    ->disk('public')
                    ->directory('battles/sides')
                    ->visibility('public')
                    ->formatStateUsing(fn (?string $state): ?string => self::urlToDiskPath($state))
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::diskPathToUrl($state)),
                FileUpload::make('side_b_image')
                    ->label('Изображение стороны B')
                    ->image()
                    ->disk('public')
                    ->directory('battles/sides')
                    ->visibility('public')
                    ->formatStateUsing(fn (?string $state): ?string => self::urlToDiskPath($state))
                    ->dehydrateStateUsing(fn (?string $state): ?string => self::diskPathToUrl($state)),
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
                Select::make('category_id')
                    ->label('Категория')
                    ->options(fn () => Category::query()->orderBy('sort_order')->pluck('name_en', 'id'))
                    ->searchable()
                    ->nullable(),
                Toggle::make('is_sponsored')
                    ->label('Спонсорский слайд на главной')
                    ->live()
                    ->helperText('Отмеченные баттлы попадают в слайдер на главной странице.'),
                TextInput::make('sponsor_handle')
                    ->label('Хендл спонсора')
                    ->prefix('@')
                    ->placeholder('brand')
                    ->visible(fn (callable $get) => (bool) $get('is_sponsored')),
                TextInput::make('total_pool')
                    ->label('Общий банк')
                    ->numeric()
                    ->disabled()
                    ->default(0),
                DateTimePicker::make('settled_at')->label('Завершён в')->disabled(),
            ]);
    }

    /**
     * Convert a value stored on the model (public-disk URL, external URL, root-relative public path,
     * or already disk-relative path) into a path Filament's FileUpload can preview from the `public` disk.
     * Returns null for values that cannot be served via the `public` disk (external URLs, /images/...).
     */
    private static function urlToDiskPath(?string $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        $publicPrefix = Storage::disk('public')->url('');
        if (str_starts_with($state, $publicPrefix)) {
            return ltrim(substr($state, strlen($publicPrefix)), '/');
        }

        if (str_starts_with($state, 'http://') || str_starts_with($state, 'https://') || str_starts_with($state, '/')) {
            return null;
        }

        return $state;
    }

    /**
     * Convert the FileUpload state (a disk-relative path produced by an upload, or an untouched
     * existing value) into the renderable URL we persist in `side_*_image`.
     */
    private static function diskPathToUrl(?string $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        if (str_starts_with($state, 'http://') || str_starts_with($state, 'https://') || str_starts_with($state, '/')) {
            return $state;
        }

        return Storage::disk('public')->url($state);
    }
}
