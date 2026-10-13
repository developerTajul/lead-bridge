<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealStage extends Model
{
    use HasFactory;

    protected $fillable = [
        'pipeline_id',
        'name',
        'order_position',
        'probability',
    ];

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}