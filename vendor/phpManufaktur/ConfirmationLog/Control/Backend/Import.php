<?php

/**
 * ConfirmationLog
 *
 * @author Team phpManufaktur <team@phpmanufaktur.de>
 * @link https://addons.phpmanufaktur.de/SyncData
 * @copyright 2013 Ralf Hertsch <ralf.hertsch@phpmanufaktur.de>
 * @license MIT License (MIT) http://www.opensource.org/licenses/MIT
 */

namespace phpManufaktur\ConfirmationLog\Control\Backend;

use phpManufaktur\ConfirmationLog\Data\Import\ImportOldLog;

class Import extends Backend
{
    /**
     * Execute import from the previous ConfirmationLog table
     *
     * @param Application $app
     */
    public function controllerImportPreviousTable($app)
    {
        $this->initialize($app);

        $ImportOldLog = new ImportOldLog();
        $result = $ImportOldLog->exec($app);

        $this->clearMessage();
        $this->setMessage($result);

        // important: unset the GET variable!
        $this->removeParameter('import');
        return $this->controllerImport($app);
    }

    /**
     * Perform the selected import
     *
     * @param Application $app
     */
    public function controllerImport($app)
    {
        $this->initialize($app);

        $import = $this->getParameter('import');

        switch ($import) {
            case 'previous':
                // execute import from previous version
                return $this->controllerImportPreviousTable($app);
        }

        return $this->app['twig']->render($this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/import.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('import'),
                'message' => $this->getMessage(),
                'link_previous' => self::$link.'&action=import&import=previous&usage='.self::$usage
            ));
    }
}
