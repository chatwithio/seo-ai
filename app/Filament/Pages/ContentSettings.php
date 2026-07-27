<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Models\GscSite;
use App\Services\AccountOnboardingService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * @property-read Schema $form
 */
class ContentSettings extends Page
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Content';

    protected static ?string $title = 'Content Settings';

    protected static ?string $slug = 'content';

    protected static ?int $navigationSort = 3;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected string $view = 'filament.pages.publishing-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $siteData = $this->sites()->mapWithKeys(fn (GscSite $site): array => [
            (string) $site->id => $site->only([
                'auto_content_enabled',
                'auto_content_strategy',
                'auto_content_count',
                'auto_content_interval_days',
                'content_language',
                'content_length',
                'content_keyword_density',
                'content_instructions',
                'agent_enabled',
                'agent_strategy',
                'min_impressions',
                'max_clicks',
                'grouping_limit',
            ]),
        ])->all();

        $this->form->fill(['sites' => $siteData]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $sites = $this->sites();

        return $schema
            ->components([
                Tabs::make('Site content settings')
                    ->persistTabInQueryString()
                    ->tabs($sites->isEmpty()
                        ? [$this->emptySitesTab()]
                        : $sites->map(fn (GscSite $site): Tab => $this->siteTab($site))->all())
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $siteData = $this->form->getState()['sites'] ?? [];

        foreach ($this->sites() as $site) {
            $settings = $siteData[(string) $site->id] ?? $siteData[$site->id] ?? [];

            $site->update(Arr::only($settings, [
                'auto_content_enabled',
                'auto_content_strategy',
                'auto_content_count',
                'auto_content_interval_days',
                'content_language',
                'content_length',
                'content_keyword_density',
                'content_instructions',
                'agent_enabled',
                'agent_strategy',
                'min_impressions',
                'max_clicks',
                'grouping_limit',
            ]));
        }
        app(AccountOnboardingService::class)->markContentSettingsReviewedForUser((int) auth()->id());

        Notification::make()
            ->title('Content settings saved')
            ->body('Automatic generation and article defaults were updated for your sites.')
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, GscSite>
     */
    private function sites(): Collection
    {
        return GscSite::query()
            ->where('user_id', auth()->id())
            ->orderByRaw('COALESCE(name, site_url)')
            ->get();
    }

    private function siteTab(GscSite $site): Tab
    {
        $path = "sites.{$site->id}";
        $label = filled($site->name)
            ? Str::limit($site->name, 28)
            : Str::limit(parse_url($site->site_url, PHP_URL_HOST) ?: $site->site_url, 28);

        return Tab::make($label)
            ->key("content-site-{$site->id}")
            ->icon('heroicon-o-globe-alt')
            ->schema([
                Section::make('Full AI Agent Workflow')
                    ->description('Configure the scheduled workflow that groups keywords, generates plans and articles, and reviews the result.')
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Toggle::make("{$path}.agent_enabled")
                            ->label('Enable full AI agent workflow')
                            ->helperText('Runs for this site only when automatic content generation below is disabled.')
                            ->columnSpanFull(),
                        Select::make("{$path}.agent_strategy")
                            ->label('Keyword targeting strategy')
                            ->options([
                                'low_ctr' => 'High impressions, low clicks',
                                'high_clicks' => 'Top-performing keywords',
                            ])
                            ->default('low_ctr')
                            ->live()
                            ->required(),
                        TextInput::make("{$path}.grouping_limit")
                            ->label('Keywords grouped per batch')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(200)
                            ->default(50)
                            ->required(),
                        TextInput::make("{$path}.min_impressions")
                            ->label('Minimum impressions')
                            ->numeric()
                            ->minValue(0)
                            ->default(100)
                            ->visible(fn (Get $get): bool => $get("{$path}.agent_strategy") === 'low_ctr')
                            ->required(fn (Get $get): bool => $get("{$path}.agent_strategy") === 'low_ctr'),
                        TextInput::make("{$path}.max_clicks")
                            ->label('Maximum clicks')
                            ->numeric()
                            ->minValue(0)
                            ->default(10)
                            ->visible(fn (Get $get): bool => $get("{$path}.agent_strategy") === 'low_ctr')
                            ->required(fn (Get $get): bool => $get("{$path}.agent_strategy") === 'low_ctr'),
                    ]),
                Section::make('Automatic Content Generation')
                    ->description("Choose what to generate for {$site->site_url} and how often it should run.")
                    ->columns([
                        'default' => 1,
                        'md' => 2,
                    ])
                    ->schema([
                        Toggle::make("{$path}.auto_content_enabled")
                            ->label('Enable automatic content generation')
                            ->helperText('The scheduler checks this site daily and only runs when its interval is due.')
                            ->columnSpanFull(),
                        Select::make("{$path}.auto_content_strategy")
                            ->label('Keyword target')
                            ->options([
                                'opportunities' => 'High impressions, low clicks',
                                'top_impressions' => 'Top impressions',
                                'top_clicks' => 'Top clicks',
                            ])
                            ->default('opportunities')
                            ->required(),
                        TextInput::make("{$path}.auto_content_count")
                            ->label('Articles per run')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->default(3)
                            ->required()
                            ->helperText('Example: 3 creates up to three articles each time this site runs.'),
                        TextInput::make("{$path}.auto_content_interval_days")
                            ->label('Run every')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(365)
                            ->default(1)
                            ->required()
                            ->suffix('day(s)'),
                    ]),
                Section::make('Article Defaults')
                    ->description('These settings are used for automatic generation and every Generate Content button on this site.')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        Select::make("{$path}.content_language")
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
                        Select::make("{$path}.content_length")
                            ->label('Article length')
                            ->options([
                                500 => 'Short (~500 words)',
                                1000 => 'Medium (~1000 words)',
                                1500 => 'Long (~1500 words)',
                            ])
                            ->default(1000)
                            ->required(),
                        Select::make("{$path}.content_keyword_density")
                            ->label('Keyword repeat density')
                            ->options([
                                '1.0' => '1%',
                                '1.5' => '1.5%',
                                '2.0' => '2%',
                                '2.5' => '2.5%',
                                '3.0' => '3%',
                            ])
                            ->default('1.5')
                            ->required(),
                        Textarea::make("{$path}.content_instructions")
                            ->label('Additional instructions')
                            ->placeholder('Optional tone, audience, product, or page guidance')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private function emptySitesTab(): Tab
    {
        return Tab::make('No managed sites')
            ->icon('heroicon-o-information-circle')
            ->schema([
                Section::make('Connect a site first')
                    ->description('Add a Google connection and sync a Search Console site before configuring content generation.')
                    ->schema([]),
            ]);
    }
}
