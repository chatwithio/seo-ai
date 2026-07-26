<?php

namespace App\Filament\Clusters;

use BackedEnum;
use Filament\Clusters\Cluster;

class Settings extends Cluster
{
    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
}
