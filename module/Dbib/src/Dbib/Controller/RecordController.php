<?php

namespace Dbib\Controller;

use VuFind\Connection\Manager as ConnectionManager,
    VuFind\Exception\Solr as SolrException;
use VuFind\Exception\Forbidden as ForbiddenException;

class RecordController extends \VuFind\Controller\RecordController
{

    var $dev = false;
    var $viewOnly = false;

    // Forward to edit 
    public function editAction() {
        $this->driver = $this->loadRecord();
        $oid = $this->driver->getUniqueId();
        return $this->forwardTo('Dbib', 'edit', ['id' => $oid]);
    }

    // Forward to view
    public function pdfAction() {
        return $this->viewAction();
    }

    // Forward to view
    public function restrictedAction() {
        return $this->viewAction();
    }

    /*
     * Record view
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function viewAction()
    {
        // $headers = $this->getResponse()->getHeaders();
        // $headers->addHeaderLine('Access-Control-Allow-Origin', '*');
        
        $this->driver = $this->loadRecord();
        $url = $this->isRestricted() ? null : $this->getURL();
        $oid = $this->driver->getUniqueId();

        if (empty($url)) {
            $view = $this->createViewModel();
            $view->setTemplate('error/permissiondenied');
            $view->msg = 'hold_error_blocked';
            return $view;
        } 

        if (substr($url,-4)=='.pdf') {
            $this->log('view pdf ' . $url);
            return $this->forwardTo('View', 'pdf', 
                ['id' => $oid, 'url' => $url, 'onlyView' => $this->viewOnly]);
        } else if (substr($url,-4)=='.mp4') {
            $this->log('view mp4 ' . $url);
            return $this->forwardTo('View', 'video', 
                ['id' => $oid, 'url' => $url]);
        } else if (substr($url,-4)=='.xml') {
            return $this->forwardTo('View', 'iiif', ['id' => $oid]);
        } else if (substr($url,-5)=='.epub') {
            $this->log('view epub ' . $url);
            return $this->forwardTo('View', 'epub', 
                ['id' => $oid, 'url' => $url]);
        } else if (substr($url,-4)=='/SMS') {
            return $this->forwardTo('View', 'pdf', 
                ['id' => $oid, 'url' => $url, 'onlyView' => $this->viewOnly]);
        } else if (substr($url,-4)=='/PDF') {
            return $this->forwardTo('View', 'pdf', 
                ['id' => $oid, 'url' => $url, 'onlyView' => $this->viewOnly]);
        }

        $view = $this->createViewModel();
        $view->setTemplate('view/cover');
        $view->cover = $this->driver->getThumbnail('medium');
        return $view;
    }

    protected function getURL()
    {
        $url = null;
        foreach ($this->driver->getURLs() as $route) {
            $url = $route['routeParams']['q'] ?? $route['url'] ?? false;
        }

        if (substr($url, 0, 7) == 'file://') {
            $oid = $this->driver->getUniqueId();
            $url = $this->url()->fromRoute('record-sms', ['id' => $oid]);
        }

        return $url;
    }

    private function isRestricted() {
        $restricted = true;
        $restrictions = $this->driver->getRestrictions();
        if (empty($restrictions)) {
            $restricted = false;
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
            foreach($restrictions['rights'] as $right) {
                if ($right === 'viewOnly') {
                    $this->viewOnly = true;
                } else if (substr($ip, 0, strlen($right)) === $right) {
                    $restricted = false;
                } // else $this->log('# ' .$right .' # ' . $ip);
            }
            if ($restricted) {
                $this->log('restricted resource ' . $ip);
            }
        }
        return $restricted;
    }

    public function smsAction() {
        $this->dev = true;
        if (!$this->getAuthorizationService()->isGranted('access.StreamView')) {
            return false;
        }

        $this->driver = parent::loadRecord();
        $req = $this->getRequest()->getQuery()->toArray();
        $fn = empty($req['q']) ? $this->params()->fromQuery('q') : $req['q'];
        //$fn = empty($fn) ? $this->params()->fromRoute('q') : $fn;
        if (empty($fn)) {
            $route = current($this->driver->getURLs());
            $fn = $route['routeParams']['q'] ?? null;
        }

        if (empty($fn)) {
            $this->log('refused stream [' . json_encode($route) . ']');
            return $this->redirect()->toRoute('error-permissiondenied');
        } else if (substr($fn, 0, 7) == 'file://') {
            $file = substr($fn, 7);
            $base = $this->getConfig('Dbib')->Doklief->data ?? null;
            if (file_exists($file) || empty($base)) {
                //
            } else if (file_exists($base.'/'.$file)) {
                $file = $base.'/'.$file;
            }
            if (file_exists($file)) {
                $this->log('stream ['. $file . ']');
                return $this->stream($file);
            } else {
                return $this->redirect()->toRoute('error-permissiondenied');
            }
        } else {
            $this->log('unexpected stream [' . json_encode($route) . ']');
        }
    }

    private function stream($file)
    {
        $allow = false;

        $mime = mime_content_type($file);
        // $ext = pathinfo($file, PATHINFO_EXTENSION);
        $dbib = $this->getConfig('Dbib');
        $db = $this->serviceLocator->get('VuFind\DbAdapterFactory')
            ->getAdapterFromConnectionString($dbib->Publish->database);
        $sql = 'SELECT mime_type from format where diss_format=1';
        $result = $db->query($sql)->execute();
        for ($i=0; $i<$result->count(); $result->next(), $i++) {
            if ($mime == $result->current()['mime_type']) {
                $allow = true;
                $this->log('allowed ' . $mime);
            }
        }

        if ($allow) {
            $stream = new DataStream($file, $mime);
            $stream->start();
        } else {
            $response = $this->getResponse();
            // $headers = $response->getHeaders();
            $data = '<html><body><h2>404</h2></body></html>';
            return $response->setContent($data);
        }
    }

    private function log($msg) {
        if ($this->dev) {
            error_log('RecordController '.$msg);
        }
    }

}

