<?php

namespace Dbib\Controller;

use VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Solr as SolrException;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Controller\AbstractRecord;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\View\Model\ViewModel;

class ViewController extends \VuFind\Controller\AbstractRecord
{

    var $dev = false;

    /*
     * IIIF Viewer Action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function iiifAction()
    {
        $this->driver = parent::loadRecord();
        $oid = $this->params()->fromRoute('id');
        $this->log('iiif view ' . $oid);
        if (empty($this->driver->getRestrictions())) {
            $manifest = $this->getServerUrl('record').$oid.'/Export?style=IIIF';
            $view = $this->createViewModel();

            // build path
            $viewer = $this->getConfig('Dbib')['View']['viewer'] ?? 'mirador';
            $themeInfo = $this->serviceLocator->get('VuFindTheme\ThemeInfo');
            $theme = $themeInfo->getTheme();
            // $view->setTemplate('view/iiif'); // default
            $view->base = $this->url()->fromRoute('home'). 'themes/'
                    . $theme . '/js/mirador-2.7/';
            $view->title = $this->driver->getTitle();
            $view->manifest = $manifest;
            $view->link = $this->getServerUrl('record') . $oid;

            $auth = $this->getAuthorizationService();
            if ($auth->isGranted('access.StaffViewTab')) {
                $view->annotator = $this->getUser()->username;
                $cf = $this->getConfig();
                if (isset($cf['Dbib']['autobib'])) {
                    $view->module = 'SimpleASEndpoint';
                    $view->storage = $cf['Dbib']['autobib'].'annotation/';
                } else {
                    $view->module = 'LocalStorageEndpoint';
                }
            } else {
                $view->module = 'LocalStorageEndpoint'; // no show without
            }
        } else {
            // prevent restricted material to appear
            $view = $this->createViewModel();
            $view->setTemplate('error/permissiondenied');
            $view->msg = 'hold_error_blocked';
        }
        return $view;
    }

    /*
     * Video View Action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function videoAction()
    {
        $oid = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $this->driver = parent::loadRecord();
        $url = $this->params()->fromRoute('url');
        
        $this->log('video action [' . $oid . '] [' . $url . ']');

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        // $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());
        $view->video = $url;
        $view->poster = $this->driver->getThumbnail();
        $view->docbase = $this->getServerUrl();
        return $view;
    }

    /**
     * PDF action - Viewer should appear
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function PDFAction() {
        $this->driver = parent::loadRecord();
        $oid = $this->driver->getUniqueId();
        $url = $this->params()->fromRoute('url');
        $page = $this->params()->fromRoute('page');
        $only = $this->params()->fromRoute('onlyView');
        $this->log('pdf view [' . $url . ']');
        $this->layout()->setTemplate('view/layout');
        $view = $this->createViewModel();
        $them = $this->serviceLocator->get('VuFindTheme\ThemeInfo')->getTheme();
        $base = $this->url()->fromRoute('home').'themes/'.$them.'/js/pdf.js';
        $view->title = $this->driver->getTitle();
        $view->link = $this->getServerUrl('record') . $oid;

        if (empty($only)) {
            $view->viewer = $base.'/web/viewer.html?file=';
        } else {
            $view->viewer = $base.'/web/viewonly.html?file=';
        }

        if (empty($page)) {
            $view->file = $url;
        } else {
            $view->file = $url . '#page=' . $page;
        }
        return $view;
    }

    /*
     * Universal Viewer Action
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function uvAction()
    {
        $this->log('uv action');
        $oid = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $this->driver = parent::loadRecord();
        $manifest = $this->getServerUrl('record').$oid.'/Export?style=IIIF';

        $view = $this->createViewModel();
        $view->title = $this->driver->getTitle();

        $them = $this->serviceLocator->get('VuFindTheme\ThemeInfo')->getTheme();
        $view->base = $this->url()->fromRoute('home'). 'themes/'
                    . $them . '/js/uview/';
        $view->setTemplate('view/uview'); // theme ub4 only for now
        $view->manifest = $manifest;
        return $view;
    }

    /**
     * Epub action - Epub Viewer appears
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function epubAction() {
        $conf = $this->getConfig('Dbib');
        $reader = $conf['View']['epub'] ?? 'epubjs-reader';
        $this->driver = parent::loadRecord();
        $oid = $this->driver->getUniqueId();
        $url = $this->params()->fromRoute('url');
        $themeInfo = $this->serviceLocator->get('VuFindTheme\ThemeInfo');
        $them = $themeInfo->getTheme();
        $base = $this->url()->fromRoute('home').'themes/'.$them.'/js/'.$reader;
        $view = $this->createViewModel();
        $this->layout()->setTemplate('view/layout'); // use simplified layout 
        if ($reader=='epubJsViewer-ojs') {
            $view->setTemplate('view/epub-ojs'); 
        } else {
            // $view->setTemplate('view/epub'); // default
        }
        $view->viewer = $base;
        $view->title = $this->driver->getTitle();
        $view->link = $this->getServerUrl('record') . $oid;
        $view->url = $url;
        return $view;
    }

    private function log($msg) {
        if ($this->dev) {
            error_log('ViewController '.$msg);
        }
    }
}

