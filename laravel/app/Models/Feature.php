<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    protected $fillable = ['name', 'key', 'enabled', 'description'];

    protected $casts = ['enabled' => 'boolean'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    /**
     * Single source of truth for feature access: the feature must be globally
     * enabled AND assigned to the user. Extend access rules here.
     */
    public function isAccessibleBy(User $user): bool
    {
        return $this->enabled && $this->users()->whereKey($user->id)->exists();
    }
}
