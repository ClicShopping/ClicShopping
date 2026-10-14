<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Sites\Shop;

use ClicShopping\OM\Apps;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use function count;
use function defined;
use function in_array;
use function is_array;
use function is_null;
/**
 * The Payment class handles payment modules within the system.
 * It is capable of managing multiple payment methods, including initialization,
 * selection, validation, and updating of payment statuses.
 */
class Payment
{
  public array $modules = [];
  public string $selected_module = '';
  public mixed $template;
  public mixed $lang;
  public string $form_action_url = '';

// class constructor

  /**
   * Payment module constructor.
   *
   * Initializes the payment modules, sets the selected payment module if specified,
   * and configures the registry for each payment module class. It also handles
   * scenarios where only one payment method is available and ensures the
   * appropriate session variable is set for the payment method.
   *
   * @param string|null $module The specific payment module to initialize, if any.
   * @return void
   */
  public function __construct($module = null)
  {
    $this->template = Registry::get('Template');
    $this->lang = Registry::get('Language');

    if (defined('MODULE_PAYMENT_INSTALLED') && !is_null(MODULE_PAYMENT_INSTALLED)) {
      $this->modules = explode(';', MODULE_PAYMENT_INSTALLED);

      $include_modules = [];

      if ((!is_null($module)) && (in_array($module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)), $this->modules, true) || in_array($module, $this->modules))) {

        $this->selected_module = $module;

        if (str_contains($module, '\\')) {
          $class = Apps::getModuleClass($module, 'Payment');
          $include_modules[] = [
            'class' => $module,
            'file' => $class
          ];
        }
      } else {
        foreach ($this->modules as $value) {
          if (str_contains($value, '\\')) {
            $class = Apps::getModuleClass($value, 'Payment');
            $include_modules[] = [
              'class' => $value,
              'file' => $class
            ];
          }
        }
      }

      for ($i = 0, $n = count($include_modules); $i < $n; $i++) {
        if (str_contains($include_modules[$i]['class'], '\\')) {
          $class = $include_modules[$i]['file'];
          $moduleId = $include_modules[$i]['class'];

          // Drop a module that cannot be loaded or instantiated from the active
          // list, so the downstream loops over $this->modules never look up an
          // unregistered Registry key.
          if (empty($class) || !class_exists($class)) {
            $this->modules = array_values(array_filter($this->modules, static fn($m) => $m !== $moduleId));
            continue;
          }

          try {
            Registry::set('Payment_' . str_replace('\\', '_', $moduleId), new $class());
          } catch (\Throwable $e) {
            trigger_error('ClicShopping: Payment module "' . $moduleId . '" failed to instantiate: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(), E_USER_WARNING);
            $this->modules = array_values(array_filter($this->modules, static fn($m) => $m !== $moduleId));
          }
        }
      }

// if there is only one payment method, select it as default because in
// checkout_confirmation.php the $_SESSION['payment'] variable is being assigned the
// $_POST['payment'] value which will be empty (no radio button selection possible)

      if (($this->getCountPaymentModules() == 1) && (!isset($_SESSION['payment']) || ($_SESSION['payment'] != $include_modules[0]['class']))) {
        $_SESSION['payment'] = $include_modules[0]['class'];
      }

      if ((!is_null($module)) && (in_array($module . '.' . substr(CLICSHOPPING::getIndex(), (strrpos(CLICSHOPPING::getIndex(), '.') + 1)), $this->modules, true) || in_array($module, $this->modules))) {
        if (str_contains($module, '\\') && Registry::exists('Payment_' . str_replace('\\', '_', $module))) {
          $CLICSHOPPING_PM = Registry::get('Payment_' . str_replace('\\', '_', $module));

          if (isset($CLICSHOPPING_PM->form_action_url)) {
            /**
             *
             */
              $this->form_action_url = $CLICSHOPPING_PM->form_action_url;
          }
        }
      }
    }
  }

