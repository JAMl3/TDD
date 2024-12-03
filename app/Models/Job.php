<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Database\Factories\JobFactory;

class Job extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'budget',
        'deadline',
        'required_skills',
        'status',
    ];

    protected $casts = [
        'deadline' => 'datetime',
        'required_skills' => 'array',
        'budget' => 'decimal:2',
    ];

    protected static function newFactory()
    {
        return JobFactory::new();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }
} 