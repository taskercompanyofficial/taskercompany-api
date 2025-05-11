<?php

namespace App\Http\Controllers;

use App\Models\StoreUserSpecific;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class StoreUserSpecificController extends Controller
{

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();
        StoreUserSpecific::where('user_id', $user->id)->delete();
        StoreUserSpecific::create([
            'user_id' => $user->id,
            'push_token' => $request->push_token
        ]);
        return response()->json(['message' => 'Push token stored successfully'], 200);
    }
    public function sendNotification(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'body' => 'required|string',
        ]);

        $user = $request->user();
        $pushToken = StoreUserSpecific::where('user_id', $user->id)->first()->push_token;

        $client = new Client();
        $response = $client->post('https://exp.host/--/api/v2/push/send', [
            'json' => [
                'to' => $pushToken,
                'title' => $request->title,
                'body' => $request->body,
            ],
        ]);

        return response()->json(['message' => 'Notification sent successfully.']);
    }
    /**
     * Display the specified resource.
     */
    public function show(StoreUserSpecific $storeUserSpecific)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(StoreUserSpecific $storeUserSpecific)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, StoreUserSpecific $storeUserSpecific)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(StoreUserSpecific $storeUserSpecific)
    {
        //
    }
}
