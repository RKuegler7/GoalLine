<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\BadRequestException;
use Anthropic\Core\Exceptions\NotFoundException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\AiFallback;
use Throwable;

/**
 * One tiny request to Claude with the configured key and model, to tell plainly whether the AI coach works. It never
 * prints or logs the key, and costs a handful of tokens.
 */
class AiCheck
{
    /**
     * @return array{ok: bool, message: string}
     */
    public function run(): array
    {
        $model = (string) config('services.anthropic.model');

        if (blank(config('services.anthropic.key'))) {
            return ['ok' => false, 'message' => 'No ANTHROPIC_API_KEY is set, so the coach uses its built-in questions and starter plans. Set it in the environment properties.'];
        }

        if ($model === '') {
            return ['ok' => false, 'message' => 'No ANTHROPIC_MODEL is set. Set it in the environment properties.'];
        }

        try {
            $message = $this->send($model);
        } catch (AuthenticationException) {
            return ['ok' => false, 'message' => 'The API key was rejected (401). Check ANTHROPIC_API_KEY: it may be mistyped, revoked, or from another account.'];
        } catch (PermissionDeniedException) {
            return ['ok' => false, 'message' => "The key works but isn't allowed to use {$model} (403). Check the key's workspace and model access."];
        } catch (NotFoundException) {
            return ['ok' => false, 'message' => "The model {$model} wasn't found (404). Check ANTHROPIC_MODEL."];
        } catch (RateLimitException) {
            return ['ok' => false, 'message' => 'The key works, but the account is rate limited right now (429). Try again in a minute, and check its usage limits.'];
        } catch (BadRequestException $exception) {
            return ['ok' => false, 'message' => 'The request was refused (400): '.AiFallback::safe($exception->getMessage()).' A low credit balance shows up here too.'];
        } catch (APIStatusException $exception) {
            return ['ok' => false, 'message' => "Claude returned an error ({$exception->status}). Try again shortly: ".AiFallback::safe($exception->getMessage())];
        } catch (APITimeoutException|APIConnectionException) {
            return ['ok' => false, 'message' => "Couldn't reach the Claude API (a timeout or network problem). Check the server's internet access and try again."];
        } catch (Throwable $exception) {
            return ['ok' => false, 'message' => 'The check failed: '.AiFallback::safe($exception->getMessage())];
        }

        return ['ok' => true, 'message' => "It works: {$model} answered with this key ({$message->usage->inputTokens} tokens in, {$message->usage->outputTokens} out)."];
    }

    /**
     * The smallest useful request: one short user message, low effort, a small output cap.
     */
    protected function send(string $model): mixed
    {
        return app(Client::class)->beta->messages->create(
            model: $model,
            maxTokens: 64,
            messages: [['role' => 'user', 'content' => 'Reply with the single word: ready']],
            outputConfig: ['effort' => 'low'],
        );
    }
}
