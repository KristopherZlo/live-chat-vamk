<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\MessageReactionController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\RoomBanController;

Route::get('/', [RoomController::class, 'landing'])->name('home');
Route::get('/presentation', LandingController::class)->name('presentation');
Route::get('/live-chat-vamk/presentation', LandingController::class);
Route::get('/live-chat-vamk/live-chat-vamk/presentation', LandingController::class);

Route::get('/join', [RoomController::class, 'joinForm'])->name('rooms.join');
Route::post('/join', [RoomController::class, 'joinSubmit'])->name('rooms.join.submit');

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
    Route::post('/rooms/{room}/bans', [RoomBanController::class, 'store'])
        ->name('rooms.bans.store');
    Route::delete('/rooms/{room}/bans/{ban}', [RoomBanController::class, 'destroy'])
        ->name('rooms.bans.destroy');
});

// Публичная страница комнаты по slug
Route::get('/r/{slug}', [RoomController::class, 'showPublic'])
    ->name('rooms.public');

// Отправка сообщения (общий чат + опциональный вопрос)
Route::post('/rooms/{room}/messages', [MessageController::class, 'store'])
    ->middleware('throttle:room-messages')
    ->name('rooms.messages.store');
Route::post('/rooms/{room}/messages/{message}/reactions', [MessageReactionController::class, 'toggle'])
    ->middleware('throttle:room-messages')
    ->name('rooms.messages.reactions.toggle');

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
