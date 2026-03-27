<?php

namespace Romansh\LaravelCreemAgent\Agent;

interface ParsesAgentMessages
{
    public function parse(string $message): array;
}