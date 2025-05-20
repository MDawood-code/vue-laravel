<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HelpdeskTicket extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = [];

    /**
     * Get the customer.
     *
     * @return BelongsTo<User, self>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    /**
     * Get the support agent.
     *
     * @return BelongsTo<User, self>
     */
    public function supportAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'id');
    }

    /**
     * Get the reseller agent.
     *
     * @return BelongsTo<User, self>
     */
    public function resellerAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_agent', 'id');
    }

    /**
     * Get the user who manages it.
     *
     * @return BelongsTo<User, self>
     */
    public function manageBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manage_by', 'id');
    }

    /**
     * Get the issue type.
     *
     * @return BelongsTo<IssueType, self>
     */
    public function issueType(): BelongsTo
    {
        return $this->belongsTo(IssueType::class);
    }

    /**
     * is seen scope
     *
     * @param Builder<HelpdeskTicket> $query
     */
    public function scopeIsSeen(Builder $query): void
    {
        $query->where('is_seen', true);
    }

    /**
     * is not seen scope
     *
     * @param Builder<HelpdeskTicket> $query
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
