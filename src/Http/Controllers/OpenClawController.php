<?php

namespace Romansh\LaravelCreemAgent\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Artisan;

class OpenClawController extends Controller
{
    public function heartbeat(Request $request)
    {
        if (!config('creem-agent.openclaw.enabled', false)) {
            return response('OpenClaw integration disabled', 403);
        }

        $secret = $request->header('X-OpenClaw-Shared-Secret') ?? $request->input('secret');
        $expected = config('creem-agent.openclaw.shared_secret');

        if (empty($expected) || empty($secret) || !hash_equals((string) $expected, (string) $secret)) {
            return response('Unauthorized', 401);
        }

        // Trigger a full heartbeat run. Use --all-stores to match scheduler behavior.
        Artisan::call('creem-agent:heartbeat', ['--all-stores' => true]);

        return response()->json(['ok' => true]);
    }
}
