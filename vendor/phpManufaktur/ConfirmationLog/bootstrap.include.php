<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/contact
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

// not really needed but make syntax control more easy ...
global $app;

// scan the /Locale directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale');
// scan the /Locale/Custom directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale/Custom');

$command->post('/confirmation',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Confirmation::controllerCreateIFrame');

$app->get('/confirmationlog/dialog',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Confirmation::controllerDialog');
$app->post('/confirmationlog/dialog/check',
    'phpManufaktur\ConfirmationLog\Control\kitcommand\Confirmation::controllerCheckConfirmation');

