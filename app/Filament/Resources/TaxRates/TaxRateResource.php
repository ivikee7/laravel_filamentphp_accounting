<?php

namespace App\Filament\Resources\TaxRates;

use App\Filament\Resources\TaxRates\Pages\CreateTaxRate;
use App\Filament\Resources\TaxRates\Pages\EditTaxRate;
use App\Filament\Resources\TaxRates\Pages\ListTaxRates;
use App\Models\TaxRate;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TaxRateResource extends Resource
{
    protected static ?string $model = TaxRate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tax Rate Details')->schema([
                TextInput::make('name')->required()->maxLength(100),
                TextInput::make('code')->required()->maxLength(32),
                TextInput::make('rate')->numeric()->required()->minValue(0)->suffix('%'),
                DatePicker::make('effective_from')->required()->default(now()),
                DatePicker::make('effective_to'),
                Toggle::make('is_active')->default(true)->aboveLabel('Is Active')->hiddenLabel(),
            ])->columns(6)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tax Rate Details')->schema([
                TextEntry::make('name'),
                TextEntry::make('code'),
                TextEntry::make('rate')->suffix('%'),
                TextEntry::make('effective_from')->date(),
                TextEntry::make('effective_to')->date()->placeholder('No end date'),
                IconEntry::make('is_active')->boolean()->label('Active'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('code')->searchable(),
                TextColumn::make('rate')->suffix('%'),
                TextColumn::make('effective_from')->date(),
                TextColumn::make('effective_to')->date()->placeholder('—'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxRates::route('/'),
            'create' => CreateTaxRate::route('/create'),
            'edit' => EditTaxRate::route('/{record}/edit'),
        ];
    }
}
