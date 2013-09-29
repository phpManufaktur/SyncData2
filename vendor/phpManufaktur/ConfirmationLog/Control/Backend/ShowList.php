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

class ShowList extends Backend
{
    protected static $route = null;
    protected static $columns = null;
    protected static $rows_per_page = null;
    protected static $select_status = null;
    protected static $order_by = null;
    protected static $order_direction = null;
    protected static $current_page = null;
    protected static $max_pages = null;


    protected function initialize($app)
    {
        parent::initialize($app);

        try {
            // search for the config file in the template directory
            $cfg_file = $this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/confirmation.list.json', '', true);
            // get the columns to show in the list
            $cfg = $this->app['utils']->readJSON($cfg_file);
            self::$columns = isset($cfg['columns']) ? $cfg['columns'] : $this->ConfirmationData->getColumns();
            self::$rows_per_page = isset($cfg['list']['rows_per_page']) ? $cfg['list']['rows_per_page'] : 100;
            self::$select_status = isset($cfg['list']['select_status']) ? $cfg['list']['select_status'] : array('PENDING', 'SUBMITTED');
            self::$order_by = isset($cfg['list']['order']['by']) ? $cfg['list']['order']['by'] : array('received_at');
            self::$order_direction = isset($cfg['list']['order']['direction']) ? $cfg['list']['order']['direction'] : 'DESC';
        } catch (\Exception $e) {
            // the config file does not exists - use all available columns
            self::$columns = $this->ConfirmationData->getColumns();
            self::$rows_per_page = 100;
            self::$select_status = array('PENDING', 'SUBMITTED');
            self::$order_by = array('received_at');
            self::$order_direction = 'DESC';
        }
        self::$current_page = 1;
        self::$route =  array(
            'pagination' => self::$link.'&action=list&page={page}&order={order}&direction={direction}&usage='.self::$usage,
            'edit' => self::$link.'&action=detail&id={confirmation_id}&usage='.self::$usage
        );
    }

    /**
     * Set the current page for the table
     *
     * @param integer $page
     */
    public function setCurrentPage($page)
    {
        self::$current_page = $page;
    }

    protected function getList(&$list_page, $rows_per_page, $select_status=null, &$max_pages=null, $order_by=null, $order_direction='ASC')
    {
        // count rows
        $count_rows = $this->ConfirmationData->count($select_status);

        if ($count_rows < 1) {
            // nothing to do ...
            return null;
        }

        $max_pages = ceil($count_rows/$rows_per_page);
        if ($list_page < 1) {
            $list_page = 1;
        }
        if ($list_page > $max_pages) {
            $list_page = $max_pages;
        }
        $limit_from = ($list_page * $rows_per_page) - $rows_per_page;

        return $this->ConfirmationData->selectList($limit_from, $rows_per_page, $select_status, $order_by, $order_direction, self::$columns);
    }


    public function controllerList($app)
    {
        $this->initialize($app);

        $this->setCurrentPage($this->getParameter('page', 1));
        $order_by = explode(',', $this->getParameter('order', implode(',', self::$order_by)));
        $order_direction = $this->getParameter('direction', self::$order_direction);

        $confirmations = $this->getList(self::$current_page, self::$rows_per_page, self::$select_status, self::$max_pages, $order_by, $order_direction);

/*
        echo "<pre>";
        print_r($confirmations);
        echo "</pre>";
*/
        return $this->app['twig']->render($this->app['utils']->getTemplateFile('@phpManufaktur/ConfirmationLog/Template', 'backend/confirmation.list.twig'),
            array(
                'usage' => self::$usage,
                'toolbar' => $this->getToolbar('list'),
                'message' => $this->getMessage(),
                'confirmations' => $confirmations,
                'columns' => self::$columns,
                'current_page' => self::$current_page,
                'route' => self::$route,
                'order_by' => $order_by,
                'order_direction' => strtolower($order_direction),
                'last_page' => self::$max_pages,
                'FRAMEWORK_URL' => CMS_ADMIN_URL
            ));
    }
}
