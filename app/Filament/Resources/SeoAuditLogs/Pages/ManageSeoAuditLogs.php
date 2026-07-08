<?php

namespace App\Filament\Resources\SeoAuditLogs\Pages;

use App\Filament\Resources\SeoAuditLogs\SeoAuditLogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageSeoAuditLogs extends ManageRecords
{
    protected static string $resource = SeoAuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
