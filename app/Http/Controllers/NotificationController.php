<?php

namespace App\Http\Controllers;
use App\Events\NewNotification;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
   public function sendNotification(Request $request)
{
    $request->validate([
        "type" => 'required|string',
        'title' => 'required|string',
        'message' => 'required|string',
    ]);

    event(new NewNotification($request->title, $request->message, $request->type));

    return response()->json([
        'status' => 'success',
        'message' => 'Notification sent successfully!'
    ]);
}
}
