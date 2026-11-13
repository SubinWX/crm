<?php

namespace App\Support;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Anthropic\Core\Exceptions\PermissionDeniedException;
use Anthropic\Core\Exceptions\RateLimitException;

/**
 * The AI assistant: sends a conversation to Claude and returns the answer.
 * It is off until a Super Admin saves an Anthropic API key on the Assistant page.
 * Only the conversation typed on that page is sent; nothing from the CRM's records is.
 */
class Assistant
{
    public const MODEL = 'claude-opus-5-5';

    protected const SYSTEM = <<<'TXT'
        You are the assistant built into the internal CRM of a small agency. The people who talk to you are the agency's own team: owners, managers and staff. They use you for everyday office work, such as drafting and improving emails, proposals and replies to clients, summarising or rewording text they paste in, planning tasks, and answering general questions.

        You cannot see the CRM's records (clients, invoices, leave, projects) or take actions in it. When someone asks about their own data, say so plainly and suggest where in the CRM they would find it, or ask them to paste the details in.

        Write the way a helpful colleague would: clear, direct and in plain language. Keep answers as short as the question allows. When you draft something to send, give the draft ready to copy.
        TXT;

    public static function enabled(): bool
    {
        return filled(MailSettings::secret('assistant_api_key'));
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages  the conversation so far, oldest first, ending with the person's message
     *
     * @throws \RuntimeException with a message that can be shown to the person
     */
    public function reply(array $messages): string
    {
        $key = MailSettings::secret('assistant_api_key');
        if (blank($key)) {
            throw new \RuntimeException('The assistant is not set up yet. A Super Admin needs to add the API key.');
        }

        try {
            $response = (new Client(apiKey: $key, requestOptions: ['timeout' => 170.0, 'maxRetries' => 1]))->beta->messages->create(
                model: self::MODEL,
                maxTokens: 8000,
                system: self::SYSTEM,
                messages: $messages,
                // If the model declines a request, the API retries it once on another model by itself.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (AuthenticationException|PermissionDeniedException) {
            throw new \RuntimeException('The API key was not accepted. A Super Admin needs to check it on this page.');
        } catch (RateLimitException) {
            throw new \RuntimeException('The assistant is busy or the account has reached its limit. Try again in a minute.');
        } catch (APIConnectionException) {
            throw new \RuntimeException('Could not reach the assistant. Check the internet connection and try again.');
        } catch (APIStatusException $e) {
            report($e);

            throw new \RuntimeException('The assistant could not answer just now. Try again in a moment.');
        }

        if ($response->stopReason === 'refusal') {
            return 'I can’t help with that request.';
        }

        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        if ($response->stopReason === 'max_tokens') {
            $text .= "\n\n(The answer was cut short because it got very long. Ask me to continue.)";
        }

        return trim($text) !== '' ? trim($text) : 'I did not get an answer. Please try again.';
    }
}
