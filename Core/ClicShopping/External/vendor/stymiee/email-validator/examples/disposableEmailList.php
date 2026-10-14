<?php

use EmailValidator\EmailValidator;

require('../vendor/autoload.php');

$testEmailAddresses = [
    'test@gmail.com',
    'test@hotmail.com',
    'test@outlook.com',
    'test@yahoo.com',
    'test@example.com',
    'test@nobugmail.com',
    'test@mxfuel.com',
    'test@cellurl.com',
    'test@10minutemail.com',
];

$config = [
    'checkDisposableEmail' => true,
];
$emailValidator = new EmailValidator($config);

foreach ($testEmailAddresses as $emailAddress) {
    $emailIsValid = $emailValidator->validate($emailAddress);
    echo  ($emailIsValid) ? 'Email is valid' : $emailValidator->getErrorReason();
    echo PHP_EOL;
}
