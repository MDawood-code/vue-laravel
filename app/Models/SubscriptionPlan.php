<?php

namespace App\Models;

use Spatie\Activitylog\Traits\LogsActivity;
use Override;
use Spatie\Activitylog\LogOptions;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SubscriptionPlan extends Model
{
    use SoftDeletes, LogsActivity;

    protected $fillable = ['price', 'discount', 'user_price', 'user_discount'];

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'price' => 'double',
            'discount' => 'double',
            'user_price' => 'double',
            'user_discount' => 'double',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['*'])->logOnlyDirty();
    }
}
