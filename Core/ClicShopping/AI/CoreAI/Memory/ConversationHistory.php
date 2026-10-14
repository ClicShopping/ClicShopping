<?php

namespace ClicShopping\AI\CoreAI\Memory;


use LLPhant\Chat\Message;

/**
 * Class ConversationHistory
 *
 * Manages the conversation history for a chat application.
 */

class ConversationHistory
{
  private array $messages = [];

  /**
   * Adds a message to the conversation history.
   *
   * @param Message $message The message to add.
   */
  public function addMessage(Message $message): void
  {
    $this->messages[] = $message;
  }

  /**
   * Adds a user message to the conversation history.
   *
   * @param string $content The content of the user message.
   */
  public function addUserMessage(string $content): void
  {
    $this->messages[] = new Message('user', $content);
  }

  /**
   * Adds an assistant message to the conversation history.
   *
   * @param string $content The content of the assistant message.
   */
  public function addAssistantMessage(string $content): void
  {
    $this->messages[] = new Message('assistant', $content);
  }

  /**
   * Retrieves the conversation history.
   *
   * @return array An array of Message objects representing the conversation history.
   */
  public function getMessages(): array
  {
    return $this->messages;
  }

  /**
   * Clears the conversation history.
   */
  public function clear(): void
  {
    $this->messages = [];
  }
}