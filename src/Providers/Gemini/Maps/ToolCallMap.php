<?php

declare(strict_types=1);

namespace PrismPHP\Prism\Providers\Gemini\Maps;

use PrismPHP\Prism\ValueObjects\ToolCall;

class ToolCallMap
{
    /**
     * @param  array<array<string, mixed>>  $toolCalls
     * @return array<ToolCall>
     */
    public static function map(array $toolCalls): array
    {
        if ($toolCalls === []) {
            return [];
        }

        return array_map(fn (array $toolCall): \PrismPHP\Prism\ValueObjects\ToolCall => new ToolCall(
            id: data_get($toolCall, 'functionCall.name'),
            name: data_get($toolCall, 'functionCall.name'),
            arguments: data_get($toolCall, 'functionCall.args'),
        ), $toolCalls);
    }
}
