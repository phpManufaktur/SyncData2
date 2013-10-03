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

// scan the /Locale directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale');
// scan the /Locale/Custom directory and add all available languages
$app['utils']->addLanguageFiles(MANUFAKTUR_PATH.'/ConfirmationLog/Data/Locale/Custom');

// setup, update and uninstall
$admin->get('/confirmationlog/setup',
    'phpManufaktur\ConfirmationLog\Data\Setup\Setup::controllerSetup');
$admin->get('/confirmationlog/update',
    'phpManufaktur\ConfirmationLog\Data\Setup\Update::controllerUpdate');
$admin->get('/confirmationlog/uninstall',
    'phpManufaktur\ConfirmationLog\Data\Setup\Uninstall::controllerUninstall');

// kitCommand ~~ confirmation ~~
$command->post('/confirmation',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Confirmation::controllerCreateIFrame')
    ->setOption('info', MANUFAKTUR_PATH.'/ConfirmationLog/command.confirmation.json');

// routes for the kitCommand ~~ confirmation ~~
$app->get('/confirmationlog/dialog',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Confirmation::controllerDialog');
$app->post('/confirmationlog/dialog/check',
    'phpManufaktur\ConfirmationLog\Control\kitcommand\Confirmation::controllerCheckConfirmation');

// kitCommand ~~ ConfirmationReport ~~
$command->post('confirmationreport',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Report::controllerCreateIFrame')
    ->setOption('info', MANUFAKTUR_PATH.'/ConfirmationLog/command.confirmationreport.json');

$app->get('confirmationlog/report',
    'phpManufaktur\ConfirmationLog\Control\kitCommand\Report::controllerReport');

/**
 * Use the EmbeddedAdministration feature to connect the extension with the CMS
 *
 * @link https://github.com/phpManufaktur/kitFramework/wiki/Extensions-%23-Embedded-Administration
 */
$app->get('/confirmationlog/cms/{cms_information}', function ($cms_information) use ($app) {
    $administration = new EmbeddedAdministration($app);
    return $administration->route('/admin/confirmationlog/control?action=list', $cms_information);
});

$admin->match('/confirmationlog/control',
    'phpManufaktur\ConfirmationLog\Control\Backend\Control::exec');
