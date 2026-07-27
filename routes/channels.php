<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('chat.{orderId}', function ($user, $orderId) {
    $order = \App\Models\Order::find($orderId);
    if (! $order) {
        return false;
    }
    // Izinkan jika user adalah owner atau pemilik pesanan tersebut
    return $user->isOwner() || $order->user_id === $user->id;
});