// class methods
  /* The following method is needed in the checkout_confirmation.php page
     due to a chicken and egg problem with the payment class and order class.
     The payment modules needs the order destination data for the dynamic status
     feature, and the order class needs the payment module title.
     The following method is a work-around to implementing the method in all
     payment modules available which would break the modules in the contributions
     section. This should be looked into again post 2.2.
  */
  /**
   * Updates the status of the selected payment module if it exists and has an update_status method.
   *
   * @return void
   */
  public function update_status()
  {
    if (is_array($this->modules)) {
      if (str_contains($this->selected_module, '\\')) {
        $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

        if (Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);

          if (method_exists($CLICSHOPPING_PM, 'update_status')) {
            $CLICSHOPPING_PM->update_status();
          }
        }
      }
    }
  }

  /**
   * Generates and returns JavaScript code for validating the payment form on the checkout page.
   * The validation script checks if a payment method is selected and ensures that any additional validation
   * logic provided by enabled payment modules is executed.
   *
   * @return string The JavaScript validation code as a string.
   */
  public function javascript_validation(): string
  {
    $js = '';
    if (is_array($this->modules)) {
      $js = '<script>' . "\n" .
        'function check_form() {' . "\n" .
        '  let error = 0;' . "\n" .
        '  let error_message = ' . json_encode(CLICSHOPPING::getDef('js_error') . "\n\n", JSON_THROW_ON_ERROR) . ';' . "\n" .
        '  let payment_value = null;' . "\n" .
        '  if (document.checkout_payment.payment.length) {' . "\n" .
        '    for (let i=0; i<document.checkout_payment.payment.length; i++) {' . "\n" .
        '      if (document.checkout_payment.payment[i].checked) {' . "\n" .
        '        payment_value = document.checkout_payment.payment[i].value;' . "\n" .
        '      }' . "\n" .
        '    }' . "\n" .
        '  } else if (document.checkout_payment.payment.checked) {' . "\n" .
        '    payment_value = document.checkout_payment.payment.value;' . "\n" .
        '  } else if (document.checkout_payment.payment.value) {' . "\n" .
        '    payment_value = document.checkout_payment.payment.value;' . "\n" .
        '  }' . "\n\n";

      foreach ($this->modules as $value) {
        $code = 'Payment_' . str_replace('\\', '_', $value);

        if (str_contains($value, '\\') && Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);

          if ($CLICSHOPPING_PM->enabled) {
            $js .= $CLICSHOPPING_PM->javascript_validation();
          }
        }
      }

      $js .= "\n" . '  if (payment_value == null) {' . "\n" .
        '    error_message = error_message + ' . json_encode(CLICSHOPPING::getDef('js_error_no_payment_module_selected') . "\n", JSON_THROW_ON_ERROR) . ';' . "\n" .
        '    error = 1;' . "\n" .
        '  }' . "\n\n" .
        '  if (error == 1) {' . "\n" .
        '    alert(error_message);' . "\n" .
        '    return false;' . "\n" .
        '  } else {' . "\n" .
        '    return true;' . "\n" .
        '  }' . "\n" .
        '}' . "\n" .
        '</script>' . "\n";
    }

    return $js;
  }

  /**
   * Executes the checkout initialization method for each enabled payment module that supports it.
   *
   * @return array Returns an array containing the results of the checkout initialization methods from applicable payment modules.
   */
  public function checkout_initialization_method(): array
  {
    $initialize_array = [];

    if (is_array($this->modules)) {
      foreach ($this->modules as $value) {
        $code = 'Payment_' . str_replace('\\', '_', $value);

        if (str_contains($value, '\\') && Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);

          if ($CLICSHOPPING_PM->enabled && method_exists($CLICSHOPPING_PM, 'checkout_initialization_method')) {
            $initialize_array[] = $CLICSHOPPING_PM->checkout_initialization_method();
          }
        }
      }
    }

    return $initialize_array;
  }

  /**
   * Retrieves an array of selection options from enabled payment modules.
   *
   * @return array Contains a list of selection data from the enabled modules.
   */
  public function selection(): array
  {
    $selection_array = [];

    if (is_array($this->modules)) {
      foreach ($this->modules as $value) {
        $code = 'Payment_' . str_replace('\\', '_', $value);

        if (str_contains($value, '\\') && Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);

          if ($CLICSHOPPING_PM->enabled) {
            $selection = $CLICSHOPPING_PM->selection();
            if (is_array($selection)) $selection_array[] = $selection;
          }
        }
      }
    }

    return $selection_array;
  }

  /**
   * Performs a pre-confirmation check for the selected payment module.
   * If the selected module is enabled, it invokes its specific `pre_confirmation_check` method.
   *
   * @return void
   */
  public function pre_confirmation_check()
  {
    if (is_array($this->modules)) {
      $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

      if (str_contains($this->selected_module, '\\') && Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);

        if ($CLICSHOPPING_PM->enabled) {
          $CLICSHOPPING_PM->pre_confirmation_check();
        }
      }
    }
  }

  /**
   *
   */
  public function confirmation()
  {
    if (is_array($this->modules)) {
      $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

      if (str_contains($this->selected_module, '\\') && Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);

        if ($CLICSHOPPING_PM->enabled) {
          return $CLICSHOPPING_PM->confirmation();
        }
      }
    }
  }

  /**
   * Executes the process_button functionality for the selected payment module.
   *
   * This method checks if the payment modules are defined as an array and if the
   * selected module contains a namespace separator. If so, it retrieves the corresponding
   * payment module instance and calls its process_button method if the module is enabled.
   */
  public function process_button()
  {
    if (is_array($this->modules)) {
      $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

      if (str_contains($this->selected_module, '\\') && Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);

        if ($CLICSHOPPING_PM->enabled) {
          return $CLICSHOPPING_PM->process_button();
        }
      }
    }
  }

  /**
   * Executes the `before_process` method for the selected payment module if it is enabled.
   *
   * This method first checks if the `$modules` property is an array. Then, it verifies
   * if the `selected_module` contains a backslash character (`\`). If so, a payment
   * module instance is retrieved using the `Registry` class. If the retrieved module
   * is enabled, its `before_process` method is invoked and its result is returned.
   *
   * @return mixed Returns the result of the payment module's `before_process` method if invoked; null otherwise.
   */
  public function before_process()
  {
    if (is_array($this->modules)) {
      $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

      if (str_contains($this->selected_module, '\\') && Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);

        if ($CLICSHOPPING_PM->enabled) {
          return $CLICSHOPPING_PM->before_process();
        }
      }
    }
  }

  /**
   * Executes the after_process method for the selected payment module.
   *
   * Checks if the selected payment module exists and is enabled. If so, it calls the
   * after_process method of that specific payment module to handle any operations
   * that should be performed after the main process is completed.
   *
   * This method relies on the presence of a valid module name in the `selected_module`
   * property and ensures the module is structured within the application registry.
   *
   * Returns the result of the after_process method from the selected payment module.
   */
  public function after_process()
  {
    if (is_array($this->modules)) {
      $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

      if (str_contains($this->selected_module, '\\') && Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);

        if ($CLICSHOPPING_PM->enabled) {
          return $CLICSHOPPING_PM->after_process();
        }
      }
    }
  }

  /**
   * Retrieves error information from the selected module.
   *
   * If the modules property is an array and the selected module contains a
   * namespace separator, it attempts to access the corresponding payment module
   * and invokes its get_error method, returning any error details it provides.
   *
   * @return mixed|null Returns the error information from the module, or null if no error is found or the condition is not met.
   */
  public function get_error()
  {
    if (is_array($this->modules)) {
      $code = 'Payment_' . str_replace('\\', '_', $this->selected_module);

      if (str_contains($this->selected_module, '\\') && Registry::exists($code)) {
        $CLICSHOPPING_PM = Registry::get($code);

        if ($CLICSHOPPING_PM->enabled) {
          return $CLICSHOPPING_PM->get_error();
        }
      }
    }
  }

  /**
   * Counts the number of enabled payment modules installed in the system.
   *
   * @return int The count of enabled payment modules.
   */
  public function getCountPaymentModules(): int
  {
    $count = 0;

    $modules_array = explode(';', MODULE_PAYMENT_INSTALLED);

    for ($i = 0, $n = count($modules_array); $i < $n; $i++) {
      $m = $modules_array[$i];

      $CLICSHOPPING_PM = null;

      if (str_contains($m, '\\')) {
        list($vendor, $app, $module) = explode('\\', $m);

        $module = $vendor . '\\' . $app . '\\' . $module;

        $code = 'Payment_' . str_replace('\\', '_', $module);

        if (Registry::exists($code)) {
          $CLICSHOPPING_PM = Registry::get($code);
        }
      }

      if (isset($CLICSHOPPING_PM) && $CLICSHOPPING_PM->enabled) {
        $count++;
      }
    }

    return $count;
  }
}
