<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTML;
use ClicShopping\OM\Registry;

use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();

CLICSHOPPING::loadSite('ClicShoppingAdmin');

$CLICSHOPPING_Db = Registry::get('Db');
$CLICSHOPPING_Language = Registry::get('Language');

AdministratorAdmin::hasUserAccess();

$prompt = HTML::sanitize($_POST['message']);
$result = Gpt::getGptResponse($prompt);

$pos = strstr($result, ':');

if ($pos !== false) {
  $result = substr($pos, 2); // Pour enlever les deux-points et l'espace
  echo $result;
} else {
  echo $result; // Si "Keywords:" n'est pas trouvé, imprimez la chaîne d'origine.
}