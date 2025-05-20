<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExternalIntegration extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'url',
        'secret_key',
        'external_integration_type_id',
    ];

    /**
     * Get the external integration type.
     *
     * @return BelongsTo<ExternalIntegrationType, self>
     */
    public function externalIntegrationType(): BelongsTo
    {
        return $this->belongsTo(ExternalIntegrationType::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
