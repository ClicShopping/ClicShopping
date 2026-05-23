<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use InvalidArgumentException;

/**
 * Class Event
 *
 * Represents a Server-Sent Event (SSE).
 * Parses and exposes the individual fields of an SSE message.
 */
class Event
{
  private string $data = '';
  private ?string $name = null;
  private ?string $id = null;
  private ?int $retry = null;
  private array $rawLines = [];

  /**
   * Event constructor.
   *
   * @param string $eventData Raw SSE message as a string.
   * @throws InvalidArgumentException If input is empty or lacks a data field.
   */
  public function __construct(string $eventData)
  {
    if (empty(trim($eventData))) {
      throw new InvalidArgumentException('Event data cannot be empty');
    }

    $this->parseEventData($eventData);

    if (empty($this->data)) {
      throw new InvalidArgumentException('Event must contain data field');
    }
  }

  /**
   * Parses the raw SSE message into structured fields.
   *
   * @param string $eventData Raw event string.
   */
  private function parseEventData(string $eventData): void
  {
    $lines = explode("\n", $eventData);

    foreach ($lines as $line) {
      $line = trim($line);
      if ($line === '') {
        continue;
      }

      $this->rawLines[] = $line;

      if (str_starts_with($line, 'data:')) {
        $this->parseDataLine($line);
      } elseif (str_contains($line, ':')) {
        $this->parseFieldLine($line);
      }
    }
  }

  /**
   * Appends the content of a "data:" line to the data property.
   *
   * @param string $line The data line.
   */
  private function parseDataLine(string $line): void
  {
    $dataContent = substr($line, 5);
    $this->data .= $dataContent;
  }

  /**
   * Parses and assigns known fields (id, event/name, retry).
   *
   * @param string $line Field line with format "field: value".
   */
  private function parseFieldLine(string $line): void
  {
    [$field, $value] = explode(':', $line, 2);
    $field = trim($field);
    $value = trim($value);

    switch ($field) {
      case 'id':
        $this->id = $value;
        break;
      case 'event':
      case 'name':
        $this->name = $value;
        break;
      case 'retry':
        $this->retry = (int)$value;
        break;
      default:
        break;
    }
  }

  /** Returns the event data. */
  public function getData(): string
  {
    return $this->data;
  }

  /** Returns the event name (if any). */
  public function getName(): ?string
  {
    return $this->name;
  }

  /** Returns the event ID (if any). */
  public function getId(): ?string
  {
    return $this->id;
  }

  /** Returns the retry value (if any). */
  public function getRetry(): ?int
  {
    return $this->retry;
  }

  /** Returns the raw input lines of the event. */
  public function getRawLines(): array
  {
    return $this->rawLines;
  }

  /** True if event contains data. */
  public function hasData(): bool
  {
    return !empty($this->data);
  }

  /** True if event has a name. */
  public function hasName(): bool
  {
    return $this->name !== null;
  }

  /** True if event has an ID. */
  public function hasId(): bool
  {
    return $this->id !== null;
  }

  /**
   * Attempts to decode data as JSON.
   *
   * @return array|null Decoded array or null if invalid.
   */
  public function getDataAsJson(): ?array
  {
    $decoded = json_decode($this->data, true);
    return $decoded !== null ? $decoded : null;
  }

  /** True if event data is valid JSON. */
  public function isJsonData(): bool
  {
    return $this->getDataAsJson() !== null;
  }

  /**
   * Serializes the event back into SSE format.
   *
   * @return string SSE-formatted string.
   */
  public function __toString(): string
  {
    $output = [];

    if ($this->id) {
      $output[] = "id: {$this->id}";
    }

    if ($this->name) {
      $output[] = "event: {$this->name}";
    }

    if ($this->retry !== null) {
      $output[] = "retry: {$this->retry}";
    }

    $output[] = "data: {$this->data}";
    $output[] = "";

    return implode("\n", $output);
  }
}
