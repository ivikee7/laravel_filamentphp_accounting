<?php

namespace App\Filament\Resources\FiscalPeriods;

use App\Filament\Resources\FiscalPeriods\Pages\CreateFiscalPeriod;
use App\Filament\Resources\FiscalPeriods\Pages\EditFiscalPeriod;
use App\Filament\Resources\FiscalPeriods\Pages\ListFiscalPeriods;
use App\Models\FiscalPeriod;
use App\Models\Team;
use App\Services\Accounting\FiscalPeriodService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
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
use RuntimeException;
use UnitEnum;

class FiscalPeriodResource extends Resource
{
    protected static ?string $model = FiscalPeriod::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Compliance';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fiscal Period')->schema([
                TextInput::make('name')->required()->maxLength(40),
                DatePicker::make('start_date')->required(),
                DatePicker::make('end_date')->required(),
            ])->columns(3)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Fiscal Period')->schema([
                TextEntry::make('name'),
                TextEntry::make('start_date')->date(),
                TextEntry::make('end_date')->date(),
                IconEntry::make('is_closed')->boolean()->label('Closed'),
                TextEntry::make('closed_at')->since()->label('Closed At'),
                TextEntry::make('closedBy.name')->label('Closed By'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('start_date')->date(),
                TextColumn::make('end_date')->date(),
                IconColumn::make('is_closed')->boolean()->label('Closed'),
                TextColumn::make('closed_at')->since()->placeholder('—'),
            ])
            ->filters([
                TernaryFilter::make('is_closed')->label('Closed'),
            ])
            ->actions([
                Action::make('close')
                    ->icon(Heroicon::OutlinedLockClosed)
                    ->color('danger')
                    ->visible(fn (FiscalPeriod $record): bool => ! $record->is_closed)
                    ->requiresConfirmation()
                    ->action(function (FiscalPeriod $record): void {
                        $tenant = Filament::getTenant();
                        if (! $tenant instanceof Team) {
                            throw new RuntimeException('Tenant is required.');
                        }
                        app(FiscalPeriodService::class)->close($record, auth()->user());
                    }),
                Action::make('reopen')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->color('warning')
                    ->visible(fn (FiscalPeriod $record): bool => $record->is_closed)
                    ->requiresConfirmation()
                    ->action(fn (FiscalPeriod $record) => app(FiscalPeriodService::class)->reopen($record)),
            ])
            ->defaultSort('start_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFiscalPeriods::route('/'),
            'create' => CreateFiscalPeriod::route('/create'),
            'edit' => EditFiscalPeriod::route('/{record}/edit'),
        ];
    }
}
