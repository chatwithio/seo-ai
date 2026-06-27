<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SeoKeyword extends Model
{
    protected $guarded = [];

    public function site()
    {
        return $this->belongsTo(GscSite::class, 'site_id');
    }
}
