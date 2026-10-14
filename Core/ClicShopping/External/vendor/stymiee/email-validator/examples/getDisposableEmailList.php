<?php

use EmailValidator\Policy;
use EmailValidator\Validator\DisposableEmailValidator;

require('../vendor/autoload.php');

$disposableEmailList = (new DisposableEmailValidator(new Policy()))->getList();

// Store this list somewhere for later use
var_dump($disposableEmailList);
