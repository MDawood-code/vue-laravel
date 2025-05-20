<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResellerComment extends Model
{
    use HasFactory, SoftDeletes, LogsActivity;

    protected $table = 'reseller_comments';

    protected $fillable = [
        'description',
        'reseller_id',
        'created_by',
    ];

    /**
     * Get the reseller.
     *
     * @return BelongsTo<User, self>
     */
    public function commentReseller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reseller_id', 'id');
    }

    /**
     * Get the user who created it.
     *
     * @return BelongsTo<User, self>
     */
    public function createdByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
