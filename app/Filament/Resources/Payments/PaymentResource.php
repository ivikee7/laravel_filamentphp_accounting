<?php

namespace App\Filament\Resources\Payments;

use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Models\Document;
use App\Models\Payment;
use App\Models\Team;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('team_id', filament()->getTenant()?->getKey());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Details')->schema([
                Select::make('document_id')->required()->label('Document')
                    ->options(function (): array {
                        $tenant = Filament::getTenant();
                        if (! $tenant instanceof Team) {
                            return [];
                        }

                        return Document::query()
                            ->where('team_id', $tenant->getKey())
                            ->whereIn('status', ['issued', 'partially_paid', 'overdue'])
                            ->orderByDesc('issue_date')
                            ->get()
                            ->mapWithKeys(fn (Document $doc) => [
                                $doc->getKey() => strtoupper($doc->type) . ' ' . $doc->number . ' — Due ' . number_format((float) $doc->balance_due, 2),
                            ])->all();
                    })
                    ->searchable(),
                DatePicker::make('payment_date')->required()->default(now()),
                TextInput::make('amount')->required()->numeric()->minValue(0.01)->prefix('$'),
                Select::make('method')->required()->default('bank_transfer')->options([
                    'cash' => 'Cash',
                    'bank_transfer' => 'Bank Transfer',
                    'card' => 'Card',
                    'upi' => 'UPI',
                    'cheque' => 'Cheque',
                ]),
                TextInput::make('reference')->maxLength(100),
                Textarea::make('notes')->rows(2)->columnSpanFull(),
            ])->columns(2)->columnSpanFull(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Payment Details')->schema([
                TextEntry::make('document.number')->label('Document'),
                TextEntry::make('document.type')->badge()->label('Type'),
                TextEntry::make('payment_date')->date(),
                TextEntry::make('amount')->money('USD'),
                TextEntry::make('method')->badge(),
                TextEntry::make('reference'),
                TextEntry::make('createdBy.name')->label('Recorded By'),
                TextEntry::make('created_at')->since()->label('Recorded At'),
            ])->columns(2),
            Section::make('Notes')->schema([
                TextEntry::make('notes')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document.type')->badge()->label('Type'),
                TextColumn::make('document.number')->label('Document')->searchable(),
                TextColumn::make('payment_date')->date()->sortable(),
                TextColumn::make('amount')->money('USD')->sortable(),
                TextColumn::make('method')->badge(),
                TextColumn::make('reference'),
            ])
            ->filters([
                SelectFilter::make('method')->options([
                    'cash' => 'Cash',
                    'bank_transfer' => 'Bank Transfer',
                    'card' => 'Card',
                    'upi' => 'UPI',
                    'cheque' => 'Cheque',
                ]),
            ])
            ->defaultSort('payment_date', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'create' => CreatePayment::route('/create'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }
}
