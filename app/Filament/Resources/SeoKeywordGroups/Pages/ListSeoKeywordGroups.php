<?php

namespace App\Filament\Resources\SeoKeywordGroups\Pages;

use App\Filament\Resources\SeoKeywordGroups\SeoKeywordGroupResource;
use App\Models\GscSite;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;

class ListSeoKeywordGroups extends ListRecords
{
    protected static string $resource = SeoKeywordGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('autoGroupKeywords')
                ->label('Auto Group Keywords')
                ->icon('heroicon-o-squares-plus')
                ->color('primary')
                ->modalHeading('Auto Group Keywords')
                ->modalDescription('Choose a site and the maximum number of keywords the AI should organize in this batch.')
                ->form([
                    Select::make('site_id')
                        ->label('Site')
                        ->options(fn () => GscSite::query()
                            ->where('user_id', auth()->id())
                            ->orderBy('site_url')
                            ->pluck('site_url', 'id'))
                        ->searchable()
                        ->required(),
                    TextInput::make('limit')
                        ->label('Keywords per AI batch')
                        ->helperText('The AI will organize up to this many ungrouped keywords at one time.')
                        ->numeric()
                        ->default(50)
                        ->minValue(1)
                        ->maxValue(200)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $siteId = (int) $data['site_id'];
                    $site = GscSite::query()
                        ->where('user_id', auth()->id())
                        ->find($siteId);

                    if (! $site) {
                        Notification::make()
                            ->title('Site not found')
                            ->body('Choose one of your connected sites and try again.')
                            ->danger()
                            ->send();

                        return;
                    }

                    if (Cache::has("seo:group-keywords:lock:site:{$siteId}")) {
                        Notification::make()
                            ->title('Grouping is already running')
                            ->body('You can follow its progress in the sidebar or Active Jobs.')
                            ->warning()
                            ->send();

                        return;
                    }

                    try {
                        $php = (new PhpExecutableFinder)->find(false) ?: 'php';
                        $limit = (int) $data['limit'];

                        exec('cd '.escapeshellarg(base_path()).' && '.escapeshellarg($php)." artisan seo:group-keywords {$siteId} --limit={$limit} > /dev/null 2>&1 &");

                        Notification::make()
                            ->title('Keyword grouping started')
                            ->body("The AI is organizing up to {$limit} keywords. New groups will appear here automatically.")
                            ->success()
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Keyword grouping could not start')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
            CreateAction::make()
                ->label('Create Group')
                ->color('gray'),
        ];
    }
}
