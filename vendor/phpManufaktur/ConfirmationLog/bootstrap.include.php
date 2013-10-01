<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de/contact
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

use phpManufaktur\Basic\Control\CMS\EmbeddedAdministration;

// not really needed but make syntax control more easy ...
global $app;

// scan the /Locale directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale');
// scan the /Locale/Custom directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale/Custom');

// kitCommand ~~ confirmation ~~
$command->post('/confirmation',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Confirmation::controllerCreateIFrame')
    ->setOption('info', MANUFAKTUR_PATH.'/ConfirmationLog/command.confirmation.json');

// routes for the kitCommand ~~ confirmation ~~
$app->get('/confirmationlog/dialog',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Confirmation::controllerDialog');
$app->post('/confirmationlog/dialog/check',
    'phpManufaktur\ConfirmationLog\Control\kitcommand\Confirmation::controllerCheckConfirmation');

// setup, update and uninstall
$admin->get('/confirmationlog/setup',
    'phpManufaktur\ConfirmationLog\Data\Setup\Setup::controllerSetup');

/**
 * Use the EmbeddedAdministration feature to connect the extension with the CMS
 *
 * @link https://github.com/phpManufaktur/kitFramework/wiki/Extensions-%23-Embedded-Administration
 */
$app->get('/confirmationlog/cms/{cms_information}', function ($cms_information) use ($app) {
    $administration = new EmbeddedAdministration($app);
    return $administration->route('/admin/confirmationlog/about', $cms_information);
});

$admin->get('/confirmationlog/about',
    'phpManufaktur\ConfirmationLog\Control\Backend\About::controllerAppAbout');
$admin->get('/confirmationlog/control',
    'phpManufaktur\ConfirmationLog\Control\Backend\Control::exec');
