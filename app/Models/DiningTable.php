<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Override;

class DiningTable extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = ['name', 'number_of_seats', 'qr_code_path', 'branch_id', 'is_drive_thru'];

    #[Override]
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($diningTable): void {
            if (! is_null($diningTable->qr_code_path)) {
                Storage::delete(str_replace('/storage', 'public', $diningTable->qr_code_path));
            }
        });
    }

    /**
     * Get the Branch.
     *
     * @return BelongsTo<Branch, self>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
