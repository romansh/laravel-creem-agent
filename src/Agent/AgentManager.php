<?php

namespace Romansh\LaravelCreemAgent\Agent;

use Illuminate\Support\Facades\Log;
use Romansh\LaravelCreemAgent\Cli\CreemCliManager;

class AgentManager
{
    private ParsesAgentMessages $ruleParser;
    private ParsesAgentMessages $llmParser;
    private IntentRouter $router;

    public function __construct(
        private CreemCliManager $cli,
        ?ParsesAgentMessages $ruleParser = null,
        ?ParsesAgentMessages $llmParser = null,
        ?IntentRouter $router = null,
    ) {
        $this->ruleParser = $ruleParser ?? new CommandParser();
        $this->llmParser = $llmParser ?? new LlmCommandParser();
        $this->router = $router ?? new IntentRouter($cli);
    }

    public function handleMessage(string $message, array $context = []): string
    {
        $source = $context['source'] ?? null;
        $ruleIntent = $this->ruleParser->parse($message);

        if ($source === 'telegram') {
            if (($ruleIntent['intent'] ?? 'unknown') !== 'unknown') {
                return $this->router->route($ruleIntent);
            }
        }

        $llmIntent = null;

        if (config('creem-agent.llm.enabled', true)) {
            try {
                $llmIntent = $this->llmParser->parse($message);

                if (($llmIntent['intent'] ?? 'unknown') !== 'unknown') {
                    return $this->router->route($llmIntent);
                }
            } catch (\Throwable $e) {
                Log::warning('[CreemAgent] LLM parser failed, falling back to rule parser.', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if (($ruleIntent['intent'] ?? 'unknown') !== 'unknown') {
            return $this->router->route($ruleIntent);
        }

        return $this->router->route($llmIntent ?? $ruleIntent);
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
