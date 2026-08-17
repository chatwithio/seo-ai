<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Models\GscSite;
use App\Models\PublishingSetting;
use App\Models\SitePublishingConnection;
use App\Services\AccountOnboardingService;
use App\Services\WixPublishingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * @property-read Schema $form
 */
class PublishingSettings extends Page
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Publishing';

    protected static ?string $title = 'Publishing Settings';

    protected static ?string $slug = 'publishing';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-paper-airplane';

    protected string $view = 'filament.pages.publishing-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = PublishingSetting::firstOrCreate(['user_id' => auth()->id()]);

        if (blank($settings->content_api_key)) {
            $apiCode = $this->newApiCode();

            $settings->update([
                'content_api_key' => $apiCode,
                'content_api_key_hash' => hash('sha256', $apiCode),
            ]);
        }

        $this->form->fill([
            ...$settings->only([
                'content_api_enabled',
                'content_api_key',
                'auto_publish_enabled',
                'auto_publish_multiple_channels',
                'automation_publish_time',
                'general_webhook_enabled',
                'general_webhook_url',
                'general_webhook_secret',
                'general_webhook_priority',
                'wordpress_webhook_enabled',
                'wordpress_webhook_url',
                'wordpress_webhook_secret',
                'wordpress_webhook_priority',
                'wordpress_email_enabled',
                'wordpress_email',
                'wordpress_email_priority',
                'wordpress_post_status',
            ]),
            'content_api_list_url' => url('/api/v1/content'),
            'content_api_unread_url' => url('/api/v1/content/unread'),
            'wix_connections' => SitePublishingConnection::query()
                ->where('user_id', auth()->id())
                ->where('provider', 'wix')
                ->with('site:id,site_url')
                ->get()
                ->map(fn (SitePublishingConnection $connection): array => [
                    'site_id' => $connection->site_id,
                    'is_enabled' => $connection->is_enabled,
                    'priority' => $connection->priority,
                    'api_key' => $connection->credentials['api_key'] ?? '',
                    'wix_site_id' => $connection->settings['wix_site_id'] ?? '',
                    'member_id' => $connection->settings['member_id'] ?? '',
                    'post_status' => $connection->settings['post_status'] ?? 'draft',
                ])
                ->all(),
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Automation Schedule & Publishing')
                    ->description('Choose when this account creates scheduled content. Approved articles can be delivered automatically after the AI quality review.')
                    ->icon('heroicon-o-clock')
                    ->schema([
                        TimePicker::make('automation_publish_time')
                            ->label('Daily automation time')
                            ->seconds(false)
                            ->displayFormat('H:i')
                            ->required()
                            ->helperText(fn (): string => 'New accounts receive a staggered, non-round time automatically. All times use the system timezone: '.config('app.timezone', 'UTC').'.'),
                        Toggle::make('auto_publish_enabled')
                            ->label('Auto-publish new articles')
                            ->helperText('Publishing starts only after the AI quality review approves the article.')
                            ->live(),
                        Toggle::make('auto_publish_multiple_channels')
                            ->label('Send each article through every enabled method')
                            ->helperText('Off: stop after the first successful method and use the rest only as fallbacks. On: deliver the same article through every enabled method.')
                            ->visible(fn (Get $get): bool => (bool) $get('auto_publish_enabled')),
                    ])
                    ->columns([
                        'default' => 1,
                        'xl' => 2,
                    ])
                    ->columnSpanFull(),
                Tabs::make('Publishing methods')
                    ->persistTabInQueryString()
                    ->tabs(array_reverse([
                        Tab::make('Content API')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Section::make('Content Pull API')
                                    ->description('Let another website request generated articles using a private API code.')
                                    ->schema([
                                        Toggle::make('content_api_enabled')
                                            ->label('Enable Content API'),
                                        TextInput::make('content_api_key')
                                            ->label('API Code')
                                            ->password()
                                            ->revealable()
                                            ->readOnly()
                                            ->copyable(copyMessage: 'API code copied')
                                            ->suffixAction(
                                                Action::make('regenerateContentApiCode')
                                                    ->label('Regenerate')
                                                    ->icon('heroicon-o-arrow-path')
                                                    ->requiresConfirmation()
                                                    ->modalDescription('The current API code will stop working immediately.')
                                                    ->action(function (Set $set): void {
                                                        $apiCode = $this->newApiCode();

                                                        PublishingSetting::where('user_id', auth()->id())->update([
                                                            'content_api_key' => encrypt($apiCode),
                                                            'content_api_key_hash' => hash('sha256', $apiCode),
                                                        ]);

                                                        $set('content_api_key', $apiCode);

                                                        Notification::make()
                                                            ->title('API code regenerated')
                                                            ->success()
                                                            ->send();
                                                    }),
                                            )
                                            ->helperText('Send this in the X-API-Code request header. Keep it private.'),
                                        TextInput::make('content_api_list_url')
                                            ->label('List all publishable content')
                                            ->readOnly()
                                            ->copyable()
                                            ->dehydrated(false),
                                        TextInput::make('content_api_unread_url')
                                            ->label('Read next unread content')
                                            ->readOnly()
                                            ->copyable()
                                            ->dehydrated(false)
                                            ->helperText('Each request returns one unread article and marks it read.'),
                                    ])
                                    ->columns([
                                        'default' => 1,
                                        'xl' => 2,
                                    ]),
                            ]),
                        Tab::make('General Webhook')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('General Website Webhook')
                                    ->description('Send a neutral JSON article payload to any website or application.')
                                    ->schema([
                                        Toggle::make('general_webhook_enabled')
                                            ->label('Enable general webhook')
                                            ->live(),
                                        TextInput::make('general_webhook_priority')
                                            ->label('Automatic publishing position')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(99)
                                            ->default(30)
                                            ->required(fn (Get $get): bool => (bool) $get('auto_publish_enabled') && (bool) $get('general_webhook_enabled'))
                                            ->visible(fn (Get $get): bool => (bool) $get('auto_publish_enabled') && (bool) $get('general_webhook_enabled'))
                                            ->helperText('Lower numbers run first. Example: 1 runs before 2.'),
                                        TextInput::make('general_webhook_url')
                                            ->label('Webhook URL')
                                            ->url()
                                            ->placeholder('https://example.com/webhooks/seo-content')
                                            ->required(fn (Get $get): bool => (bool) $get('general_webhook_enabled')),
                                        TextInput::make('general_webhook_secret')
                                            ->label('Signing secret')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Optional. Used to create the X-SEOAI-Signature header.'),
                                    ]),
                            ]),
                        Tab::make('Wix')
                            ->icon('heroicon-o-window')
                            ->schema([
                                Section::make('Wix Blog')
                                    ->description('Connect one managed site to Wix for this account. API keys are encrypted at rest.')
                                    ->schema([
                                        Repeater::make('wix_connections')
                                            ->label('Wix site')
                                            ->addActionLabel('Connect Wix site')
                                            ->maxItems(1)
                                            ->reorderable(false)
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => GscSite::query()
                                                ->where('user_id', auth()->id())
                                                ->whereKey($state['site_id'] ?? null)
                                                ->value('site_url'))
                                            ->schema([
                                                Select::make('site_id')
                                                    ->label('Managed site')
                                                    ->options(fn (): array => GscSite::query()
                                                        ->where('user_id', auth()->id())
                                                        ->orderBy('site_url')
                                                        ->pluck('site_url', 'id')
                                                        ->all())
                                                    ->searchable()
                                                    ->distinct()
                                                    ->required()
                                                    ->disableOptionsWhenSelectedInSiblingRepeaterItems(),
                                                Toggle::make('is_enabled')
                                                    ->label('Enable Wix publishing')
                                                    ->live(),
                                                TextInput::make('priority')
                                                    ->label('Automatic publishing position')
                                                    ->numeric()
                                                    ->minValue(1)
                                                    ->maxValue(99)
                                                    ->default(40)
                                                    ->required(),
                                                TextInput::make('api_key')
                                                    ->label('Wix API key')
                                                    ->password()
                                                    ->revealable()
                                                    ->live(onBlur: true)
                                                    ->required(fn (Get $get): bool => (bool) $get('is_enabled')),
                                                TextInput::make('wix_site_id')
                                                    ->label('Wix Site ID')
                                                    ->live(onBlur: true)
                                                    ->required(fn (Get $get): bool => (bool) $get('is_enabled')),
                                                Select::make('member_id')
                                                    ->label('Wix Blog member (Author)')
                                                    ->searchable()
                                                    ->createOptionUsing(fn (string $input): string => trim($input))
                                                    ->createOptionAction(fn (Action $action) => $action->modalHeading('Enter Custom Member ID'))
                                                    ->helperText('Select a member from Wix, or click the refresh icon to pull the latest 10 members.')
                                                    ->options(function (Get $get, WixPublishingService $wix): array {
                                                        $apiKey = (string) ($get('api_key') ?? '');
                                                        $siteId = (string) ($get('wix_site_id') ?? '');
                                                        $current = (string) ($get('member_id') ?? '');

                                                        $options = [];
                                                        if (filled($current)) {
                                                            $options[$current] = "Selected: {$current}";
                                                        }

                                                        if (blank($apiKey) || blank($siteId)) {
                                                            return $options;
                                                        }

                                                        try {
                                                            $fetched = $wix->listMembers($apiKey, $siteId, 10);

                                                            return array_replace($options, $fetched);
                                                        } catch (\Throwable) {
                                                            return $options;
                                                        }
                                                    })
                                                    ->suffixAction(
                                                        Action::make('fetchWixMembers')
                                                            ->label('Refresh members')
                                                            ->icon('heroicon-o-arrow-path')
                                                            ->tooltip('Fetch last 10 members from Wix')
                                                            ->action(function (Get $get, Set $set, WixPublishingService $wix): void {
                                                                $apiKey = (string) ($get('api_key') ?? '');
                                                                $siteId = (string) ($get('wix_site_id') ?? '');

                                                                if (blank($apiKey) || blank($siteId)) {
                                                                    Notification::make()
                                                                        ->title('Wix API Key and Site ID required')
                                                                        ->body('Please enter your Wix API key and Wix Site ID first.')
                                                                        ->warning()
                                                                        ->send();

                                                                    return;
                                                                }

                                                                try {
                                                                    $members = $wix->listMembers($apiKey, $siteId, 10);
                                                                    if (empty($members)) {
                                                                        Notification::make()
                                                                            ->title('No Wix members found')
                                                                            ->body('Could not retrieve members. Ensure your Wix API key has Members read permissions.')
                                                                            ->warning()
                                                                            ->send();

                                                                        return;
                                                                    }

                                                                    if (blank($get('member_id'))) {
                                                                        $firstId = array_key_first($members);
                                                                        $set('member_id', $firstId);
                                                                    }

                                                                    Notification::make()
                                                                        ->title('Wix members loaded')
                                                                        ->body('Retrieved '.count($members).' member(s) from Wix.')
                                                                        ->success()
                                                                        ->send();
                                                                } catch (\Throwable $e) {
                                                                    Notification::make()
                                                                        ->title('Failed to fetch Wix members')
                                                                        ->body($e->getMessage())
                                                                        ->danger()
                                                                        ->send();
                                                                }
                                                            })
                                                    )
                                                    ->required(fn (Get $get): bool => (bool) $get('is_enabled')),
                                                Select::make('post_status')
                                                    ->label('Wix post status')
                                                    ->options([
                                                        'draft' => 'Create or update a draft',
                                                        'publish' => 'Publish immediately',
                                                    ])
                                                    ->default('draft')
                                                    ->required(),
                                            ])
                                            ->columns([
                                                'default' => 1,
                                                'xl' => 2,
                                            ]),
                                        Actions::make([
                                            Action::make('testWixConnection')
                                                ->label('Test Wix connection')
                                                ->icon('heroicon-o-signal')
                                                ->color('info')
                                                ->form([
                                                    Select::make('site_id')
                                                        ->label('Wix-connected site')
                                                        ->options(fn (): array => SitePublishingConnection::query()
                                                            ->where('user_id', auth()->id())
                                                            ->where('provider', 'wix')
                                                            ->where('is_enabled', true)
                                                            ->with('site:id,site_url')
                                                            ->get()
                                                            ->pluck('site.site_url', 'site_id')
                                                            ->all())
                                                        ->required(),
                                                ])
                                                ->action(function (array $data, WixPublishingService $wix): void {
                                                    $connection = SitePublishingConnection::query()
                                                        ->where('user_id', auth()->id())
                                                        ->where('site_id', (int) $data['site_id'])
                                                        ->where('provider', 'wix')
                                                        ->where('is_enabled', true)
                                                        ->firstOrFail();

                                                    try {
                                                        $message = $wix->testConnection($connection);
                                                        Notification::make()->title('Wix connection works')->body($message)->success()->send();
                                                    } catch (\Throwable $exception) {
                                                        $connection->update([
                                                            'last_tested_at' => now(),
                                                            'last_test_status' => 'failed',
                                                            'last_test_message' => Str::limit($exception->getMessage(), 1000, ''),
                                                        ]);
                                                        Notification::make()->title('Wix connection failed')->body($exception->getMessage())->danger()->send();
                                                    }
                                                }),
                                        ])->alignStart(),
                                    ]),
                            ]),
                        Tab::make('WordPress Webhook')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Section::make('WordPress Webhook')
                                    ->description('Send WordPress-shaped post fields to WP Webhooks or another WordPress listener.')
                                    ->schema([
                                        Toggle::make('wordpress_webhook_enabled')
                                            ->label('Enable WordPress webhook')
                                            ->live(),
                                        TextInput::make('wordpress_webhook_priority')
                                            ->label('Automatic publishing position')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(99)
                                            ->default(20)
                                            ->required(fn (Get $get): bool => (bool) $get('auto_publish_enabled') && (bool) $get('wordpress_webhook_enabled'))
                                            ->visible(fn (Get $get): bool => (bool) $get('auto_publish_enabled') && (bool) $get('wordpress_webhook_enabled'))
                                            ->helperText('Lower numbers run first. Example: 1 runs before 2.'),
                                        TextInput::make('wordpress_webhook_url')
                                            ->label('WordPress webhook URL')
                                            ->url()
                                            ->placeholder('https://example.com/wp-json/.../webhook')
                                            ->required(fn (Get $get): bool => (bool) $get('wordpress_webhook_enabled')),
                                        TextInput::make('wordpress_webhook_secret')
                                            ->label('Signing secret')
                                            ->password()
                                            ->revealable()
                                            ->helperText('Optional. Configure the same secret in the WordPress webhook receiver.'),
                                        Select::make('wordpress_post_status')
                                            ->label('WordPress post status')
                                            ->options([
                                                'publish' => 'Publish immediately',
                                                'draft' => 'Create as draft',
                                            ])
                                            ->default('publish')
                                            ->required(),
                                    ]),
                            ]),
                        Tab::make('WordPress Email')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('WordPress Post by Email')
                                    ->description('WordPress can create a post from an email. Enter the private address configured in WordPress Writing settings.')
                                    ->schema([
                                        Toggle::make('wordpress_email_enabled')
                                            ->label('Enable WordPress post by email')
                                            ->live(),
                                        TextInput::make('wordpress_email_priority')
                                            ->label('Automatic publishing position')
                                            ->numeric()
                                            ->minValue(1)
                                            ->maxValue(99)
                                            ->default(10)
                                            ->required(fn (Get $get): bool => (bool) $get('auto_publish_enabled') && (bool) $get('wordpress_email_enabled'))
                                            ->visible(fn (Get $get): bool => (bool) $get('auto_publish_enabled') && (bool) $get('wordpress_email_enabled'))
                                            ->helperText('Lower numbers run first. Example: 1 runs before 2.'),
                                        TextInput::make('wordpress_email')
                                            ->label('Private WordPress publishing email')
                                            ->email()
                                            ->placeholder('private-post-address@example.com')
                                            ->required(fn (Get $get): bool => (bool) $get('wordpress_email_enabled'))
                                            ->helperText('Keep this address private. The article title becomes the email subject and the article HTML becomes the message body.'),
                                    ]),
                            ]),
                    ]))
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $apiCode = (string) ($data['content_api_key'] ?? '');

        $this->validateAutomaticPublishing($data);

        PublishingSetting::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                ...Arr::only($data, [
                    'content_api_enabled',
                    'content_api_key',
                    'auto_publish_enabled',
                    'auto_publish_multiple_channels',
                    'automation_publish_time',
                    'general_webhook_enabled',
                    'general_webhook_url',
                    'general_webhook_secret',
                    'general_webhook_priority',
                    'wordpress_webhook_enabled',
                    'wordpress_webhook_url',
                    'wordpress_webhook_secret',
                    'wordpress_webhook_priority',
                    'wordpress_email_enabled',
                    'wordpress_email',
                    'wordpress_email_priority',
                    'wordpress_post_status',
                ]),
                'content_api_key_hash' => filled($apiCode) ? hash('sha256', $apiCode) : null,
            ],
        );

        SitePublishingConnection::query()
            ->where('user_id', auth()->id())
            ->where('provider', 'wix')
            ->update(['is_enabled' => false]);

        foreach ($data['wix_connections'] ?? [] as $connectionData) {
            $site = GscSite::query()
                ->where('user_id', auth()->id())
                ->findOrFail((int) ($connectionData['site_id'] ?? 0));

            SitePublishingConnection::updateOrCreate(
                ['user_id' => auth()->id(), 'provider' => 'wix'],
                [
                    'site_id' => $site->id,
                    'is_enabled' => (bool) ($connectionData['is_enabled'] ?? false),
                    'priority' => (int) ($connectionData['priority'] ?? 40),
                    'credentials' => ['api_key' => (string) ($connectionData['api_key'] ?? '')],
                    'settings' => [
                        'wix_site_id' => (string) ($connectionData['wix_site_id'] ?? ''),
                        'member_id' => (string) ($connectionData['member_id'] ?? ''),
                        'post_status' => (string) ($connectionData['post_status'] ?? 'draft'),
                    ],
                ],
            );
        }
        app(AccountOnboardingService::class)->markPublishingReviewedForUser((int) auth()->id());

        Notification::make()
            ->title('Publishing settings saved')
            ->body('Your publishing methods have been updated.')
            ->success()
            ->send();
    }

    private function newApiCode(): string
    {
        return 'seoai_'.Str::random(48);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function validateAutomaticPublishing(array $data): void
    {
        if (! ($data['auto_publish_enabled'] ?? false)) {
            return;
        }

        $enabledMethods = collect([
            'general_webhook' => (bool) ($data['general_webhook_enabled'] ?? false),
            'wordpress_webhook' => (bool) ($data['wordpress_webhook_enabled'] ?? false),
            'wordpress_email' => (bool) ($data['wordpress_email_enabled'] ?? false),
        ])->filter();

        $enabledWixConnections = collect($data['wix_connections'] ?? [])
            ->filter(fn (array $connection): bool => (bool) ($connection['is_enabled'] ?? false));

        if ($enabledMethods->isEmpty() && $enabledWixConnections->isEmpty()) {
            throw ValidationException::withMessages([
                'data.auto_publish_enabled' => 'Enable at least one publishing method before turning on automatic publishing.',
            ]);
        }

        $priorities = $enabledMethods
            ->keys()
            ->map(fn (string $method): int => (int) ($data[$method.'_priority'] ?? 0));

        if ($priorities->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'data.auto_publish_enabled' => 'Each enabled publishing method must have a different automatic publishing position.',
            ]);
        }

        foreach ($enabledWixConnections as $index => $connection) {
            $wixPriority = (int) ($connection['priority'] ?? 0);

            if ($priorities->contains($wixPriority)) {
                throw ValidationException::withMessages([
                    "data.wix_connections.{$index}.priority" => 'This position is already used by another enabled publishing method.',
                ]);
            }
        }
    }
}
