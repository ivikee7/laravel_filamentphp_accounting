<?php

namespace App\Filament\Resources\Teams\RelationManagers;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OwnerRelationManager extends RelationManager
{
    protected static string $relationship = 'owner';

    protected static ?string $relatedResource = UserResource::class;

    protected static ?string $relationshipTitle = 'Owners';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->sortable(),
            ])
            ->bulkActions([])
            ->recordActions([])
            ->recordUrl(null)
            ->headerActions([
                Action::make('assignOwner')
                    ->label('Assign Owner')
                    ->form([
                        Select::make('user_id')
                            ->label('User')
                            ->options(UserResource::getModel()::pluck('name', 'id'))
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $this->transferOwnershipTo($data['user_id']);

                        Notification::make()
                            ->success()
                            ->title('Ownership Transferred')
                            ->send();
                    }),
            ]);
    }

    public function transferOwnershipTo($userId): void
    {
        $team = $this->getOwnerRecord();
        $team->owner()->associate($userId);
        $team->save();

        // Force the browser to refresh the current URL entirely
//        $this->redirect(request()->header('referer'));
    }

}
