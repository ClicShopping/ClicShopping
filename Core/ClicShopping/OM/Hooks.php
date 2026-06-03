<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use DirectoryIterator;
use ReflectionFunction;
use function call_user_func_array;
use function is_string;

/**
 * The Hooks class abstracts the functionality for managing, registering, and executing hooks within
 * the ClicShopping framework. Hooks are used to provide a flexible and extensible way to execute
 * custom code at various points in the application lifecycle.
 */
class Hooks
{
  protected string|null $site;
  protected array $hooks = [];
  protected array $watches = [];

  /**
   * Constructor method for initializing the object with a site value.
   *
   * @param string|null $site Optional. The site name. If not provided, it defaults to the value returned by CLICSHOPPING::getSite().
   * @return void
   */
  public function __construct(?string $site = null)
  {
    if (!isset($site)) {
      $site = CLICSHOPPING::getSite();
    }

    $this->site = basename($site);
  }

  /**
   * Executes a specified hook and action for a given group and collects the results.
   *
   * @param string $group The group name of the hook.
   * @param string $hook The specific hook to be called.
   * @param array|null $parameters Optional parameters to be passed to the hook/action.
   * @param string|null $action The action to be executed, defaults to 'execute' if not provided.
   * @param string|null $context Optional context identifier (ex: tab, position, etc.) to filter hooks.
   * @return array The results returned by the executed hook actions.
   */
  public function call(string $group, string $hook, ?array $parameters = null, ?string $action = null, ?string $context = null): array
  {
    if (!isset($action)) {
      $action = 'execute';
    }

    if (!isset($this->hooks[$this->site][$group][$hook][$action])) {
      $this->register($group, $hook, $action);
    }

      $calls = [];

      if (isset($this->hooks[$this->site][$group][$hook][$action])) {
        $calls = $this->hooks[$this->site][$group][$hook][$action];
      }

      if (isset($this->watches[$this->site][$group][$hook][$action])) {
        // Filtrer les watches selon le contexte si spécifié
        foreach ($this->watches[$this->site][$group][$hook][$action] as $watchEntry) {
          //Cjekcif it's a structure with context or simple code
          if (is_array($watchEntry) && isset($watchEntry['code'])) {
            // Structure avec contexte
            if ($context !== null) {
              // Si le watch a un contexte défini, il doit correspondre
              if (isset($watchEntry['context']) && $watchEntry['context'] !== $context) {
                continue; // Skip ce watch, le contexte ne correspond pas
              }
              // Si le watch n'a pas de contexte mais qu'on en demande un spécifique, skip aussi
              if (!isset($watchEntry['context']) && $context !== 'global') {
                continue;
              }
            }
            $calls[] = $watchEntry['code'];
          } else {
            // Structure simple (code directement) - rétrocompatibilité
            if ($context === null || $context === 'global') {
              $calls[] = $watchEntry;
            }
          }
        }
      }

      $result = [];

      foreach ($calls as $code) {
        $bait = null;

        if (is_string($code)) {
          $class = Apps::getModuleClass($code, 'Hooks');

          if (empty($class) || !class_exists($class)) {
            continue;
          }

          try {
            $obj = new $class();

            // Passer le contexte à l'objet s'il supporte cette méthode
            if (method_exists($obj, 'setContext') && $context !== null) {
              $obj->setContext($context);
            }

            // Ajouter le contexte aux paramètres
            $contextualParameters = $parameters ?? [];
            if ($context !== null) {
              $contextualParameters['_context'] = $context;
            }

            $bait = $obj->$action($contextualParameters);
          } catch (\Throwable $e) {
            trigger_error('ClicShopping: Hook "' . $code . '"::' . $action . '() failed at runtime: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(), E_USER_WARNING);
            continue;
          }
        } else {
          $ref = new ReflectionFunction($code);

          if ($ref->isClosure()) {
            // Ajouter le contexte aux paramètres pour les closures
            $contextualParameters = $parameters ?? [];
            if ($context !== null) {
              $contextualParameters['_context'] = $context;
            }

            $bait = $code($contextualParameters);
          }
        }

        if (!empty($bait)) {
          $result[] = $bait;
        }
      }

      return $result;
    }

  /**
   * Combines and returns the result of calling the 'call' method with the provided arguments.
   *
   * @param string $group The group name of the hook.
   * @param string $hook The specific hook to be called.
   * @param array|null $parameters Optional parameters to be passed to the hook/action.
   * @param string|null $action The action to be executed, defaults to 'execute' if not provided.
   * @param string|null $context Optional context identifier to filter hooks.
   * @return string Concatenated string resulting from the invocation of the 'call' method with passed arguments.
   */
  public function output(string $group, string $hook, array|null $parameters = null, string|null $action = null, string|null $context = null): string
  {
    return implode('', $this->call($group, $hook, $parameters, $action, $context));
  }

  /**
   * Add a callback or action to a specific hook within a group for the current site.
   *
   * @param string $group The group to which the hook belongs.
   * @param string $hook The hook within the group to watch.
   * @param string $action The specific action within the hook to associate the code with.
   * @param mixed $code The code or callback to be executed when the action is triggered.
   * @param string|null $context Optional context identifier to restrict when this watch is executed.
   * @return void
   */
  public function watch(string $group, string $hook, string $action, $code, string|null $context = null): void
  {
    if ($context !== null) {
      // Structure avec contexte
      $this->watches[$this->site][$group][$hook][$action][] = [
        'code' => $code,
        'context' => $context
      ];
    } else {
      // Simple structure (rétrocompatibility)
      $this->watches[$this->site][$group][$hook][$action][] = $code;
    }
  }

  /**
   * Helper method to watch a hook for a specific tab
   *
   * @param string $group The group to which the hook belongs.
   * @param string $hook The hook within the group to watch.
   * @param string $tab The tab identifier.
   * @param mixed $code The code or callback to be executed.
   * @param string $action The specific action, defaults to 'display'.
   * @return void
   */
  public function watchTab(string $group, string $hook, string $tab, $code, string $action = 'display'): void
  {
    $this->watch($group, $hook, $action, $code, $tab);
  }

  /**
   * Helper method to check if a hook exists for a specific context
   *
   * @param string $group The group name.
   * @param string $hook The hook name.
   * @param string|null $context The context to check.
   * @param string $action The action name, defaults to 'display'.
   * @return bool True if hooks exist for the given context.
   */
  public function hasContextualHook(string $group, string $hook, string|null $context = null, string $action = 'display'): bool
  {
    $results = $this->call($group, $hook, null, $action, $context);
    return !empty($results);
  }

  /**
   * Registers a specific action to a hook within a group for a given site context.
   *
   * @param string $group The name of the group to register the hook under.
   * @param string $hook The name of the specific hook to register.
   * @param string $action The action to be executed when the hook is called.
   * @return void
   */
  protected function register(string $group, string $hook, string $action): void
  {
    // Conserver le groupe original et créer aussi la version courte pour compatibilité
    $originalGroup = $group;
    $shortGroup = basename($group);
    
    // Enregistrer sous le groupe demandé (court ou long)
    $this->hooks[$this->site][$group][$hook][$action] = [];

    $directory = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/Module/Hooks/' . $this->site . '/' . $group;

    if (is_dir($directory)) {
      if ($dir = new DirectoryIterator($directory)) {
        foreach ($dir as $file) {
          if (!$file->isDot() && !$file->isDir() && ($file->getExtension() == 'php') && ($file->getBasename('.php') == $hook)) {
            $class = 'ClicShopping\OM\Module\Hooks\\' . $this->site . '\\' . $shortGroup . '\\' . $hook;

            if (method_exists($class, $action)) {
              $this->hooks[$this->site][$group][$hook][$action][] = $class;
            }
          }
        }
      }
    }

    // Chercher avec le groupe tel quel
    $filter = [
      'site' => $this->site,
      'group' => $group,
      'hook' => $hook
    ];

    $modules = Apps::getModules('Hooks', null, $filter);

    if (!str_contains($group, '/')) {
      $filterLong = [
        'site' => $this->site,
        'group' => $this->site . '/' . $group,
        'hook' => $hook
      ];

      $modulesLong = Apps::getModules('Hooks', null, $filterLong);
      
      // Fusionner les deux résultats
      $modules = array_merge($modules, $modulesLong);
    }

    foreach ($modules as $k => $class) {
      if (is_string($k) && str_contains($k, '\\')) {
        $parts = explode('\\', $k);
        $code = end($parts);
        if ($code !== $hook) {
          continue;
        }
      }

      if (method_exists($class, $action)) {
        $this->hooks[$this->site][$group][$hook][$action][] = $k;
      }
    }
  }
}
