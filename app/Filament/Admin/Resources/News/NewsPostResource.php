<?php

namespace App\Filament\Admin\Resources\News;

use App\Filament\Admin\Resources\News\Pages\CreateNewsPost;
use App\Filament\Admin\Resources\News\Pages\EditNewsPost;
use App\Filament\Admin\Resources\News\Pages\ListNewsPosts;
use App\Models\FeedPost;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Official VERSUS announcements shown in the News Feed: features, updates, rule changes. */
class NewsPostResource extends Resource
{
    protected static ?string $model = FeedPost::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?string $navigationLabel = 'Новости VERSUS';

    protected static ?string $modelLabel = 'Новость';

    protected static ?string $pluralModelLabel = 'Новости VERSUS';

    protected static ?string $slug = 'news';

    /** Only official news: other feed_posts rows are statuses, achievements and Top moves. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('type', FeedPost::TYPE_VERSUS_NEWS);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Заголовок')
                ->helperText('Вторая строка в ленте, например «Добавлены новые категории Challenges».')
                ->required()
                ->maxLength(200),
            Textarea::make('body')
                ->label('Подробности (необязательно)')
                ->rows(4),
            DateTimePicker::make('published_at')
                ->label('Дата публикации')
                ->helperText('Новость появится в ленте с этого момента.')
                ->default(now())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Заголовок')->searchable()->wrap(),
                TextColumn::make('published_at')->label('Опубликовано')->dateTime()->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListNewsPosts::route('/'),
            'create' => CreateNewsPost::route('/create'),
            'edit' => EditNewsPost::route('/{record}/edit'),
        ];
    }
}
