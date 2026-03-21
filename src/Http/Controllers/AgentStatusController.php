<?php

namespace Romansh\LaravelCreemAgent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Romansh\LaravelCreemAgent\Heartbeat\StateManager;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class AgentStatusController extends Controller
{
    public function index(CreemCliManager $cli): JsonResponse
    {
        $stateManager = new StateManager();
        $stores = config('creem-agent.stores', []);
        $status = [];

        foreach ($stores as $name => $config) {
            $state = $stateManager->load($name);
            $status[$name] = [
                'last_heartbeat' => $state['lastCheckAt'],
                'customers' => $state['customerCount'],
                'transactions' => $state['transactionCount'],
                'subscriptions' => $state['subscriptions'],
            ];
        }

        return response()->json([
            'cli_backend' => $cli->isNativeCliAvailable() ? 'native' : 'artisan',
            'active_store' => $cli->getActiveStore(),
            'stores' => $status,
        ]);
    }
}
