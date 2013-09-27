<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://kit2.phpmanufaktur.de
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

if ('á' != "\xc3\xa1") {
    // the language files must be saved as UTF-8 (without BOM)
    throw new \Exception('The language file ' . __FILE__ . ' is damaged, it must be saved UTF-8 encoded!');
}

return array(
    'Confirm'
        => 'Bestätigen',
    'I have read the full text above'
        => 'Ich habe den obigen Text gelesen',
    'Please confirm that you have read the full text above.'
        => 'Bitte bestätigen Sie, dass Sie den obigen Text gelesen haben.',
    'Thank you for the confirmation!'
        => 'Vielen Dank für die Lesebestätigung!',
    'The confirmation box must be checked!'
        => 'Bitte setzen Sie ein Häkchen in der Bestätigungsbox!',
    'The email address %email% is not valid!'
        => 'Die E-Mail Adresse %email% ist nicht gültig, bitte prüfen Sie Ihre Eingabe!',
    'The name must contain at minimum 3 characters.'
        => 'Der eingegebene Name muss mindestens drei Zeichen lang sein.',
    'Your email'
        => 'Ihre E-Mail',
    'Your name'
        => 'Ihr Name',
    );
