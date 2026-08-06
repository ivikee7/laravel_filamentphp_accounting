<?php

namespace App\Filament\Resources\Users\Resources\Teams\Pages;

use App\Filament\Resources\Users\Resources\Teams\TeamResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTeam extends CreateRecord
{
    protected static string $resource = TeamResource::class;
}
