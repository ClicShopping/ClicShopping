<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;


use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\CoreAI\Memory\ConversationHistory;
use LLPhant\Chat\Message;

/**
 * ShortTermMemoryManager Class
 *
 * Responsible for managing short-term conversational history.
 * Separated from ConversationMemory to follow Single Responsibility Principle.
 *
 * Responsibilities:
 * - Manage ConversationHistory (LLPhant)
 * - Add and retrieve messages
 * - Enforce size limits (maxHistorySize)
 * - Automatic trimming when limit reached
 * - Clear history when needed
 */

class ShortTermMemoryManager
{
  private ConversationHistory $conversationHistory;
  private SecurityLogger $logger;
  private bool $debug;
  private int $maxHistorySize;

  /**
   * Constructor
   *
   * @param int $maxHistorySize Maximum number of messages to keep
   * @param bool $debug Enable debug logging
   */
  public function __construct(int $maxHistorySize = 10, bool $debug = false)
  {
    $this->maxHistorySize = $maxHistorySize;
    $this->debug = $debug;
    $this->logger = new SecurityLogger();
    $this->conversationHistory = new ConversationHistory();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "ShortTermMemoryManager initialized with maxHistorySize={$maxHistorySize}",
        'info'
      );
    }
  }

  /**
   * Add a message to short-term history
   *
   * @param Message $message Message to add
   * @return void
   */
  public function addMessage(Message $message): void
  {
    $this->conversationHistory->addMessage($message);

    // Trim if necessary
    $this->trimHistory();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Message added to short-term history. Current size: " . count($this->conversationHistory->getMessages()),
        'info'
      );
    }
  }

  /**
   * Get recent messages from history
   *
   * @param int $limit Maximum number of messages to return
   * @return array Array of Message objects
   */
  public function getRecentMessages(int $limit = 5): array
  {
    $messages = $this->conversationHistory->getMessages();

    // Return last N messages
    $recentMessages = array_slice($messages, -$limit);

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Retrieved {$limit} recent messages from short-term history",
        'info'
      );
    }

    return $recentMessages;
  }

  /**
   * Get all messages from history
   *
   * @return array Array of Message objects
   */
  public function getAllMessages(): array
  {
    return $this->conversationHistory->getMessages();
  }

  /**
   * Clear all history
   *
   * @return void
   */
  public function clearHistory(): void
  {
    // Create new instance to clear
    $this->conversationHistory = new ConversationHistory();

    if ($this->debug) {
      $this->logger->logSecurityEvent(
        "Short-term history cleared",
        'info'
      );
    }
  }

  /**
   * Get the number of messages in history
   *
   * @return int Number of messages
   */
  public function getMessageCount(): int
  {
    return count($this->conversationHistory->getMessages());
  }

  /**
   * Trim history to maxHistorySize (FIFO)
   *
   * @return void
   */
  private function trimHistory(): void
  {
    $messages = $this->conversationHistory->getMessages();
    $currentSize = count($messages);

    if ($currentSize > $this->maxHistorySize) {
      // Keep only the last maxHistorySize messages
      $trimmedMessages = array_slice($messages, -$this->maxHistorySize);

      // Recreate history with trimmed messages
      $this->conversationHistory = new ConversationHistory();
      foreach ($trimmedMessages as $message) {
        $this->conversationHistory->addMessage($message);
      }

      if ($this->debug) {
        $removed = $currentSize - $this->maxHistorySize;
        $this->logger->logSecurityEvent(
          "History trimmed: removed {$removed} old messages (FIFO)",
          'info'
        );
      }
    }
  }

  /**
   * Get the ConversationHistory instance (for compatibility)
   *
   * @return ConversationHistory
   */
  public function getConversationHistory(): ConversationHistory
  {
    return $this->conversationHistory;
  }
}
