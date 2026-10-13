<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Contact extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];

    /**
     * Companies associated with this contact (via company_contact pivot).
     */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_contact')
            ->withPivot(['job_title', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Activities logged for this contact.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'trackable');
    }
}