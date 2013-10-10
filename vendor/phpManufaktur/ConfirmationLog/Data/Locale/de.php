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
    '- no selection -'
        => '- nichts ausgewählt -',

    'About'
        => '?',
    'Actual there exists no confirmations!'
        => 'Aktuell liegen keine Lesebestätigungen vor.',

    'Back to the overview'
        => 'Zurück zur Übersicht',

    'checksum'
        => 'Prüfsumme',
    'Click to sort column ascending'
        => 'Anklicken um die Spalte aufsteigend zu sortiern',
    'Click to sort column descending'
        => 'Anklicken um die Spalte absteigend zu sortieren',
    'Confirm'
        => 'Bestätigen',
    'confirmed_at'
        => 'Bestätigt am',

    'For the following installations are missing one or more confirmations.'
        => 'Zu den aufgeführten Installationen fehlen eine oder mehrere Lesebestätigungen.',

    'Group: %group_name%, group by: %group_by%'
        => 'Gruppe: %group_name%, Gruppieren nach: %group_by%',

    'I have read the full text above'
        => 'Ich habe den obigen Text gelesen',
    'id'
        => 'ID',
    'installation_name'
        => 'Installation',
    'installation_names'
        => 'Installationen',
    'Imported %added_records% records, skipped %skipped_records% records which already exists. Please check the logfile for further information.'
        => 'Es wurden %added_records% Datensätze importiert, %skipped_records% bereits vorhandene Datensätze wurden übersprungen.<br />Bitte kontrollieren Sie die Protokolldatei für detailierte Informationen.',
    'Import from table `mod_confirmation_log` (previous version)'
        => 'Import von Tabelle `mod_confirmation_log` (Vorgänger)',

    'name'
        => 'Bezeichnung',
    'No results for filter ID %filter_id%.'
        => 'Keine Ergebnisse für den Filter mit der ID %filter_id%!',
    'No results for the group %group%!'
        => 'Keine Ergebnisse für die Gruppe %group%!',
    'not available'
        => 'nicht verfügbar',

    'page_id'
        => 'PAGE ID',
    'page_title'
        => 'Seitentitel',
    'page_type'
        => 'Seiten Typ',
    'page_url'
        => 'Seiten URL',
    'PENDING'
        => 'Ausstehend',
    'person_name'
        => 'Name',
    'Persons: %group_name%, group by: %group_by%'
        => 'Personen Gruppe: %group_name%, Gruppieren nach: %group_by%',
    'Please confirm that you have read the full text above.'
        => 'Bitte bestätigen Sie, dass Sie den obigen Text gelesen haben.',
    'Please define a group!'
        => "Bitte legen Sie mit dem Parameter 'group' eine Gruppe fest!",
    'Please select the import you wish to perform.'
        => 'Bitte wählen Sie den gewünschten Import aus, der durchgeführt werden soll.',

    'received_at'
        => 'Erhaltem am',

    'second_id'
        => 'Sekundäre ID',
    'Select'
        => 'Auswählen',
    'status'
        => 'Status',
    'SUBMITTED'
        => 'Übermittelt',

    'Thank you for the confirmation!'
        => 'Vielen Dank für die Lesebestätigung!',
    'The confirmation box must be checked!'
        => 'Bitte setzen Sie ein Häkchen in der Bestätigungsbox!',
    'The confirmation with the ID %id% does not exists!'
        => 'Die Bestätigung mit der ID %id% existiert nicht!',
    'The email address %email% is not valid!'
        => 'Die E-Mail Adresse %email% ist nicht gültig, bitte prüfen Sie Ihre Eingabe!',
    'The group with the name %group% does not exists!'
        => 'Die Gruppe mit dem Bezeichner "%group%" existiert nicht!',
    'The name must contain at minimum 3 characters.'
        => 'Der eingegebene Name muss mindestens drei Zeichen lang sein.',
    'The table `mod_confirmation_log` does not exists, import aborted.'
        => 'Die Tabelle `mod_confirmation_log` existiert nicht, ein Import kann daher nicht durchgeführt werden.',
    'The user group with the name %group% does not exists!'
        => 'Die Benutzergruppe mit der Bezeichnung <b>%group%</b> existiert nicht, bitte prüfen Sie Ihre Eingabe!',
    'There exists no installation names in the records which can be used for the reports!'
        => 'Es existieren keine Installationsnamen in den Datensätzen, die für eine Auswertung verwendet werden können!',
    'There exists no page titles which can be checked for a report!'
        => 'Es sind keine Seitentitel erfasst, für die ein Bericht erstellt werden kann.',
    'time_on_page'
        => 'Gemessene Zeit',
    'timestamp'
        => 'Zeitstempel',
    'title'
        => 'Seitentitel',
    'transmitted_at'
        => 'Übermittelt am',
    'typed_email'
        => 'Angegebene E-Mail',
    'typed_name'
        => 'Angegebener Name',

    'user_email'
        => 'Login E-Mail',
    'user_name'
        => 'Login Name',

    'You have already confirmed this article at %date%.'
        => 'Sie haben diesen Artikel am %date% Uhr bestätigt.',
    'Your email'
        => 'Ihre E-Mail',
    'Your name'
        => 'Ihr Name',
    );
