<?php

use App\Models\Room;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('room.host.{slug}', function ($user, string $slug) {
    return Room::canAccessHostChannel($user, $slug);
});
