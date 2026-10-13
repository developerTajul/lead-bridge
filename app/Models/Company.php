<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Company extends Model
{
    use HasFactory;

    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'website',
        'industry',
        'phone',
        'address',
    ];

    /**
     * Contacts associated with this company (via company_contact pivot).
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'company_contact')
            ->withPivot(['job_title', 'is_primary'])
            ->withTimestamps();
    }

    /**
     * Deals associated with this company.
     */
    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    /**
     * Activities logged for this company.
     */
    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'trackable');
    }
}