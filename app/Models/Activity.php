<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Persistence model for activities. Tracks any record (lead, deal, contact)
 * through the {@see MorphTo} relation.
 */
final class Activity extends Model
{
    /**
     * Mass assignable attributes.
     *
     * @var array<int, string>
    protected $fillable = [
        'user_id',
        'trackable_type',
        'trackable_id',
        'type',
        'subject',
        'description',
        'due_date',
        'completed_at',
    ];
    */

    /**
     * Attribute casting.
     *
     * @return array<string, string>
    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
    */

    /**
     * যে ইউজার অ্যাক্টিভিটি তৈরি করেছে তার রিলেশন
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * পলিমর্ফিক রিলেশন (Lead, Deal, Company ইত্যাদি)
     */
    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }
}