<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM\Interfaces;

/**
 * Interface for implementing payment modules.
 *
 * Defines the methods that must be implemented to handle various
 * stages of a payment process, such as status updates, validation,
 * and error handling.
 */
interface PaymentInterface
{
  public function update_status();

  public function javascript_validation();

  public function selection();

  public function pre_confirmation_check();

  public function confirmation();

  public function process_button();

  public function before_process();

  public function after_process();

  public function get_error();

  public function check();

  public function install();

  public function remove();

  public function keys();
}
