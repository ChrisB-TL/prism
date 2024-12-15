<?php

declare(strict_types=1);

namespace Tests\Providers\Gemini;

use EchoLabs\Prism\Enums\FinishReason;
use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\ValueObjects\Messages\Support\Image;
use EchoLabs\Prism\ValueObjects\Messages\UserMessage;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\FixtureResponse;

beforeEach(function (): void {
    config()->set('prism.providers.gemini.api_key', env('GEMINI_API_KEY', 'gsk_00000000000000000000000000000000'));
});

describe('Text generation for Gemini', function (): void {
    it('can generate text with a prompt', function (): void {
        FixtureResponse::fakeResponseSequence('*', 'gemini/generate-text-with-a-prompt');

        $response = Prism::text()
            ->using(Provider::Gemini, 'gemini-1.5-flash')
            ->withPrompt('Who are you?')
            ->withMaxTokens(10)
            ->generate();

        expect($response->text)->toBe(
            "I am a large language model, trained by Google.  I am an AI, and I don't have a name, feelings, or personal experiences.  My purpose is to process information and respond to a wide range of prompts and questions in a helpful and informative way.\n"
        )
            ->and($response->usage->promptTokens)->toBe(4)
            ->and($response->usage->completionTokens)->toBe(57)
            ->and($response->response)->toBe([
                'id' => null,
                'model' => 'gemini-1.5-flash',
            ])
            ->and($response->finishReason)->toBe(FinishReason::Stop);
    });

    it('can generate text with a system prompt', function (): void {
        FixtureResponse::fakeResponseSequence('*', 'gemini/generate-text-with-system-prompt');

        $response = Prism::text()
            ->using(Provider::Gemini, 'gemini-1.5-flash')
            ->withSystemPrompt('You are a helpful AI assistant named Prism generated by echolabs')
            ->withPrompt('Who are you?')
            ->generate();

        expect($response->text)->toBe('I am Prism, a helpful AI assistant created by echo labs.')
            ->and($response->usage->promptTokens)->toBe(17)
            ->and($response->usage->completionTokens)->toBe(14)
            ->and($response->response)->toBe([
                'id' => null,
                'model' => 'gemini-1.5-flash',
            ])
            ->and($response->finishReason)->toBe(FinishReason::Stop);
    });
});

describe('Image support with Gemini', function (): void {
    it('can send images from path', function (): void {
        FixtureResponse::fakeResponseSequence('*', 'gemini/image-detection');

        $response = Prism::text()
            ->using(Provider::Gemini, 'gemini-1.5-flash')
            ->withMessages([
                new UserMessage(
                    'What is this image',
                    additionalContent: [
                        Image::fromPath('tests/Fixtures/test-image.png'),
                    ],
                ),
            ])
            ->generate();

        // Assert response
        expect($response->text)->toBe("That's an illustration of a **diamond**.  More specifically, it's a stylized, geometric representation of a diamond, often used as an icon or symbol")
            ->and($response->usage->promptTokens)->toBe(263)
            ->and($response->usage->completionTokens)->toBe(35)
            ->and($response->response)->toBe([
                'id' => null,
                'model' => 'gemini-1.5-flash',
            ])
            ->and($response->finishReason)->toBe(FinishReason::Stop);

        // Assert request format
        Http::assertSent(function (Request $request): bool {
            $message = $request->data()['contents'][0]['parts'];

            expect($message[0])->toBe([
                'text' => 'What is this image',
            ]);

            expect($message[1]['inline_data'])->toHaveKeys(['mime_type', 'data']);
            expect($message[1]['inline_data']['mime_type'])->toBe('image/png');
            expect($message[1]['inline_data']['data'])->toBe(
                base64_encode(file_get_contents('tests/Fixtures/test-image.png'))
            );

            return true;
        });
    });

    it('can send images from base64', function (): void {
        FixtureResponse::fakeResponseSequence('*', 'gemini/image-detection');

        $response = Prism::text()
            ->using(Provider::Gemini, 'gemini-1.5-flash')
            ->withMessages([
                new UserMessage(
                    'What is this image',
                    additionalContent: [
                        Image::fromBase64(
                            base64_encode(file_get_contents('tests/Fixtures/test-image.png')),
                            'image/png'
                        ),
                    ],
                ),
            ])
            ->generate();

        Http::assertSent(function (Request $request): bool {
            $message = $request->data()['contents'][0]['parts'];

            expect($message[0])->toBe([
                'text' => 'What is this image',
            ]);

            expect($message[1]['inline_data'])->toHaveKeys(['mime_type', 'data']);
            expect($message[1]['inline_data']['mime_type'])->toBe('image/png');
            expect($message[1]['inline_data']['data'])->toBe(
                base64_encode(file_get_contents('tests/Fixtures/test-image.png'))
            );

            return true;
        });
    });

    it('can send images from url', function (): void {
        FixtureResponse::fakeResponseSequence('*', 'gemini/image-detection');

        $image = 'https://storage.echolabs.dev/api/v1/buckets/public/objects/download?preview=true&prefix=test-image.png';

        $response = Prism::text()
            ->using(Provider::Gemini, 'gemini-1.5-flash')
            ->withMessages([
                new UserMessage(
                    'What is this image',
                    additionalContent: [
                        Image::fromUrl($image, 'image/png'),
                    ],
                ),
            ])
            ->generate();

        Http::assertSent(function (Request $request) use ($image): bool {
            $message = $request->data()['contents'][0]['parts'];

            expect($message[0])->toBe([
                'text' => 'What is this image',
            ]);

            expect($message[1]['inline_data'])->toHaveKeys(['mime_type', 'data']);
            expect($message[1]['inline_data']['mime_type'])->toBe('image/png');
            expect($message[1]['inline_data']['data'])->toBe(
                base64_encode(file_get_contents($image))
            );

            return true;
        });
    });
});
