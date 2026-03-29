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
        $llmIntent = null;

        // If source is Telegram, avoid using the LLM to prevent token usage
        $source = $context['source'] ?? null;
        if ($source === 'telegram') {
            $ruleIntent = $this->ruleParser->parse($message);

            if (($ruleIntent['intent'] ?? 'unknown') !== 'unknown') {
                return $this->router->route($ruleIntent);
            }

            return $this->router->route($ruleIntent);
        }

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

        $ruleIntent = $this->ruleParser->parse($message);

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
