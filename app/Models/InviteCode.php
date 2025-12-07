<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InviteCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'used_by',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by');
    }
}
