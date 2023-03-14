<?php

namespace Dbib\Controller;

use VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Solr as SolrException;
use VuFind\Exception\Forbidden as ForbiddenException;

class RecordController extends \VuFind\Controller\RecordController
{

    var $dev = false;

    // Forward to edit action
    public function editAction() {
        return $this->forwardTo('Dbib', 'edit', ['id' => $oid]);
    }

    /**
     * Restricted action - let the restricted template appear
     *
     * @return mixed
     */
    public function restrictedAction() {
        $driver = $this->loadRecord();
        $view = false;
        $ip = $_SERVER['REMOTE_ADDR'];
        $restrictions = $this->driver->getRestrictions();
        $rights = $restrictions['rights'];
        $url = $this->getRestrictedLink($restrictions);

        if (empty($url)) {
            $oid = $this->driver->getUniqueId();
            $this->log('deny ' . $ip . ' to [' . $oid . ']');
            $view = $this->createViewModel();
            $view->setTemplate('error/permissiondenied');
            $view->msg = 'hold_error_blocked';
        } else if (array_search('ViewOnly', $rights)===FALSE) {
            $this->log('Accepted ' . $ip . ' ['.$url.']');
            return $this->redirect()->toUrl($url);
        } else if (substr($url, -4) == '.pdf' || strpos($url, 'download')>0) {
            // OnlyView active : forward but hide download 
            $this->log('OnlyView ' . $url);
            $oid = $this->driver->getUniqueId();
            return $this->forwardTo('View', 'pdf', 
                 ['id' => $oid, 'url' => $url, 'onlyView' => true]);
        } else {
            $view = $this->createViewModel();
            $view->setTemplate('error/permissiondenied');
            $view->msg = 'hold_error_blocked';
        }
        return $view;
    }

    private function getRestrictedLink($restrictions) {
        $link = null;
        $rights = [];
        $ip = $_SERVER['REMOTE_ADDR'];
        $url = $this->getURL();

        if (empty($restrictions)) {
            //
        } else {
            $rights = $restrictions['rights'];
            if ($this->dev) {
                $rights[] = '127.0.0.1';
            }
        }

        foreach($rights as $right) {
            if (substr($ip, 0, strlen($right)) === $right) {
                $link = $url;
                break;
            }
        }

        return $link;
    }

    /*
     * Record view
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function viewAction()
    {
        $url = $this->getURL();
        $oid = $this->driver->getUniqueId();
        $this->log('view ' . $oid . ' ['.$url.']');
        if (empty($url)) {
            // $this->log('empty view');
        } else if (!empty($this->driver->getRestrictions())) {
            return $this->forwardTo('Record', 'restricted', ['id' => $oid]);
        } else if (substr($url,-4)=='.pdf') {
            $this->log('view pdf ' . $url);
            return $this->forwardTo('View', 'pdf', ['id' => $oid, 'url' => $url]);
        } else if (substr($url,-4)=='.mp4') {
            $this->log('view mp4 ' . $url);
            return $this->forwardTo('View', 'video', ['id' => $oid, 'url' => $url]);
        } else if (substr($url,-4)=='.xml') {
            return $this->forwardTo('View', 'iiif', ['id' => $oid]);
        } else if (substr($url,-5)=='.epub') {
            $this->log('view epub ' . $url);
            return $this->forwardTo('View', 'epub', ['id' => $oid, 'url' => $url]);
        }
        $view = $this->createViewModel();
        $view->setTemplate('view/cover');
        $view->cover = $this->driver->getThumbnail('medium');
        return $view;
    }

    protected function getURL()
    {
        $url = null;
        $driver = $this->loadRecord();
        foreach ($driver->getURLs() as $route) {
            $url = $route['routeParams']['q'] ?? $route['url'] ?? false;
        }
        return $url;
    }

    private function log($msg) {
        if ($this->dev) {
            error_log('RecordController '.$msg);
        }
    }

}

