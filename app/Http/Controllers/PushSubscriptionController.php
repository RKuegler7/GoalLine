<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PushSubscriptionController extends Controller
{
    /**
     * Remember this device for push. Re-subscribing the same device moves it to whoever is logged in now.
     */
    public function store(Request $request): Response
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aes128gcm,aesgcm'],
        ]);

        PushSubscription::firstOrNew(['endpoint_hash' => hash('sha256', $validated['endpoint'])])
            ->fill([
                'endpoint' => $validated['endpoint'],
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
            ])
            ->forceFill(['user_id' => $request->user()->id])
            ->save();

        return response()->noContent();
    }

    /**
     * Forget this device: push turned off here.
     */
    public function destroy(Request $request): Response
    {
        $validated = $request->validate(['endpoint' => ['required', 'string', 'max:2000']]);

        $request->user()->pushSubscriptions()->where('endpoint_hash', hash('sha256', $validated['endpoint']))->delete();

        return response()->noContent();
    }
}
