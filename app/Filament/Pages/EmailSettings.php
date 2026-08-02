<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Settings;
use App\Livewire\EmailTemplatesTable;
use App\Models\PublishingSetting;
use BackedEnum;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

/**
 * @property-read Schema $form
 */
class EmailSettings extends Page
{
    protected static ?string $cluster = Settings::class;

    protected static ?string $navigationLabel = 'Email';

    protected static ?string $title = 'Email Settings';

    protected static ?string $slug = 'email';

    protected static ?int $navigationSort = 2;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected string $view = 'filament.pages.publishing-settings';

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public function mount(): void
    {
        $settings = PublishingSetting::firstOrCreate(['user_id' => auth()->id()]);

        $this->form->fill($settings->only([
            'weekly_activity_email_enabled',
            'weekly_ideas_email_enabled',
        ]));
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Email settings')
                    ->persistTabInQueryString()
                    ->tabs([
                        Tab::make('Email Preferences')
                            ->icon('heroicon-o-adjustments-horizontal')
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
                            ]),
                        Tab::make('Email Templates')
                            ->icon('heroicon-o-envelope-open')
                            ->schema([
                                Livewire::make(EmailTemplatesTable::class)
                                    ->key('email-templates-table'),
                                Section::make('Available placeholders')
                                    ->description('{name}, {app_name}, {url}, {login_url}, {dashboard_url}, {keywords_url}, {email_settings_url}, {support_url}, {youtube_url}, {activity_period}, {keyword_count}, {keyword_change}, {impressions}, {impressions_change}, {clicks}, {clicks_change}, {competitive_ideas_html}, {lower_traffic_ideas_html}, {ideas_html}')
                                    ->schema([]),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PublishingSetting::updateOrCreate(
            ['user_id' => auth()->id()],
            Arr::only($data, [
                'weekly_activity_email_enabled',
                'weekly_ideas_email_enabled',
            ]),
        );

        Notification::make()
            ->title('Email settings saved')
            ->body('Your weekly email preferences have been updated.')
            ->success()
            ->send();
    }
}
