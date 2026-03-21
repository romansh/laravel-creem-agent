<?php

namespace Romansh\LaravelCreemAgent\Support;

class OpenClawTelegramConfigBuilder
{
    public function __construct(private TelegramModeResolver $modeResolver) {}

    public function hasBotToken(): bool
    {
        return $this->botToken() !== null;
    }

    public function build(): array
    {
        $telegram = [
            'enabled' => true,
            'botToken' => $this->botToken() ?? '__SET_OPENCLAW_TELEGRAM_BOT_TOKEN__',
            'dmPolicy' => (string) config('creem-agent.openclaw.telegram.dm_policy', 'pairing'),
            'groupPolicy' => (string) config('creem-agent.openclaw.telegram.group_policy', 'allowlist'),
            'groups' => [
                '*' => [
                    'requireMention' => (bool) config('creem-agent.openclaw.telegram.require_mention', true),
                ],
            ],
        ];

        $allowFrom = $this->csvList(config('creem-agent.openclaw.telegram.allow_from'));
        if ($allowFrom !== []) {
            $telegram['allowFrom'] = $allowFrom;
        }

        $groupAllowFrom = $this->csvList(config('creem-agent.openclaw.telegram.group_allow_from'));
        if ($groupAllowFrom !== []) {
            $telegram['groupAllowFrom'] = $groupAllowFrom;
        }

        return [
            'channels' => [
                'telegram' => $telegram,
            ],
        ];
    }

    public function render(string $format = 'snippet'): string
    {
        $format = strtolower($format);
        $config = $this->build();

        if ($format === 'json') {
            return (string) json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $this->renderValue($config).PHP_EOL;
    }

    public function openClawOwnsTelegram(): bool
    {
        return $this->modeResolver->usesOpenClawGateway();
    }

    private function botToken(): ?string
    {
        $token = trim((string) config('creem-agent.openclaw.telegram.bot_token', ''));

        return $token !== '' ? $token : null;
    }

    private function csvList(mixed $value): array
    {
        if (!is_string($value) || trim($value) === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value)
        ), static fn (string $item): bool => $item !== ''));
    }

    private function renderValue(mixed $value, int $depth = 0): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (!is_array($value)) {
            return 'null';
        }

        if ($value === []) {
            return '{}';
        }

        $indent = str_repeat('    ', $depth);
        $childIndent = str_repeat('    ', $depth + 1);
        $isAssoc = array_keys($value) !== range(0, count($value) - 1);

        if (!$isAssoc) {
            $lines = array_map(fn (mixed $item): string => $childIndent.$this->renderValue($item, $depth + 1), $value);

            return "[\n".implode(",\n", $lines)."\n{$indent}]";
        }

        $lines = [];
        foreach ($value as $key => $item) {
            $renderedKey = preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', (string) $key)
                ? (string) $key
                : (string) json_encode((string) $key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            $lines[] = $childIndent.$renderedKey.': '.$this->renderValue($item, $depth + 1);
        }

        return "{\n".implode(",\n", $lines)."\n{$indent}}";
    }
}