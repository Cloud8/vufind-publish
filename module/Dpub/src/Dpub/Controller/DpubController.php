<?php
/**
 * Controller
 *
 * PHP version 7
 *
 * Copyright (C) Abstract Power 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @license  http://opensource.org/licenses/gpl-2.0.php 
 * @link     http://vufind.org   Main Site
 */
namespace Dpub\Controller;
use VuFind\Exception\Forbidden as ForbiddenException,
    VuFind\Exception\Mail as MailException,
    Laminas\ServiceManager\ServiceLocatorInterface,
    Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Dpub\Publish\MetaForm as MetaForm;
use Dpub\Publish\DpubAdmin as DpubAdmin;

/**
 * Dpub Controller
 *
 * @category VuFind2
 * @package  Controller
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public 
 * @link     http://vufind.org   Main Site
 *
 */
class DpubController extends \VuFind\Controller\AbstractBase
{

    /**
     * Record driver
     *
     * @var AbstractRecordDriver
     */
    protected $driver = null;

    /**
     * Session container 
     *
     * @var \Laminas\Session\Container
     */
    private $container;

    /**
     * Constructor
     */
    public function __construct(ServiceLocatorInterface $sm, Container $ct) {
        parent::__construct($sm);
        $this->container = $ct;
    }

    /**
     * Home action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        $auth = $this->getAuthorizationService();
        if (empty($this->container)) {
            throw new ForbiddenException('Cannot make session.');
        } else if ($auth->isGranted('access.AdminModule')) {
            return $this->redirect()->toRoute('dpub-admin');
        } else {
            return $this->redirect()->toRoute('dpub-upload');
        } 
    }

    /** Used by themes/seaview/templates/dpub/admin.phtml */
    public function adminAction()
    {
        if ($this->isAdmin()) {
            // OK
        } else if ($this->getUser()) {
            return $this->redirect()->toRoute('dpub-upload');
        } else {
            return $this->redirect()->toRoute('error-permissiondenied');
        }

        $this->getViewRenderer()->getHelperPluginManager()->configure(
            (new \Laminas\Form\ConfigProvider())->getViewHelperConfig());

        $view = $this->createViewModel();
        $options = $this->connect();
        $options['admin'] = true;
        $request = $this->getRequest();

        if ($request->isPost()) {
            $post = $request->getPost()->toArray();
            $post['page'] = $this->params()->fromQuery('page');
            $form = new DpubAdmin('Admin', $options);
            $post = $form->process($post);
            $view->form = $form->create($post);
            // $view->setTemplate('dpub/admin'); // default
        } else if ($request->isGet()) {
            $post['page'] = $this->params()->fromQuery('page');
            $view->form = (new DpubAdmin('Admin', $options))->create($post);
            // $view->setTemplate('dpub/admin'); // default
        } else { // garbage request
            return $this->redirect()->toRoute('home');
        }
        return $view;
    }

    /** see themes/seaview/templates/dpub/metadata.phtml */
    public function editAction() {
        if ($this->isAdmin()) {
            // OK
        } else if (!$this->getUser()) {
            return $this->forceLogin();
        } else {
            throw new ForbiddenException('Access denied.');
        }

        $request = $this->getRequest();
        $options = $this->connect();
        $options['admin'] = true;

        $view = $this->createViewModel();
        $form = null;
        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            $opus = $this->container->offsetGet('opus');
            if (empty($opus) || empty($opus['opus:files'])) {
                $post['opus:files'] = [];
            } else {
                $post['opus:files'] = $opus['opus:files'];
            }

            $form = new MetaForm('dpub-data', $options); 
            $form->create($post);
            if ($form->process($post)) {
                $this->redirect()->toRoute('dpub-admin');
            }
            $post['opus:files'] = $form->files;
            $this->container->opus = $post;
            $view->setTemplate('dpub/metadata');
        } else if ($request->isGet()) { 
            // forwarded from RecordController
            $oid = $this->params()->fromRoute('id'); 
            // or set from admin template
            $oid = empty($oid) ? $this->params()->fromQuery('id') : $oid;
            if (empty($oid)) { 
                // should not happen, but is mostly harmless
                error_log("DpubController: unexpected action");
                return $this->redirect()->toRoute('dpub-admin');
            } else {
                error_log("edit get request " . $oid);
                $form = new MetaForm('dpub-data', $options); 
                $data = $form->read($oid);
                if (empty($data)) {
                    error_log('DpubController read failed ' . $oid);
                    $form->create();
                    $view->setTemplate('error/unavailable');
                } else {
                    $this->container->opus = $data;
                    $form->create($data);
                    $form->setData($data);
                    $view->setTemplate('dpub/metadata');
                }
            }
        } else {
            error_log("DpubController editAction: Zero request unexpected");
            return false;
        }

