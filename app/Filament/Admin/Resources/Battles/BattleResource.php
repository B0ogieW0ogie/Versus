<?php

namespace App\Filament\Admin\Resources\Battles;

use App\Filament\Admin\Resources\Battles\Pages\CreateBattle;
use App\Filament\Admin\Resources\Battles\Pages\EditBattle;
use App\Filament\Admin\Resources\Battles\Pages\ListBattles;
use App\Filament\Admin\Resources\Battles\Schemas\BattleForm;
use App\Filament\Admin\Resources\Battles\Tables\BattlesTable;
use App\Models\Battle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class BattleResource extends Resource
{
    protected static ?string $model = Battle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Баттлы';

    protected static ?string $modelLabel = 'Баттл';

    protected static ?string $pluralModelLabel = 'Баттлы';

    public static function form(Schema $schema): Schema
    {
        return BattleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BattlesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBattles::route('/'),
            'create' => CreateBattle::route('/create'),
            'edit' => EditBattle::route('/{record}/edit'),
        ];
    }
}
