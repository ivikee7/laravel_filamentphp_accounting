<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use BackedEnum;
use Filament\Infolists\Components\KeyValueEntry;
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

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

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
            Section::make('Audit Event')->schema([
                TextEntry::make('event_type')->badge(),
                TextEntry::make('entity_type')->label('Entity'),
                TextEntry::make('entity_id')->label('Entity ID'),
                TextEntry::make('actor.email')->label('Actor'),
                TextEntry::make('created_at')->since()->label('Timestamp'),
                TextEntry::make('checksum')->limit(32)->label('Checksum'),
            ])->columns(2),
            Section::make('Payload')->schema([
                KeyValueEntry::make('payload')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->since()->sortable(),
                TextColumn::make('event_type')->badge()->searchable(),
                TextColumn::make('entity_type')->label('Entity'),
                TextColumn::make('entity_id')->label('ID'),
                TextColumn::make('actor.email')->label('Actor')->searchable(),
                TextColumn::make('checksum')->limit(16),
            ])
            ->filters([
                SelectFilter::make('event_type')
                    ->options([
                        'document.created' => 'Document Created',
                        'document.updated' => 'Document Updated',
                        'document.submitted' => 'Document Submitted',
                        'document.approved' => 'Document Approved',
                        'document.rejected' => 'Document Rejected',
                        'document.posted' => 'Document Posted',
                        'payment.recorded' => 'Payment Recorded',
                        'journal.posted' => 'Journal Posted',
                    ]),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
        ];
    }
}
