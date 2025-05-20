<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Questionnaire extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'learning_source_id',
        'other_learning_source',
        'preferred_platform',
        'new_or_existing',
        'company_id',
    ];

    /**
     * Get the learning source.
     *
     * @return BelongsTo<LearningSource, self>
     */
    public function learningSource(): BelongsTo
    {
        return $this->belongsTo(LearningSource::class);
    }

    /**
     * Get the admin staff.
     *
     * @return BelongsTo<User, self>
     */
    public function adminStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_staff_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
