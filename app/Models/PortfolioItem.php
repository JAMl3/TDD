<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PortfolioItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'developer_profile_id',
        'title',
        'description',
        'image_path'
    ];

    public function developer()
    {
        return $this->belongsTo(DeveloperProfile::class, 'developer_profile_id');
    }
} 