        $view->form = $form;
        return $view;
    }

    /** Display templates/dpub/metadata.phtml */
    public function uploadAction() {
        // in case of emergency : disable upload form
        if (empty($this->getConfig('Dpub')['Publish']['domain'])) {
            return $this->redirect()->toRoute('error-unavailable');
        }
        $request = $this->getRequest();

        $this->getViewRenderer()->getHelperPluginManager()->configure(
            (new \Laminas\Form\ConfigProvider())->getViewHelperConfig());

        $form = new MetaForm('dpub-data', $this->connect()); 

        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            // disable anonymous uploads except for theses
            if (isset($post['dcterms:type']) && empty($this->getUser())
                && $post['dcterms:type']!='8') {
                return $this->forceLogin();
            }

            $opus = $this->container->offsetGet('opus');
            $post['opus:files'] = empty($opus['opus:files'])?[]:$opus['opus:files'];
            $form->create($post);
            if ($form->process($post)) {
                error_log("DpubController Overdrive " . $form->end);
                return $this->redirect()->toRoute('home');
            }
            $post['opus:files'] = $form->files;
            $this->container->opus = $post;
        } else {
            error_log("upload get request " . $form->end);
            $this->container->opus = [];
            $form->create();
        }

        $view = $this->createViewModel();
        $view->setTemplate('dpub/metadata');
        $view->form = $form;
        return $view;
    }

    /**
     * Override VuFind/src/VuFind/Controller/AbstractRecord.php
     * Connect to GND subject term service
     * @return mixed
     */
    public function subjectAction() {
        $view = $this->createViewModel();
        $request = $this->getRequest();
        if ($request->isXmlHttpRequest()) { // lightbox link
            $view->setTemplate('dpub/subject');
        }
        return $view;
    }

    /*
     * View Action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function viewAction()
    {
        if (!$this->getAuthorizationService()->isGranted('access.StreamView')) {
            return false;
        }

        $req = $this->getRequest()->getQuery()->toArray();
        $fn = empty($req['q']) ? $this->params()->fromRoute('url') : $req['q'];

        if (empty($fn)) {
            return $this->redirect()->toRoute('error-permissiondenied');
        } else {
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
                error_log('GH2021-02 stream ['.$file.']');
            } else {
                // allow : pdf epub png mp4 zip 
                $allow = true; 
            }
        } else {
            // relative file path streams other formats too
            $base = $this->getConfig('Dpub')->Doklief->data;
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
            error_log('DpubController::view forbidden [' . $file . ']');
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
            error_log("DpubController::stream epub " . $file . ' ' . $ext);
        } else if ($ext=="java" || $ext=="php") {
            $headers->addHeaderLine('Content-type','text/plain');
        } else if ($ext=="rdf") {
            $headers->addHeaderLine('Content-type','application/xml+rdf');
        } else if ($ext=="mp4") {
            error_log("DpubController stream video: " . $file);
            $stream = new VideoStream($file, 'video/mp4');
            $stream->start();
        } else if ($ext=="txt") {
            $allow = true;
            $headers->addHeaderLine('Content-type', 'text/plain');
        } else if ($ext=="zip") {
            error_log('DpubController stream zip: ' . $file);
            $stream = new VideoStream($file, 'application/zip');
            $stream->start();
        } else {
            $allow = false;
        }

        $data = null;
        // error_log('DpubController stream '.$file.' : ' .$ext.' '. $allow);
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

    /*
     * Security Action / Document delivery support
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function securityAction()
    {
        return $this->redirect()->toRoute('content-security');
    }

    private function connect() {
        $conf = $this->getConfig();
        $dpub = $this->getConfig('Dpub');
        $db = $this->serviceLocator->get('VuFind\DbAdapterFactory')
            ->getAdapterFromConnectionString($dpub->Publish->database);
        $db->query("SET CHARACTER SET 'utf8'")->execute();
        $options = [ 'db' => $db ];
        $options['admin'] = false;
        $options['mailfrom'] = isset($conf['Site']['email']) 
            ? $conf['Site']['email'] : false;
        $options['mailto'] = isset($dpub['Publish']['mailto'])
            ? $dpub['Publish']['mailto'] : false;
        $options['domain'] = isset($dpub['Publish']['domain'])
            ? $dpub['Publish']['domain'] : 0;
        $options['autobib'] = isset($dpub['Publish']['autobib'])
            ? $dpub['Publish']['autobib'] : false;
        $options['urn_prefix'] = $dpub['Publish']['urn_prefix'] ?? null;
        $options['doi_prefix'] = $dpub['Publish']['doi_prefix'] ?? null;
        $options['solrcore'] = $conf->Index->url
            .'/'. $conf->Index->default_core;
        if ($this->getUser()) { 
            $options['usermail'] = $this->getUser()->email;
        } 
        return $options;
    }

    private function isAdmin() {
        $auth = $this->getAuthorizationService();
        if ($auth->isGranted('access.AdminModule')) {
            return true;
        }
        return false;
    }

}

