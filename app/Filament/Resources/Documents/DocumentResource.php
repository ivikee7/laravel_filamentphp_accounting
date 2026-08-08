<?php

namespace App\Filament\Resources\Documents;

use App\Filament\Resources\Documents\Pages\CreateDocument;
use App\Filament\Resources\Documents\Pages\EditDocument;
use App\Filament\Resources\Documents\Pages\ListDocuments;
use App\Filament\Resources\Documents\Pages\ViewDocument;
use App\Models\Contact;
use App\Models\Document;
use App\Models\TaxRate;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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

class DocumentResource extends Resource
{
    protected static ?string $model = Document::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')->schema([
                Select::make('type')->required()->options(['invoice' => 'Invoice', 'bill' => 'Bill']),
                Select::make('supply_scope')->required()->options([
                    'all' => 'All / Not specified',
                    'intra_state' => 'Intra-state',
                    'inter_state' => 'Inter-state',
                    'domestic' => 'Domestic',
                    'import' => 'Import',
                    'export' => 'Export',
                ])->default('all'),
                TextInput::make('number')->maxLength(50)->placeholder('Auto-generated if empty'),
                Select::make('contact_id')->label('Contact')
                    ->options(function (): array {
                        $tenant = Filament::getTenant();
                        if (! $tenant instanceof Team) {
                            return [];
                        }

                        return Contact::query()
                            ->where('team_id', $tenant->getKey())
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable(),
                TextInput::make('currency_code')->default('USD')->required()->maxLength(3),
            ])->columns(3),

            Section::make('Dates')->schema([
                DatePicker::make('issue_date')->required()->default(now()),
                DatePicker::make('due_date'),
                Textarea::make('notes')->rows(3)->columnSpanFull(),
            ])->columns(2),

            Section::make('Lines')->schema([
                Repeater::make('lines')
                    ->required()
                    ->minItems(1)
                    ->defaultItems(1)
                    ->schema([
                        TextInput::make('description')->required()->columnSpan(2),
                        TextInput::make('quantity')->numeric()->required()->minValue(0.0001)->default(1),
                        TextInput::make('unit_price')->numeric()->required()->minValue(0)->prefix('INR'),
                        Toggle::make('price_includes_tax')
                            ->label('Price Includes Tax')
                            ->default(function (): bool {
                                $tenant = Filament::getTenant();
                                if (! $tenant instanceof Team) {
                                    return false;
                                }

                                return (bool) $tenant->taxProfile?->prices_include_tax;
                            }),
                        Select::make('tax_treatment')
                            ->required()
                            ->options([
                                'taxable' => 'Taxable',
                                'zero_rated' => 'Zero-rated',
                                'exempt' => 'Exempt',
                            ])
                            ->default('taxable'),
                        Select::make('tax_rate_id')
                            ->label('Tax Rate')
                            ->options(function (): array {
                                $tenant = Filament::getTenant();
                                if (! $tenant instanceof Team) {
                                    return [];
                                }

                                return TaxRate::query()
                                    ->where('team_id', $tenant->getKey())
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (TaxRate $rate) => [
                                        $rate->getKey() => strtoupper($rate->tax_type).' - '.$rate->name.' ('.$rate->rate.'%)',
                                    ])->all();
                            })
                            ->searchable(),
                        TextInput::make('tax_rate')
                            ->label('Ad-hoc Tax Rate')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->suffix('%')
                            ->default(0)
                            ->helperText('Used when no tax rate is selected.'),
                    ])->columns(4)->columnSpanFull(),
            ])->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Document')->schema([
                TextEntry::make('type')->badge(),
                TextEntry::make('number'),
                TextEntry::make('contact.name')->label('Contact'),
                TextEntry::make('currency_code')->label('Currency'),
                TextEntry::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'issued' => 'info',
                        'partially_paid' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
            ])->columns(2),

            Section::make('Dates')->schema([
                TextEntry::make('issue_date')->date(),
                TextEntry::make('due_date')->date(),
                TextEntry::make('submitted_at')->since()->label('Submitted'),
                TextEntry::make('approved_at')->since()->label('Approved'),
            ])->columns(2),

            Section::make('Financials')->schema([
                TextEntry::make('subtotal_amount')->money('USD')->label('Subtotal'),
                TextEntry::make('tax_amount')->money('USD')->label('Tax'),
                TextEntry::make('total_amount')->money('USD')->label('Total'),
                TextEntry::make('paid_amount')->money('USD')->label('Paid'),
                TextEntry::make('balance_due')->money('USD')->label('Balance Due'),
            ])->columns(3),

            Section::make('Notes')->schema([
                TextEntry::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')->badge(),
                TextColumn::make('number')->searchable(),
                TextColumn::make('contact.name')->label('Contact')->searchable(),
                TextColumn::make('issue_date')->date()->sortable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'submitted' => 'warning',
                        'issued' => 'info',
                        'partially_paid' => 'warning',
                        'paid' => 'success',
                        'overdue' => 'danger',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('total_amount')->money('USD')->sortable(),
                TextColumn::make('balance_due')->money('USD'),
            ])
            ->filters([
                SelectFilter::make('type')->options(['invoice' => 'Invoice', 'bill' => 'Bill']),
                SelectFilter::make('status')->options([
                    'draft' => 'Draft',
                    'submitted' => 'Submitted',
                    'issued' => 'Issued',
                    'partially_paid' => 'Partially Paid',
                    'paid' => 'Paid',
                    'overdue' => 'Overdue',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->defaultSort('issue_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDocuments::route('/'),
            'create' => CreateDocument::route('/create'),
            'view' => ViewDocument::route('/{record}'),
            'edit' => EditDocument::route('/{record}/edit'),
        ];
    }
}
