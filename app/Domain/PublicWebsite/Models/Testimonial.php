<?php

namespace App\Domain\PublicWebsite\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'customer_name', 'city', 'rating', 'message', 'avatar_path',
        'is_approved', 'is_featured', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }
}
