<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\QuestionController;

Route::get('/', function () {
    return redirect()->route('dashboard');
})->middleware('auth');


Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::middleware(['auth'])->group(function () {
    // Личный кабинет (список комнат)
    Route::get('/dashboard', [RoomController::class, 'dashboard'])
        ->name('dashboard');

    // Создание комнаты
    Route::get('/rooms/create', [RoomController::class, 'create'])
        ->name('rooms.create');
    Route::post('/rooms', [RoomController::class, 'store'])
        ->name('rooms.store');
    Route::patch('/rooms/{room}', [RoomController::class, 'update'])
        ->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])
        ->name('rooms.destroy');
});

// Публичная страница комнаты по slug
Route::get('/r/{slug}', [RoomController::class, 'showPublic'])
    ->name('rooms.public');

// Отправка сообщения (общий чат + опциональный вопрос)
Route::post('/rooms/{room}/messages', [MessageController::class, 'store'])
    ->name('rooms.messages.store');

Route::middleware(['auth'])->group(function () {
    // ... тут уже есть dashboard / rooms.create / rooms.store

    // Изменить статус вопроса (только владелец комнаты)
    Route::post('/questions/{question}/status', [QuestionController::class, 'updateStatus'])
        ->name('questions.updateStatus');

    // Удалить вопрос со стороны владельца (soft delete)
    Route::delete('/questions/{question}/owner-delete', [QuestionController::class, 'ownerDelete'])
        ->name('questions.ownerDelete');
});

// Удалить вопрос со стороны участника (без авторизации, но по сессии)
Route::delete('/questions/{question}/participant-delete', [QuestionController::class, 'participantDelete'])
    ->name('questions.participantDelete');

// Оценка ответа на вопрос (лайк/дизлайк) — без auth, но по сессии
Route::post('/questions/{question}/rating', [QuestionController::class, 'rate'])
    ->name('questions.rate');

Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])
    ->name('questions.destroy');

Route::get('/rooms/{room}/questions-panel', [RoomController::class, 'questionsPanel'])
    ->name('rooms.questionsPanel');
Route::get('/rooms/{room}/my-questions-panel', [RoomController::class, 'myQuestionsPanel'])
    ->name('rooms.myQuestionsPanel');

Route::view('/legal/privacy', 'legal.privacy')
    ->name('privacy');

require __DIR__.'/auth.php';
