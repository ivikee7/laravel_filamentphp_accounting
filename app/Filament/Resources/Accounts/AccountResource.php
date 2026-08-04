<?php

namespace App\Filament\Resources\Accounts;

use App\Filament\Resources\Accounts\Pages\CreateAccount;
use App\Filament\Resources\Accounts\Pages\EditAccount;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Filament\Resources\Accounts\Pages\ViewAccount;
use App\Models\Account;
use BackedEnum;
use Filament\Forms\Components\Select;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Details')->schema([
                TextInput::make('code')->required()->maxLength(30)->unique(ignoreRecord: true),
                TextInput::make('name')->required()->maxLength(120),
                Select::make('type')->required()->options([
                    'asset' => 'Asset',
                    'liability' => 'Liability',
                    'equity' => 'Equity',
                    'income' => 'Income',
                    'expense' => 'Expense',
                ]),
                Select::make('normal_balance')->required()->options([
                    'debit' => 'Debit',
                    'credit' => 'Credit',
                ]),
                Toggle::make('is_active')
                    ->default(true)
                    ->hiddenLabel()
                    ->aboveLabel('Is Active'),
            ])->columns(5)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account Details')->schema([
                TextEntry::make('code'),
                TextEntry::make('name'),
                TextEntry::make('type')->badge(),
                TextEntry::make('normal_balance')->badge(),
                IconEntry::make('is_active')->boolean()->label('Active'),
                TextEntry::make('created_at')->since(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('type')->badge(),
                TextColumn::make('normal_balance')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->options([
                    'asset' => 'Asset',
                    'liability' => 'Liability',
                    'equity' => 'Equity',
                    'income' => 'Income',
                    'expense' => 'Expense',
                ]),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->defaultSort('code');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAccounts::route('/'),
            'create' => CreateAccount::route('/create'),
            'view' => ViewAccount::route('/{record}'),
            'edit' => EditAccount::route('/{record}/edit'),
        ];
    }
}
