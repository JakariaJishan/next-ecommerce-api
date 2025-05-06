<?php

use Illuminate\Support\Facades\Broadcast;

//Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
//    // Ensure the user is authenticated and matches the requested user ID
//    return auth('sanctum')->check() && (int) $user->id === (int) $id;
//});

Broadcast::channel('contests', function () {
    return true; // Public channel, no authentication required
});
