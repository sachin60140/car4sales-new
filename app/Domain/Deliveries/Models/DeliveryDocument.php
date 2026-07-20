<?php

namespace App\Domain\Deliveries\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryDocument extends Model
{
    protected $fillable = [
        'delivery_id', 'type', 'file_path', 'handed_over', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'handed_over' => 'boolean',
        ];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
