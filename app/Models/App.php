<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\RouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
#[RouteKey('slug')]
class App extends Model
{
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all binary releases for this application.
     *
     * @return HasMany<AppRelease, $this>
     */
    public function releases(): HasMany
    {
        return $this->hasMany(AppRelease::class);
    }

    /**
     * Get the latest binary release for this application.
     *
     * @return HasOne<AppRelease, $this>
     */
    public function latestRelease(): HasOne
    {
        return $this->hasOne(AppRelease::class)->latestOfMany();
    }
}
