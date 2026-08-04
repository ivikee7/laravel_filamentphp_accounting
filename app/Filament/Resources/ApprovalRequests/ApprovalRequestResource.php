<?php

namespace App\Filament\Resources\ApprovalRequests;

use App\Filament\Resources\ApprovalRequests\Pages\ListApprovalRequests;
use App\Models\ApprovalRequest;
use App\Models\Document;
use App\Models\Team;
use App\Services\Accounting\ApprovalService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;
use UnitEnum;

class ApprovalRequestResource extends Resource
{
    protected static ?string $model = ApprovalRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCheckBadge;

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
            Section::make('Approval Request')->schema([
                TextEntry::make('approvable_type')->label('Entity Type'),
                TextEntry::make('approvable_id')->label('Entity ID'),
                TextEntry::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextEntry::make('submitted_at')->since()->label('Submitted'),
                TextEntry::make('approved_at')->since()->label('Approved'),
                TextEntry::make('rejected_at')->since()->label('Rejected'),
                TextEntry::make('remarks'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('approvable_type')->label('Entity'),
                TextColumn::make('approvable_id')->label('ID'),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('submitted_at')->since()->label('Submitted'),
                TextColumn::make('approved_at')->since()->placeholder('—'),
                TextColumn::make('rejected_at')->since()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'pending' => 'Pending',
                    'approved' => 'Approved',
                    'rejected' => 'Rejected',
                ]),
            ])
            ->actions([
                Action::make('approve')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->color('success')
                    ->visible(fn (ApprovalRequest $record): bool => $record->status === 'pending' && $record->approvable_type === Document::class)
                    ->requiresConfirmation()
                    ->action(function (ApprovalRequest $record): void {
                        $tenant = Filament::getTenant();
                        if (! $tenant instanceof Team) {
                            throw new RuntimeException('Tenant is required.');
                        }

                        $document = Document::query()
                            ->where('team_id', $tenant->getKey())
                            ->whereKey($record->approvable_id)
                            ->firstOrFail();

                        app(ApprovalService::class)->approveDocument($document, $tenant, auth()->user());
                    }),
                Action::make('reject')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (ApprovalRequest $record): bool => $record->status === 'pending' && $record->approvable_type === Document::class)
                    ->requiresConfirmation()
                    ->action(function (ApprovalRequest $record): void {
                        $tenant = Filament::getTenant();
                        if (! $tenant instanceof Team) {
                            throw new RuntimeException('Tenant is required.');
                        }

                        $document = Document::query()
                            ->where('team_id', $tenant->getKey())
                            ->whereKey($record->approvable_id)
                            ->firstOrFail();

                        app(ApprovalService::class)->rejectDocument($document, $tenant, auth()->user());
                    }),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApprovalRequests::route('/'),
        ];
    }
}