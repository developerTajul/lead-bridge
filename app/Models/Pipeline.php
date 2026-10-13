<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pipeline extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function stages(): HasMany
    {
        return $this->hasMany(DealStage::class)->orderBy('order_position');
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }
}