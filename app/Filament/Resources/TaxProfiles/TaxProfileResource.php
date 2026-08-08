<?php

namespace App\Filament\Resources\TaxProfiles;

use App\Filament\Resources\TaxProfiles\Pages\EditTaxProfile;
use App\Filament\Resources\TaxProfiles\Pages\ListTaxProfiles;
use App\Models\TaxProfile;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class TaxProfileResource extends Resource
{
    protected static ?string $model = TaxProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    protected static ?string $navigationLabel = 'Tax Settings';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('GST / VAT Configuration')->schema([
                TextInput::make('country_code')->required()->minLength(2)->maxLength(2),
                TextInput::make('currency_code')->required()->minLength(3)->maxLength(3),
                TextInput::make('tax_precision')->required()->numeric()->minValue(0)->maxValue(4)->default(2),
                Select::make('rounding_mode')
                    ->required()
                    ->options([
                        'half_up' => 'Half up',
                        'half_down' => 'Half down',
                        'half_even' => 'Half even',
                        'floor' => 'Floor',
                        'ceil' => 'Ceil',
                    ])
                    ->default('half_up'),
                Toggle::make('prices_include_tax')
                    ->label('Product prices include tax by default')
                    ->default(false),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('country_code')->label('Country'),
                TextColumn::make('currency_code')->label('Currency'),
                TextColumn::make('tax_precision'),
                TextColumn::make('rounding_mode')->badge(),
                IconColumn::make('prices_include_tax')->label('Inclusive Pricing')->boolean(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxProfiles::route('/'),
            'edit' => EditTaxProfile::route('/{record}/edit'),
        ];
    }
}
