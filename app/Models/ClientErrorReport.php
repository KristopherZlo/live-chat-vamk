<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClientErrorReport extends Model
{
    protected $fillable = [
        'severity',
        'message',
        'stack',
        'url',
        'line',
        'column',
        'user_id',
        'request_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
