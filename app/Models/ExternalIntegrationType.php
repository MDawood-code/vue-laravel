<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExternalIntegrationType extends Model
{
    use HasFactory, LogsActivity;

    /**
     * Get the authenticteed user's external integrations.
     *
     * @return HasMany<ExternalIntegration>
     */
    public function authUserExternalIntegrations(): HasMany
    {
        return $this->hasMany(ExternalIntegration::class)->where('company_id', auth()->user()->company_id);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
