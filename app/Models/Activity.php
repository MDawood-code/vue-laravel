<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $fillable = [
        'title',
        'description',
        'activity_type_id',
        'start_date',
        'end_date',
        'reminder',
        'company_id',
        'created_by',
        'assigned_to',
        'is_seen',
    ];

    // protected function assingedTo(): Attribute
    // {
    //     return Attribute::make(
    //         set: fn (string $value) => (int) $value,
    //     );
    // }
    /**
     * Get the company.
     *
     * @return BelongsTo<Company, self>
     */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the activity type.
     *
     * @return BelongsTo<ActivityType, self>
     */
    public function activityType(): BelongsTo
    {
        return $this->belongsTo(ActivityType::class);
    }

    /**
     * Get the user who created this activity.
     *
     * @return BelongsTo<User, self>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the user to whom this activity belongs to.
     *
     * @return BelongsTo<User, self>
     */
    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    /**
     * Get comments.
     *
     * @return HasMany<Comment>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * is seen scope
     *
     * @param Builder<Activity> $query
     */
    public function scopeIsSeen(Builder $query): void
    {
        $query->where('is_seen', true);
    }

    /**
     * is not seen scope
     *
     * @param Builder<Activity> $query
     */
    public function scopeIsNotSeen(Builder $query): void
    {
        $query->where('is_seen', false);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
