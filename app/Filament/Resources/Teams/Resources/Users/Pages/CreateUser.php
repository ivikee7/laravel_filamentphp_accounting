<?php

namespace App\Filament\Resources\Teams\Resources\Users\Pages;

use App\Filament\Resources\Teams\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;
}
