<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\CoreAI\Memory\SubConversationMemory;

use ClicShopping\AI\DomainsAI\Semantic\Agent\SemanticAgent;
use ClicShopping\AI\DomainsAI\Semantic\Processor\EnglishQueryNormalizer;
use ClicShopping\AI\Infrastructure\Orm\DoctrineOrm;
use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\OM\CLICSHOPPING;

/**
 * ConversationTurnReader
 *
 * Reads the last conversation turns from the persisted memory store, in chronological order.
 *
 * Short-term memory lives in the request, and one chat message is one HTTP request, so an
 * in-process history is always empty in real usage. Reference resolution needs the turns the
 * user actually typed to tell a real follow-up from a self-contained question, hence this
 * cross-request read. Fail-safe: any error yields no turns, which makes the resolver abstain.
 *
 * Turns are returned in ENGLISH: the whole AI process runs in English (AGENTS.md), and the
 * prompts that consume them are English. Stored turns carry the user's language, so each is
 * passed through the shared translator, whose cache makes the repeated turns of a conversation
 * free after their first use.
 */
class ConversationTurnReader
{
  /** Per-turn cap before translation: an answer's opening is enough to show where an entity came from. */
  private const MAX_ANSWER_CHARS = 400;

  /** Rows fetched per wanted turn, to absorb the duplicates a chunked answer leaves behind. */
  private const DUPLICATE_ALLOWANCE = 4;

  private MemoryInteractionFormatter $formatter;
  private SecurityLogger $logger;
  /** @var callable(string):string */
  private $translator;

  /**
   * @param callable|null $translator Optional (string):string translator — injected in tests.
   *                                  Defaults to the shared English translator.
   */
  public function __construct(
    private string $userId,
    private int $languageId,
    private bool $debug = false,
    ?MemoryInteractionFormatter $formatter = null,
    ?SecurityLogger $logger = null,
    ?callable $translator = null
  ) {
    $this->formatter = $formatter ?? new MemoryInteractionFormatter();
    $this->logger = $logger ?? new SecurityLogger();
    $this->translator = $translator ?? static fn(string $text): string => EnglishQueryNormalizer::normalize($text);
  }

  /**
   * @param int $limit Maximum number of turns
   * @return array<int, array{user: string, assistant: string}> Chronological, oldest first
   */
  public function getRecentTurns(int $limit = 3): array
  {
    if ($limit < 1) {
      return [];
    }

    $fetch = $limit * self::DUPLICATE_ALLOWANCE;

    try {
      $table = CLICSHOPPING::getConfig('db_table_prefix', 'DB') . 'rag_conversation_memory_embedding';

      // Over-fetch, then keep one row per distinct user message: a chunked answer produces several
      // rows repeating the same question, and those duplicates would crowd out the older turns.
      // Do NOT filter on chunknumber — interactions are written with the column's 128 fallback
      // (MariaDBVectorStore:160), never 0, so such a filter silently returns nothing.
      $sql = "
        SELECT content
        FROM {$table}
        WHERE user_id = :user_id
        AND language_id = :language_id
        AND content IS NOT NULL
        ORDER BY date_modified DESC
        LIMIT {$fetch}
      ";

      $rows = DoctrineOrm::select($sql, [
        'user_id' => $this->userId,
        'language_id' => $this->languageId,
      ]);

      $turns = [];
      $seen = [];
      foreach ($rows as $row) {
        $turn = $this->formatter->parseStoredInteraction((string)($row['content'] ?? ''));
        if ($turn['user'] === '' || isset($seen[$turn['user']])) {
          continue;
        }

        $seen[$turn['user']] = true;
        $turns[] = [
          'user' => $this->toEnglish($turn['user']),
          'assistant' => $this->toEnglish(mb_substr($turn['assistant'], 0, self::MAX_ANSWER_CHARS)),
        ];

        if (count($turns) >= $limit) {
          break;
        }
      }

      return array_reverse($turns);
    } catch (\Throwable $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent('ConversationTurnReader error: ' . $e->getMessage(), 'warning');
      }

      return [];
    }
  }

  /**
   * Translate one turn fragment. A translation failure degrades to the stored text rather than
   * dropping the turn: a turn in the wrong language still shows what the user was focused on.
   */
  private function toEnglish(string $text): string
  {
    if (trim($text) === '') {
      return '';
    }

    try {
      $translated = trim(($this->translator)($text));

      return $translated !== '' ? $translated : $text;
    } catch (\Throwable $e) {
      if ($this->debug) {
        $this->logger->logSecurityEvent('ConversationTurnReader translation failed: ' . $e->getMessage(), 'warning');
      }

      return $text;
    }
  }
}
