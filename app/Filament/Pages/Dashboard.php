<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use App\Models\GoogleOauthToken;
use App\Models\SeoContentDraft;
use App\Models\SeoKeyword;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
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
        return SeoKeyword::whereHas('site', function ($q) {
            $q->where('user_id', Auth::id());
        })->where('total_impressions', '>', 50)
          ->where('total_clicks', '<', 3)
          ->count();
    }

    public function deleteToken(int $id)
    {
        GoogleOauthToken::where('user_id', Auth::id())->where('id', $id)->delete();

        \Filament\Notifications\Notification::make()
            ->title('Google account disconnected successfully')
            ->success()
            ->send();
    }
}
