<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_id',
        'user_id',
        'proposal',
        'timeline',
        'budget',
        'status',
        'portfolio_items'
    ];

    protected $casts = [
        'portfolio_items' => 'array',
        'budget' => 'decimal:2'
    ];

    public function job()
    {
        return $this->belongsTo(Job::class);
    }

    public function developer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
} 