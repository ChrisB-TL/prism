<?php

declare(strict_types=1);

namespace PrismPHP\Prism\Providers\Ollama\Maps;

use Exception;
use PrismPHP\Prism\Contracts\Message;
use PrismPHP\Prism\ValueObjects\Messages\AssistantMessage;
use PrismPHP\Prism\ValueObjects\Messages\Support\Image;
use PrismPHP\Prism\ValueObjects\Messages\SystemMessage;
use PrismPHP\Prism\ValueObjects\Messages\ToolResultMessage;
use PrismPHP\Prism\ValueObjects\Messages\UserMessage;

class MessageMap
{
    /** @var array<int, array{role: string, content: string}> */
    protected array $mappedMessages = [];

    /**
     * @param  array<int, Message>  $messages
     */
    public function __construct(
        protected array $messages,
    ) {}

    /**
     * @return array<int, array{role: string, content: string, images?: array<string>}>
     */
    public function map(): array
    {
        array_map(
            fn (Message $message) => $this->mapMessage($message),
            $this->messages
        );

        return array_values($this->mappedMessages);
    }

    protected function mapMessage(Message $message): void
    {
        match ($message::class) {
            UserMessage::class => $this->mapUserMessage($message),
            AssistantMessage::class => $this->mapAssistantMessage($message),
            ToolResultMessage::class => $this->mapToolResultMessage($message),
            SystemMessage::class => $this->mapSystemMessage($message),
            default => throw new Exception('Could not map message type '.$message::class),
        };
    }

    protected function mapSystemMessage(SystemMessage $message): void
    {
        $this->mappedMessages[] = [
            'role' => 'system',
            'content' => $message->content,
        ];
    }

    protected function mapToolResultMessage(ToolResultMessage $message): void
    {
        foreach ($message->toolResults as $toolResult) {
            $this->mappedMessages[] = [
                'role' => 'tool',
                'content' => is_string($toolResult->result)
                    ? $toolResult->result
                    : (json_encode($toolResult->result) ?: ''),
            ];
        }
    }

    protected function mapUserMessage(UserMessage $message): void
    {
        $mapped = [
            'role' => 'user',
            'content' => $message->text(),
        ];

        if ($images = $message->images()) {
            $mapped['images'] = array_map(
                fn (Image $image): string => $image->image,
                $images
            );
        }

        $this->mappedMessages[] = $mapped;
    }

    protected function mapAssistantMessage(AssistantMessage $message): void
    {
        $this->mappedMessages[] = [
            'role' => 'assistant',
            'content' => $message->content,
        ];
    }
}
