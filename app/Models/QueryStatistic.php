<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QueryStatistic extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'total_queries',
        'total_cached_queries',
        'average_duration_ms',
        'top_five_queries',
        'most_popular_hour',
        'calculated_at',
    ];

    protected $casts = [
        'top_five_queries' => 'array',
        'calculated_at' => 'datetime',
        'average_duration_ms' => 'float',
    ];
}
