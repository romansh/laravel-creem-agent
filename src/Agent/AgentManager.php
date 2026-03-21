<?php

namespace Romansh\LaravelCreemAgent\Agent;

use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class AgentManager
{
    private CommandParser $parser;
    private IntentRouter $router;

    public function __construct(private CreemCliManager $cli)
    {
        $this->parser = new CommandParser();
        $this->router = new IntentRouter($cli);
    }

    public function handleMessage(string $message): string
    {
        $intent = $this->parser->parse($message);
        return $this->router->route($intent);
    }

    public function getActiveStore(): string
    {
        return $this->cli->getActiveStore();
    }

    public function setActiveStore(string $store): void
    {
        $this->cli->setActiveStore($store);
    }
}
