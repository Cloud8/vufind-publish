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

    var $dev = true;

    /*
     * IIIF Viewer Action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function iiifAction()
    {
        $driver = parent::loadRecord();
        $oid = $this->params()->fromRoute('id');
        $this->log('iiif view ' . $oid);
        if (empty($this->driver->getRestrictions())) {
            $manifest = $this->getServerUrl('record').$oid.'/Export?style=IIIF';
            // $headers = $this->getResponse()->getHeaders();
            // $headers->addHeaderLine('Access-Control-Allow-Origin', '*');
            $view = $this->createViewModel();

            // build path
            $viewer = $this->getConfig('Dbib')['View']['viewer'] ?? 'mirador';
            $themeInfo = $this->serviceLocator->get('VuFindTheme\ThemeInfo');
            $theme = $themeInfo->getTheme();
            // $view->setTemplate('view/iiif'); // default
            $view->base = $this->url()->fromRoute('home'). 'themes/'
                    . $theme . '/js/mirador-2.7/';
            $view->title = $driver->getTitle();
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
        $driver = parent::loadRecord();
        $url = $this->params()->fromRoute('url');
        
        if (substr($url,0,7) == 'file://') {
            $url = $this->url()->fromRoute('view-stream').'?q='.$url;
        }

        $this->log('video action [' . $oid . '] [' . $url . ']');

        $view = $this->createViewModel();
        $view->tabs = $this->getAllTabs();
        // $view->activeTab = strtolower($tab);
        $view->defaultTab = strtolower($this->getDefaultTab());

        // $view->setTemplate('view/video');
        if (substr($url,0,7) == 'file://') {
            // ViewController should stream this
            $view->video = $this->url()->fromRoute('view-view').'?q='.$url;
        } else {
            $view->video = $url;
        }
        $view->poster = $this->driver->getThumbnail();
        $view->docbase = $this->getServerUrl();
        return $view;
    }

    /**
     * PDF action - Viewer should appear
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function pdfAction() {
        $driver = parent::loadRecord();
        $oid = $this->driver->getUniqueId();
        $url = $this->params()->fromRoute('url');
        $page = $this->params()->fromRoute('page');
        $only = $this->params()->fromRoute('onlyView');

        if (substr($url,0,7) == 'file://') {
            $url = $this->url()->fromRoute('view-stream').'?q='.$url;
            $this->log('pdf view [' . $url . ']');
            $url = urlencode($url);
        }
        $this->layout()->setTemplate('view/layout');
        $view = $this->createViewModel();
        $them = $this->serviceLocator->get('VuFindTheme\ThemeInfo')->getTheme();
        $base = $this->url()->fromRoute('home').'themes/'.$them.'/js/pdf.js';
        $view->title = $driver->getTitle();
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
        $driver = parent::loadRecord();
        $manifest = $this->getServerUrl('record').$oid.'/Export?style=IIIF';

        $view = $this->createViewModel();
        $view->title = $driver->getTitle();

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
        $conf = $this->getConfig('Minipupb');
        $reader = $conf['View']['epub'] ?? 'epubjs-reader';
        $driver = parent::loadRecord();
        $oid = $this->driver->getUniqueId();
        $url = $this->params()->fromRoute('url');

        if (substr($url,0,7) == 'file://') {
            $url = $this->url()->fromRoute('view-stream').'?q='.$url;
            // $this->log('ViewController epub view file [' . $url . ']');
        } else if (strpos($url, $_SERVER['SERVER_NAME'])!==FALSE) {
            $this->log('ViewController epub view [' . $url . ']');
        }

        $themeInfo = $this->serviceLocator->get('VuFindTheme\ThemeInfo');
        $them = $themeInfo->getTheme();
        $base = $this->url()->fromRoute('home').'themes/'.$them.'/js/pdf.js';
        $view = $this->createViewModel();
        $this->layout()->setTemplate('view/layout'); // use simplified layout 
        if ($reader=='epubJsViewer-ojs') {
            $view->setTemplate('view/epub-ojs'); 
        } else {
            // $view->setTemplate('view/epub'); // default
        }
        $view->viewer = $base;
        $view->title = $driver->getTitle();
        $view->link = $this->getServerUrl('record') . $oid;
        $view->url = $url;
        // $this->log('reader: ' . $reader . ' [' . $url . ']');
        return $view;
    }

    public function streamAction() {
        if (!$this->getAuthorizationService()->isGranted('access.StreamView')) {
            return false;
        }

        $req = $this->getRequest()->getQuery()->toArray();
        $fn = empty($req['q']) ? $this->params()->fromRoute('q') : $req['q'];
        $fn = empty($fn) ? $this->params()->fromQuery('q') : $fn;

        if (empty($fn)) {
            return $this->redirect()->toRoute('error-permissiondenied');
        } else {
            $this->log('stream [' . $fn . ']');
            return $this->stream($fn);
        }

    }

    /*
     * Stream files based on extension
     */
    private function stream($fname)
    {
        $allow = false;
        $file = substr($fname, 0, 7) == 'file://' ? substr($fname, 7) : $fname;
        $ext = pathinfo($file, PATHINFO_EXTENSION);

        if (file_exists($file)) {
            // absolute file path for publishing formats only
            if ($ext=="java" || $ext=="php") {
                $this->log('stream ['.$file.']');
            } else {
                // allow : pdf epub png mp4 zip
                $allow = true;
            }
        } else {
            // relative file path streams other formats too
            $base = $this->getConfig('Dbib')->Doklief->data;
            if (file_exists($base.'/'.$file)) {
                $file = $base.'/'.$file;
                $allow = true;
            } else if (isset($this->getConfig()['Content']['box'])) {
                $base = $this->getConfig()['Content']['box'];
                if (file_exists($base.'/'.$file)) {
                    $file = $base.'/'.$file;
                    $allow = true;
                }
            }
        }

        $response = $this->getResponse();
        $headers = $response->getHeaders();
        if ($allow==false) {
            $headers->addHeaderLine('Content-type','text/html');
            $this->log('DbibController::view forbidden [' . $file . ']');
        } else if ($ext=="pdf") {
            $headers->addHeaderLine('Content-type','application/pdf');
            $headers->addHeaderLine('Content-Disposition',
                                    'inline; filename='.basename($file));
        } else if ($ext=="png") {
            // $allow = true;
            $headers->addHeaderLine('Content-type','image/png');
        } else if ($ext=="tif") {
            $allow = true;
            $headers->addHeaderLine('Content-type', 'image/tiff');
        } else if ($ext=="epub") {
            $headers->addHeaderLine('Content-type','application/epub+zip');
            $headers->addHeaderLine('Content-Disposition',
                                    'inline; filename='.basename($file));
            $this->log("DbibController::stream epub " . $file . ' ' . $ext);
        } else if ($ext=="java" || $ext=="php") {
            $headers->addHeaderLine('Content-type','text/plain');
        } else if ($ext=="rdf") {
            $headers->addHeaderLine('Content-type','application/xml+rdf');
        } else if ($ext=="mp4") {
            $this->log("ViewController stream video: " . $file);
            $stream = new VideoStream($file, 'video/mp4');
            $stream->start();
        } else if ($ext=="txt") {
            $allow = true;
            $headers->addHeaderLine('Content-type', 'text/plain');
        } else if ($ext=="zip") {
            $this->log('ViewController stream zip: ' . $file);
            $stream = new VideoStream($file, 'application/zip');
            $stream->start();
        } else {
            $allow = false;
        }

        $data = null;
        // $this->log('ViewController stream '.$file.' : ' .$ext.' '. $allow);
        if ($allow && file_exists($file) && $ext=="zip") {
            $response->setStream(fopen($file, 'r'));
            $response->setStatusCode(200);
            $response->setStreamName(basename($file));
        } else if ($allow && file_exists($file)) {
            $data = file_get_contents($file);
        } else if ($allow && $ext=="epub") {
            $data = file_get_contents($file);
        } else {
            $data = '<html><body><h2>404</h2></body></html>';
        }

        if ($allow && empty($data)) {
            throw new \Exception("Problem with: {$file}.");
        }

        return $response->setContent($data);
    }

    private function log($msg) {
        if ($this->dev) {
            error_log('ViewController '.$msg);
        }
    }

}

