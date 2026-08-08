<?php

namespace App\Filament\Resources\Contacts;

use App\Filament\Resources\Contacts\Pages\CreateContact;
use App\Filament\Resources\Contacts\Pages\EditContact;
use App\Filament\Resources\Contacts\Pages\ListContacts;
use App\Filament\Resources\Contacts\Pages\ViewContact;
use App\Models\Contact;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class ContactResource extends Resource
{
    protected static ?string $model = Contact::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact Details')->schema([
                Select::make('type')->required()->options(['customer' => 'Customer', 'vendor' => 'Vendor']),
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('email')->email()->maxLength(120),
                TextInput::make('phone')->maxLength(30),
                TextInput::make('tax_id')->label('Tax ID')->maxLength(60),
            ])->columnSpanFull()->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contact Details')->schema([
                TextEntry::make('type')->badge(),
                TextEntry::make('name'),
                TextEntry::make('email'),
                TextEntry::make('phone'),
                TextEntry::make('tax_id')->label('Tax ID'),
                TextEntry::make('created_at')->since(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('tax_id')->label('Tax ID'),
            ])
            ->filters([
                SelectFilter::make('type')->options(['customer' => 'Customer', 'vendor' => 'Vendor']),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContacts::route('/'),
            'create' => CreateContact::route('/create'),
            'view' => ViewContact::route('/{record}'),
            'edit' => EditContact::route('/{record}/edit'),
        ];
    }
}
