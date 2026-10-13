<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lead extends Model
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
        'company_name',
        'status',
        'source',
    ];

    /**
     * Default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'new',
        'source' => 'website',
    ];

    public function conversion(): HasOne
    { 
        return $this->hasOne(LeadConversion::class); 
    }

    public function activities(): MorphMany
    {
        return $this->morphMany(Activity::class, 'trackable')->latest();
    }
}