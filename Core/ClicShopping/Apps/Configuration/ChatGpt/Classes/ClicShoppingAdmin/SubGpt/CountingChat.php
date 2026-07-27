<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt;

use LLPhant\Chat\ChatInterface;
use LLPhant\Chat\FunctionInfo\FunctionInfo;
use Psr\Http\Message\StreamInterface;

/**
 * CountingChat
 *
 * Transparent {@see ChatInterface} decorator that bumps {@see LlmCallCounter} once per LLM
 * round-trip, then delegates to the wrapped chat. The six generation methods count; the
 * configuration setters (system message, tools, functions, model option) do not — they
 * mutate the pending request without hitting the model. Any provider-specific method not on
 * the interface (getModel/getTotalTokens/getLastResponse/…) is forwarded verbatim via
 * __call, so wrapping is invisible to callers.
 *
 * Instances are created by ProviderManager (and the provider getLLPhantChat() consumers) at
 * the moment each LLphant chat object is built, which is the only place every call path —
 * façade and raw $chat->generateText() — funnels through.
 *
 * @package ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\SubGpt
 */
final class CountingChat implements ChatInterface
{
  public function __construct(private readonly ChatInterface $inner)
  {
  }

  /**
   * Wrap a freshly built chat so its LLM calls are counted. Idempotent (never double-wraps)
   * and a no-op for non-ChatInterface values, so it is safe to apply to the `mixed` returns
   * of the ProviderManager getters (which yield false/null on failure).
   *
   * @param mixed $chat A chat instance, or any value a provider getter may return.
   * @return mixed The counting wrapper for a ChatInterface, otherwise $chat unchanged.
   */
  public static function wrap(mixed $chat): mixed
  {
    if ($chat instanceof self) {
      return $chat;
    }

    if ($chat instanceof ChatInterface) {
      return new self($chat);
    }

    return $chat;
  }

  public function generateText(string $prompt): string
  {
    LlmCallCounter::increment();
    return $this->inner->generateText($prompt);
  }

  public function generateTextOrReturnFunctionToCall(string $prompt): string|array
  {
    LlmCallCounter::increment();
    return $this->inner->generateTextOrReturnFunctionToCall($prompt);
  }

  public function generateStreamOfText(string $prompt): StreamInterface
  {
    LlmCallCounter::increment();
    return $this->inner->generateStreamOfText($prompt);
  }

  public function generateChat(array $messages): string
  {
    LlmCallCounter::increment();
    return $this->inner->generateChat($messages);
  }

  public function generateChatOrReturnFunctionToCall(array $messages): string|array
  {
    LlmCallCounter::increment();
    return $this->inner->generateChatOrReturnFunctionToCall($messages);
  }

  public function generateChatStream(array $messages): StreamInterface
  {
    LlmCallCounter::increment();
    return $this->inner->generateChatStream($messages);
  }

  public function setSystemMessage(string $message): void
  {
    $this->inner->setSystemMessage($message);
  }

  public function setTools(array $tools): void
  {
    $this->inner->setTools($tools);
  }

  public function addTool(FunctionInfo $functionInfo): void
  {
    $this->inner->addTool($functionInfo);
  }

  public function setFunctions(array $functions): void
  {
    $this->inner->setFunctions($functions);
  }

  public function addFunction(FunctionInfo $functionInfo): void
  {
    $this->inner->addFunction($functionInfo);
  }

  public function setModelOption(string $option, mixed $value): void
  {
    $this->inner->setModelOption($option, $value);
  }

  /**
   * Forward any provider-specific method not declared on ChatInterface
   * (getModel / getTotalTokens / getLastResponse / …) to the wrapped chat unchanged.
   *
   * @param array<int, mixed> $arguments
   */
  public function __call(string $name, array $arguments): mixed
  {
    return $this->inner->{$name}(...$arguments);
  }

  /**
   * Real token usage, read from the wrapped chat. Declared rather than left to __call, which
   * method_exists() cannot see — probing callers fell back to a characters/4 estimate.
   *
   * @return mixed Provider response carrying `usage`, or null when the wrapped chat exposes none
   */
  public function getLastResponse(): mixed
  {
    return method_exists($this->inner, 'getLastResponse') ? $this->inner->getLastResponse() : null;
  }
}
