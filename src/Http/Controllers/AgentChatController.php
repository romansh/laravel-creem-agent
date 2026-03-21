<?php

namespace Romansh\LaravelCreemAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Romansh\LaravelCreemAgent\Agent\AgentManager;

class AgentChatController extends Controller
{
    public function handle(Request $request, AgentManager $agent): JsonResponse
    {
        $message = $request->input('message', '');

        if (empty($message)) {
            return response()->json(['error' => 'Message is required'], 422);
        }

        $response = $agent->handleMessage($message);

        return response()->json([
            'response' => $response,
            'store' => $agent->getActiveStore(),
        ]);
    }
}
