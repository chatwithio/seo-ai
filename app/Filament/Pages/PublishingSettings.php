<?php

namespace App\Filament\Pages;

use App\Livewire\EmailTemplatesTable;
use App\Models\PublishingSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * @property-read Schema $form
 */
class PublishingSettings extends Page
{
    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Settings';

    protected static ?string $slug = 'settings';

    protected static ?int $navigationSort = 11;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

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
                'general_webhook_enabled',
                'general_webhook_url',
                'general_webhook_secret',
                'wordpress_webhook_enabled',
                'wordpress_webhook_url',
                'wordpress_webhook_secret',
                'wordpress_email_enabled',
                'wordpress_email',
                'wordpress_post_status',
                'weekly_activity_email_enabled',
                'weekly_ideas_email_enabled',
            ]),
            'content_api_list_url' => url('/api/v1/content'),
            'content_api_unread_url' => url('/api/v1/content/unread'),
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
                Tabs::make('Settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Publishing')
                            ->icon('heroicon-o-paper-airplane')
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
                                Section::make('General Website Webhook')
                                    ->description('Send a neutral JSON article payload to any website or application.')
                                    ->schema([
                                        Toggle::make('general_webhook_enabled')
                                            ->label('Enable general webhook')
                                            ->live(),
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
                                Section::make('WordPress Webhook')
                                    ->description('Send WordPress-shaped post fields to WP Webhooks or another WordPress listener.')
                                    ->schema([
                                        Toggle::make('wordpress_webhook_enabled')
                                            ->label('Enable WordPress webhook')
                                            ->live(),
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
                                Section::make('WordPress Post by Email')
                                    ->description('WordPress can create a post from an email. Enter the private address configured in WordPress Writing settings.')
                                    ->schema([
                                        Toggle::make('wordpress_email_enabled')
                                            ->label('Enable WordPress post by email')
                                            ->live(),
                                        TextInput::make('wordpress_email')
                                            ->label('Private WordPress publishing email')
                                            ->email()
                                            ->placeholder('private-post-address@example.com')
                                            ->required(fn (Get $get): bool => (bool) $get('wordpress_email_enabled'))
                                            ->helperText('Keep this address private. The article title becomes the email subject and the article HTML becomes the message body.'),
                                    ]),
                            ]),
                        Tab::make('Email Templates')
                            ->icon('heroicon-o-envelope')
                            ->schema([
                                Section::make('Weekly Email Preferences')
                                    ->description('Choose which weekly SEO emails users receive.')
                                    ->columns([
                                        'default' => 1,
                                        'md' => 2,
                                    ])
                                    ->schema([
                                        Toggle::make('weekly_activity_email_enabled')
                                            ->label('Send weekly SEO activity email'),
                                        Toggle::make('weekly_ideas_email_enabled')
                                            ->label('Send weekly SEO content ideas'),
                                    ]),
                                Livewire::make(EmailTemplatesTable::class)
                                    ->key('email-templates-table'),
                                Section::make('Available placeholders')
                                    ->description('{name}, {app_name}, {url}, {login_url}, {dashboard_url}, {keywords_url}, {support_url}, {youtube_url}, {keyword_count}, {impressions}, {clicks}, {article_count}, {ideas_html}')
                                    ->schema([]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $apiCode = (string) ($data['content_api_key'] ?? '');

        PublishingSetting::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                ...Arr::only($data, [
                    'content_api_enabled',
                    'content_api_key',
                    'general_webhook_enabled',
                    'general_webhook_url',
                    'general_webhook_secret',
                    'wordpress_webhook_enabled',
                    'wordpress_webhook_url',
                    'wordpress_webhook_secret',
                    'wordpress_email_enabled',
                    'wordpress_email',
                    'wordpress_post_status',
                    'weekly_activity_email_enabled',
                    'weekly_ideas_email_enabled',
                ]),
                'content_api_key_hash' => filled($apiCode) ? hash('sha256', $apiCode) : null,
            ],
        );

        Notification::make()
            ->title('Settings saved')
            ->body('Publishing destinations and weekly email preferences have been updated.')
            ->success()
            ->send();
    }

    private function newApiCode(): string
    {
        return 'seoai_'.Str::random(48);
    }
}
