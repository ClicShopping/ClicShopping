<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Domains;

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Interfaces;
use ClicShopping\OM\Interfaces\SitesInterface;
use ReflectionClass;
use function array_slice;
use function count;
use function in_array;
use function is_array;

/**
 * Abstract class representing the base functionality of a page. Handles templating, execution of actions,
 * and site-specific configurations. Implementations should extend this class to define specific page behavior.
 */
abstract class PagesAbstract implements Interfaces\PagesInterface
{
  public array $data = [];

  protected string $code;
  protected string|null $file = 'main.php';
  protected bool $use_site_template = true;
  protected SitesInterface $site;
  protected array $actions_run = [];
  protected array $ignored_actions = [];
  protected bool $is_rpc = false;

  public mixed $app;

  /**
   * Constructor method for initializing the class.
   *
   * @param \ClicShopping\OM\Interfaces\SitesInterface $site The site interface instance.
   * @return void
   */
  final public function __construct(Interfaces\SitesInterface $site)
  {
    $this->code = (new ReflectionClass($this))->getShortName();
    $this->site = $site;

    $this->init();
  }

  /**
   * Initializes the required settings or configurations for the current instance.
   *
   * @return void
   */
  protected function init()
  {
  }

  /**
   * Retrieves the code associated with the current object.
   *
   * @return mixed The value of the code property.
   */
  public function getCode()
  {
    return $this->code;
  }

  /**
   * Retrieves the file path based on the current class's namespace and a defined file property.
   *
   * @return string|bool Returns the full file path if the file property is set, or false otherwise.
   */
  public function getFile()
  {
    if (isset($this->file)) {
      return dirname(CLICSHOPPING::BASE_DIR) . DIRECTORY_SEPARATOR . str_replace('\\', '/', (new ReflectionClass($this))->getNamespaceName()) . '/templates/' . $this->file;
    } else {
      return false;
    }
  }

  /**
   * Sets whether to use the site template.
   *
   * @param bool $bool Determines if the site template should be used. Pass true to enable; false to disable.
   * @return void
   */
  public function setUseSiteTemplate(bool $bool)
  {
    $this->use_site_template = ($bool === true);
  }

  /**
   * Determines whether to use the site template.
   *
   * @return bool Returns true if the site template is used, false otherwise.
   */
  public function useSiteTemplate(): bool
  {
    return $this->use_site_template;
  }

  /**
   * Sets the file property with the provided file value.
   *
   * @param string $file The file name or path to be assigned.
   * @return void
   */
  public function setFile($file)
  {
    $this->file = $file;
  }

  /**
   * Determines if the current request is an action request by analyzing
   * the query parameters and verifying against ignored actions and existing actions.
   *
   * @return bool Returns true if the current request is identified as an action request, otherwise false.
   */
  public function isActionRequest(): bool
  {
    $furious_pete = [];

    if (count($_GET) > $this->site->actions_index) {
      $furious_pete = array_keys(array_slice($_GET, $this->site->actions_index, null, true));
    }

    if (!empty($furious_pete)) {
      $action = HTML::sanitize(basename($furious_pete[0]));

      if (!in_array($action, $this->ignored_actions, true) && $this->actionExists($action)) {
        return true;
      }
    }

    return false;
  }

  /**
   * Executes a list of actions, determining their validity and handling their execution.
   *
   * @param mixed $actions A single action or an array of actions to be executed.
   *                        If not an array, it will be wrapped into one.
   * @return void
   */
  public function runAction($actions)
  {
    if (!is_array($actions)) {
      $actions = [
        $actions
      ];
    }

    $run = [];

    foreach ($actions as $action) {
      $run[] = $action;

      if ($this->actionExists($run)) {
        $this->actions_run[] = $action;

        $class = $this->getActionClassName($run);

        $ns = explode('\\', $class);

        if ((count($ns) > 2) && ($ns[0] == 'ClicShopping') && ($ns[1] == 'Apps')) {
          if (isset($this->app) && is_subclass_of($this->app, 'ClicShopping\OM\Domains\AppAbstract')) {
            if ($this->app->definitionsExist(implode('/', array_slice($ns, 4)))) {
              $this->app->loadDefinitions(implode('/', array_slice($ns, 4)));
            }
          }
        }

        $action = new $class($this);

        $action->execute();

        if ($action->isRPC()) {
          $this->is_rpc = true;
        }
      } else {
        break;
      }
    }
  }

  /**
   * Processes and sanitizes action names from the request URI, verifies their validity,
   * and executes the corresponding actions if they exist.
   *
   * Actions are extracted based on the site configuration and processed sequentially.
   * If an action is invalid or ignored, the loop breaks and stops further processing.
   * Valid actions are passed to the `runAction` method for execution.
   *
   * @return void
   */
  public function runActions()
  {
    $actions = $furious_pete = [];

    if (count($_GET) > $this->site->actions_index) {
      $furious_pete = array_keys(array_slice($_GET, $this->site->actions_index, null, true));
    }

    foreach ($furious_pete as $action) {
      $action = HTML::sanitize(basename($action));

      $actions[] = $action;

      if (in_array($action, $this->ignored_actions, true) || !$this->actionExists($actions)) {
        array_pop($actions);

        break;
      }
    }

    if (!empty($actions)) {
      $this->runAction($actions);
    }
  }

  /**
   * Checks if a given action exists and is a valid subclass of the specified interface.
   *
   * @param string|array $action The action name as a string or an array to check for existence.
   * @return bool Returns true if the action exists and implements the PagesActionsInterface, otherwise false.
   */
  public function actionExists($action)
  {
    if (!is_array($action)) {
      $action = [
        $action
      ];
    }

    $class = $this->getActionClassName($action);

    if (class_exists($class)) {
      if (is_subclass_of($class, 'ClicShopping\OM\Interfaces\PagesActionsInterface')) {
        return true;
      } else {
        trigger_error('ClicShopping\OM\PagesAbstract::actionExists() - ' . implode('\\', $action) . ': Action does not implement ClicShopping\OM\PagesActionInterface and cannot be loaded.');
      }
    }

    return false;
  }

  /**
   * Retrieves the list of actions that have been executed.
   *
   * @return array The array containing the executed actions.
   */
  public function getActionsRun()
  {
    return $this->actions_run;
  }

  /**
   * Determines if the current action is a Remote Procedure Call (RPC).
   *
   * @return bool Returns true if the current action is identified as an RPC, otherwise false.
   */
  public function isRPC(): bool
  {
    return ($this->is_rpc === true);
  }

  /**
   * Retrieves the fully qualified class name of an action based on the provided action input.
   *
   * @param string|array $action The action name or array of action names used to construct the class name.
   * @return string Returns the fully qualified class name of the action.
   */
  protected function getActionClassName($action)
  {
    if (!is_array($action)) {
      $action = [
        $action
      ];
    }

    return (new ReflectionClass($this))->getNamespaceName() . '\\Actions\\' . implode('\\', $action);
  }
}
