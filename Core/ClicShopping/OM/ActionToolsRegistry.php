<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;


use ClicShopping\OM\Interfaces\ActionToolsInterface;
use DirectoryIterator;


/**
 * Registre et gestion des outils d'action pour ChatGPT.
 */
class ActionToolsRegistry
{

  // Définir le chemin vers le dossier ActionTools
  private const TOOLS_DIR = CLICSHOPPING_BASE_DIR . 'Apps/ChatGpt/Classes/Tools/Action/';
  private const NAMESPACE_PREFIX = 'ClicShopping\\Apps\\ChatGpt\\Classes\\Tools\\Action\\';

  public function __construct()
  {
    $this->loadTools();
  }

  private function loadTools(): void
  {
    if ($this->isLoaded) return;

    if (!is_dir(self::TOOLS_DIR)) {
      $this->isLoaded = true;
      return;
    }

    foreach (new DirectoryIterator(self::TOOLS_DIR) as $fileInfo) {
      if ($fileInfo->isDot() || $fileInfo->isDir() || $fileInfo->getExtension() !== 'php') {
        continue;
      }

      $fullClass = self::NAMESPACE_PREFIX . $fileInfo->getBasename('.php');

      if (class_exists($fullClass) && in_array(ActionToolsInterface::class, class_implements($fullClass))) {
        try {
          /** @var ActionToolsInterface $toolInstance */
          $toolInstance = new $fullClass();
          $toolName = $toolInstance->getName();
          $this->tools[$toolName] = $fullClass;
        } catch (\Exception $e) {
          error_log("Failed to load Action Tool {$fullClass}: " . $e->getMessage());
        }
      }
    }

    $this->isLoaded = true;
  }

  public function getToolClass(string $toolName): ?string
  {
    return $this->tools[$toolName] ?? null;
  }

  public function getToolSchemas(): array
  {
    $schemas = [];
    foreach ($this->tools as $class) {
      /** @var ActionToolsInterface $toolInstance */
      $toolInstance = new $class();
      $schemas[] = [
        'name' => $toolInstance->getName(),
        'description' => $toolInstance->getDescription(),
        'parameters' => $toolInstance->getParametersSchema()
      ];
    }
    return $schemas;
  }
}
