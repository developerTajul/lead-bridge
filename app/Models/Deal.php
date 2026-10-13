<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'company_id',
        'contact_id',
        'amount',
        'currency',
        'pipeline_id',
        'deal_stage_id',
        'expected_close_date',
        'actual_close_date',
    ];

    protected $casts = [
        'amount'              => 'decimal:2',
        'expected_close_date' => 'date',
        'actual_close_date'   => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(DealStage::class, 'deal_stage_id');
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'trackable');
    }
}