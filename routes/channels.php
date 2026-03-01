<?php

use Illuminate\Support\Facades\Broadcast;

// Default user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Rackora — Private game channel for real-time WebSocket events
Broadcast::channel('game.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Rackora — User-specific channel (research, etc.)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
