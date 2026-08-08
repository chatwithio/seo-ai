<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'email_verified_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'articles_last_viewed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function publishingSetting()
    {
        return $this->hasOne(PublishingSetting::class);
    }

    public function sitePublishingConnections()
    {
        return $this->hasMany(SitePublishingConnection::class);
    }

    public function sites()
    {
        return $this->hasMany(GscSite::class);
    }

    public function keywords()
    {
        return $this->hasMany(SeoKeyword::class);
    }

    public function contentDrafts()
    {
        return $this->hasMany(SeoContentDraft::class);
    }

    public function temporaryLoginLinks()
    {
        return $this->hasMany(TemporaryUserLoginLink::class);
    }

    protected static function booted(): void
    {
        static::created(function (User $user): void {
            $user->publishingSetting()->firstOrCreate();
        });
    }
}
