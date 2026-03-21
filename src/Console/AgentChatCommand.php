<?php

namespace Romansh\LaravelCreemAgent\Console;

use Illuminate\Console\Command;
use Romansh\LaravelCreemAgent\Agent\AgentManager;

class AgentChatCommand extends Command
{
    protected $signature = 'creem-agent:chat {message : Natural language message}';
    protected $description = 'Chat with the Creem Agent';

    public function handle(AgentManager $agent): int
    {
        $message = $this->argument('message');
        $response = $agent->handleMessage($message);

        $this->line($response);
        return self::SUCCESS;
    }
}
