<?php

namespace App\Http\Controllers;

use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\QuestionRating;

class QuestionController extends Controller
{
    // смена статуса вопроса: new / answered / ignored / later
    public function updateStatus(Request $request, Question $question)
    {
        $room = $question->room;

        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:new,answered,ignored,later'],
        ]);

        $status = $data['status'];

        $question->status = $status;

        if ($status === 'answered') {
            $question->answered_at = now();
            $question->ignored_at = null;
        } elseif ($status === 'ignored') {
            $question->ignored_at = now();
            $question->answered_at = null;
        } else {
            // new / later
            $question->answered_at = null;
            $question->ignored_at = null;
        }

        // Возврат в очередь: чистим soft-delete флаги
        if ($status === 'new') {
            $question->deleted_by_owner_at = null;
            $question->deleted_by_participant_at = null;
        }

        $question->save();

        return back();
    }

    // удаление вопроса владельцем (soft delete)
    public function ownerDelete(Request $request, Question $question)
    {
        $room = $question->room;

        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }

        $question->deleted_by_owner_at = now();
        $question->save();

        return back();
    }

    // удаление вопроса участником
    public function participantDelete(Request $request, Question $question)
    {
        $room = $question->room;

        $sessionKey = 'room_participant_' . $room->id;
        $participantId = $request->session()->get($sessionKey);

        if (!$participantId || $question->participant_id !== $participantId) {
            abort(403);
        }

        $question->deleted_by_participant_at = now();
        $question->save();

        return back();
    }

    public function rate(Request $request, Question $question)
    {
        $room = $question->room;

        // Оценивать можно только свои вопросы
        $sessionKey = 'room_participant_' . $room->id;
        $participantId = $request->session()->get($sessionKey);

        if (!$participantId || $question->participant_id !== $participantId) {
            abort(403);
        }

        // Оценка только если вопрос отмечен как "answered"
        if ($question->status !== 'answered') {
            abort(403);
        }

        $data = $request->validate([
            'rating' => ['required', 'in:1,-1'],
        ]);

        QuestionRating::updateOrCreate(
            [
                'question_id' => $question->id,
                'participant_id' => $participantId,
            ],
            [
                'rating' => (int) $data['rating'],
            ]
        );

        return back();
    }

    public function destroy(Request $request, Question $question)
    {
        $room = $question->room;

        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }

        $question->delete();

        return back();
    }
}
