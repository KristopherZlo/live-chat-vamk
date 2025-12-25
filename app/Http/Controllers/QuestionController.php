<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\QuestionRating;
use App\Events\QuestionUpdated;

class QuestionController extends Controller
{
    // смена статуса вопроса: new / answered / ignored / later
    public function updateStatus(Request $request, Question $question)
    {
        /** @var Room|null $room */
        $room = $question->room;
        if (!$room) {
            abort(404);
        }

        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:new,answered,ignored,later'],
        ]);

        $status = $data['status'];
        $previousStatus = $question->status;
        $question->status = $status;

        // отметки времени под статус
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

        event(new QuestionUpdated($question));

        AuditLog::record($request, 'question.status.update', [
            'room_id' => $room->id,
            'target_type' => 'question',
            'target_id' => $question->id,
            'metadata' => [
                'from' => $previousStatus,
                'to' => $status,
            ],
        ]);

        return back();
    }

    // удаление вопроса владельцем (soft delete)
    public function ownerDelete(Request $request, Question $question)
    {
        /** @var Room|null $room */
        $room = $question->room;
        if (!$room) {
            abort(404);
        }

        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }

        $question->deleted_by_owner_at = now();
        $question->save();

        event(new QuestionUpdated($question));

        AuditLog::record($request, 'question.owner_delete', [
            'room_id' => $room->id,
            'target_type' => 'question',
            'target_id' => $question->id,
            'metadata' => [
                'status' => $question->status,
            ],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['deleted' => true]);
        }

        return back();
    }

    // удаление вопроса участником
    public function participantDelete(Request $request, Question $question)
    {
        /** @var Room|null $room */
        $room = $question->room;
        if (!$room) {
            abort(404);
        }

        $sessionKey = 'room_participant_' . $room->id;
        $participantId = $request->session()->get($sessionKey);

        if (!$participantId || $question->participant_id !== $participantId) {
            abort(403);
        }

        $question->deleted_by_participant_at = now();
        $question->save();

        event(new QuestionUpdated($question));

        AuditLog::record($request, 'question.participant_delete', [
            'actor_participant_id' => $participantId,
            'room_id' => $room->id,
            'target_type' => 'question',
            'target_id' => $question->id,
            'metadata' => [
                'status' => $question->status,
            ],
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['deleted' => true]);
        }

        return back();
    }

    // Оценка ответа (лайк / дизлайк)
    public function rate(Request $request, Question $question)
    {
        /** @var Room|null $room */
        $room = $question->room;
        if (!$room) {
            abort(404);
        }

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

        event(new QuestionUpdated($question));

        return back();
    }

    // Полное удаление вопроса (только владелец)
    public function destroy(Request $request, Question $question)
    {
        /** @var Room|null $room */
        $room = $question->room;
        if (!$room) {
            abort(404);
        }

        if (!Auth::check() || Auth::id() !== $room->user_id) {
            abort(403);
        }

        $question->delete();

        event(new QuestionUpdated($question));

        AuditLog::record($request, 'question.delete', [
            'room_id' => $room->id,
            'target_type' => 'question',
            'target_id' => $question->id,
            'metadata' => [
                'status' => $question->status,
            ],
        ]);

        return back();
    }
}
