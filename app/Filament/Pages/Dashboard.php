<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AiPrompts\AiPromptResource;
use App\Filament\Resources\SeoContentDrafts\SeoContentDraftResource;
use App\Filament\Resources\SeoKeywords\SeoKeywordResource;
use App\Models\GoogleOauthToken;
use App\Models\GscSite;
use App\Models\SeoContentDraft;
use App\Models\SeoKeyword;
use App\Services\BackgroundTaskManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\PhpExecutableFinder;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.dashboard';

    public function getTokens()
    {
        return GoogleOauthToken::where('user_id', Auth::id())->get();
    }

    public function getDraftsCount(): int
    {
        return SeoContentDraft::whereHas('group.site', function ($q) {
            $q->where('user_id', Auth::id());
        })->count();
    }

    public function getLowCtrKeywordsCount(): int
    {
        return $this->opportunityKeywordsQuery()->count();
    }

    public function getTopKeywordsCount(): int
    {
        return $this->topKeywordsQuery()->count();
    }

    public function getTopKeywords()
    {
        return $this->topKeywordsQuery()
            ->with('site:id,site_url,name')
            ->orderByDesc('total_impressions')
            ->orderByDesc('total_clicks')
            ->limit(5)
            ->get();
    }

    public function getOpportunityKeywords()
    {
        return $this->opportunityKeywordsQuery()
            ->with('site:id,site_url,name')
            ->orderByDesc('total_impressions')
            ->limit(5)
            ->get();
    }

    public function getManagedSitesCount(): int
    {
        return GscSite::where('user_id', Auth::id())->count();
    }

    public function getLatestImportAt(): mixed
    {
        return GscSite::where('user_id', Auth::id())->max('last_imported_at');
    }

    public function getImportTask(): ?array
    {
        return BackgroundTaskManager::findActiveForUser((int) Auth::id(), 'Import GSC Keywords');
    }

    public function getSiteSyncTask(): ?array
    {
        return BackgroundTaskManager::findActiveForUser((int) Auth::id(), 'Sync Google Sites');
    }

    public function getTopKeywordsUrl(): string
    {
        return SeoKeywordResource::getUrl('index', [
            'filters' => ['top_impressions' => ['isActive' => true]],
        ]);
    }

    public function getOpportunityKeywordsUrl(): string
    {
        return SeoKeywordResource::getUrl('index', [
            'filters' => ['opportunities' => ['isActive' => true]],
        ]);
    }

    public function getContentUrl(): string
    {
        return SeoKeywordResource::getUrl('index');
    }

    public function getArticlesUrl(): string
    {
        return SeoContentDraftResource::getUrl('index');
    }

    public function getAiInstructionsUrl(): string
    {
        return AiPromptResource::getUrl('index');
    }

    public function deleteToken(int $id)
    {
        GoogleOauthToken::where('user_id', Auth::id())->where('id', $id)->delete();

        Notification::make()
            ->title('Google account disconnected successfully')
            ->success()
            ->send();
    }

    public function generateKeywordContentAction(): Action
    {
        return Action::make('generateKeywordContent')
            ->label('Generate Content')
            ->icon('heroicon-o-sparkles')
            ->color('success')
            ->modalHeading('Generate Content')
            ->modalDescription('Choose how the article should be written before starting the background generation.')
            ->modalSubmitActionLabel('Start Generation')
            ->form([
                Select::make('language')
                    ->label('Language')
                    ->options([
                        'English' => 'English',
                        'Spanish' => 'Spanish',
                        'French' => 'French',
                        'Italian' => 'Italian',
                        'German' => 'German',
                        'Portuguese' => 'Portuguese',
                    ])
                    ->default('English')
                    ->required(),
                Select::make('length')
                    ->label('Article Length')
                    ->options([
                        '500' => 'Short (~500 words)',
                        '1000' => 'Medium (~1000 words)',
                        '1500' => 'Long (~1500 words)',
                    ])
                    ->default('1000')
                    ->required(),
                Select::make('density')
                    ->label('Keyword Repeat Density')
                    ->options([
                        '1' => '1%',
                        '1.5' => '1.5%',
                        '2' => '2%',
                        '2.5' => '2.5%',
                        '3' => '3%',
                    ])
                    ->default('1.5')
                    ->required(),
                Textarea::make('hint')
                    ->label('Additional Instructions')
                    ->placeholder('Optional tone, audience, product, or page guidance')
                    ->rows(3),
            ])
            ->action(function (array $data, array $arguments): void {
                $this->startKeywordContentGeneration(
                    (int) ($arguments['keywordId'] ?? 0),
                    $data,
                );
            });
    }

    private function startKeywordContentGeneration(int $keywordId, array $options): void
    {
        $keyword = SeoKeyword::query()
            ->where('user_id', Auth::id())
            ->with('site:id,user_id')
            ->find($keywordId);

        if (! $keyword || ! $keyword->site) {
            Notification::make()
                ->title('Keyword not found')
                ->danger()
                ->send();

            return;
        }

        if ((int) $keyword->content_generation_status === SeoKeyword::CONTENT_COMPLETED) {
            Notification::make()
                ->title('Content already exists')
                ->body('Open Search Keywords if you intentionally want to generate it again.')
                ->warning()
                ->send();

            return;
        }

        $lockKey = 'seo:generate-content:lock:'.$keyword->id;

        if ((int) $keyword->content_generation_status === SeoKeyword::CONTENT_GENERATING || Cache::has($lockKey)) {
            Notification::make()
                ->title('Content generation is already running')
                ->body('Follow its progress in the sidebar or Active Jobs.')
                ->warning()
                ->send();

            return;
        }

        $keyword->update([
            'content_generation_status' => SeoKeyword::CONTENT_GENERATING,
        ]);

        try {
            $php = (new PhpExecutableFinder)->find(false) ?: 'php';
            $keywordId = (int) $keyword->id;
            $language = escapeshellarg($options['language']);
            $density = escapeshellarg($options['density']);
            $length = escapeshellarg($options['length']);
            $hint = escapeshellarg($options['hint'] ?? '');

            exec('cd '.escapeshellarg(base_path()).' && '.escapeshellarg($php)." artisan seo:generate-content --keyword-ids={$keywordId} --language={$language} --density={$density} --length={$length} --hint={$hint} > /dev/null 2>&1 &");

            Notification::make()
                ->title('Content generation started')
                ->body("Creating content for “{$keyword->query_text}”. It has been removed from dashboard recommendations.")
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            $keyword->update([
                'content_generation_status' => SeoKeyword::CONTENT_FAILED,
            ]);

            Notification::make()
                ->title('Content generation could not start')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    private function opportunityKeywordsQuery()
    {
        return SeoKeyword::query()
            ->contentOpportunitiesForUser((int) Auth::id());
    }

    private function topKeywordsQuery()
    {
        return SeoKeyword::query()
            ->topByImpressionsForUser((int) Auth::id());
    }
}
