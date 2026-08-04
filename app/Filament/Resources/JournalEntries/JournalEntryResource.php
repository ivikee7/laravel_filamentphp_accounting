<?php

namespace App\Filament\Resources\JournalEntries;

use App\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Models\JournalEntry;
use BackedEnum;
use Filament\Infolists\Components\RepeatableEntry;
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

class JournalEntryResource extends Resource
{
    protected static ?string $model = JournalEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Journal Entry')->schema([
                TextEntry::make('entry_no')->label('Entry #'),
                TextEntry::make('entry_date')->date(),
                TextEntry::make('description'),
                TextEntry::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'posted' => 'success',
                        'draft' => 'gray',
                        default => 'gray',
                    }),
                TextEntry::make('debit_total')->money('USD')->label('Total Debit'),
                TextEntry::make('credit_total')->money('USD')->label('Total Credit'),
                TextEntry::make('fiscalPeriod.name')->label('Fiscal Period'),
                TextEntry::make('createdBy.name')->label('Created By'),
                TextEntry::make('created_at')->since(),
            ])->columns(2),

            Section::make('Journal Lines')->schema([
                RepeatableEntry::make('lines')->schema([
                    TextEntry::make('account.code')->label('Code'),
                    TextEntry::make('account.name')->label('Account'),
                    TextEntry::make('description'),
                    TextEntry::make('debit')->money('USD'),
                    TextEntry::make('credit')->money('USD'),
                ])->columns(5),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_no')->label('Entry #')->searchable(),
                TextColumn::make('entry_date')->date()->sortable(),
                TextColumn::make('description')->limit(60),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'posted' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('debit_total')->money('USD'),
                TextColumn::make('credit_total')->money('USD'),
                TextColumn::make('fiscalPeriod.name')->label('Period'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['posted' => 'Posted', 'draft' => 'Draft']),
            ])
            ->defaultSort('entry_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListJournalEntries::route('/'),
            'view' => ViewJournalEntry::route('/{record}'),
        ];
    }
}