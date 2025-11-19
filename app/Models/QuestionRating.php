<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class QuestionRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'participant_id',
        'rating',
    ];

    public function question()
    {
        return $this->belongsTo(Question::class);
    }

    public function participant()
    {
        return $this->belongsTo(Participant::class);
    }
}
