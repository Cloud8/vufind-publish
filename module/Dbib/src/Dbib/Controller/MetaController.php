<?php
/**
 * Controller
 *
 * PHP version 7
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
 * @link     http://vufind.org
 */
namespace Dbib\Controller;
use VuFind\Exception\Forbidden as ForbiddenException,
    VuFind\Exception\Mail as MailException,
    Laminas\ServiceManager\ServiceLocatorInterface,
    Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Dbib\Upload\MetaForm as MetaForm;
use Dbib\Upload\DbibAdmin as DbibAdmin;

/**
 * Meta Controller
 *
 */
class MetaController extends \VuFind\Controller\AbstractBase
{

    var $dev = true;

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
            return $this->redirect()->toRoute('dcterms-admin');
        } else {
            return $this->redirect()->toRoute('dcterms-upload');
        } 
    }

    /** Used by themes/seaview/templates/dcterms/admin.phtml */
    public function adminAction()
    {
        if ($this->isAdmin()) {
            // OK
        } else if ($this->getUser()) {
            return $this->redirect()->toRoute('meta-upload');
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
            $form = new DbibAdmin('Admin', $options);
            $post = $form->process($post);
            $view->form = $form->create($post);
            $view->setTemplate('dcterms/admin'); // default meta/admin
        } else if ($request->isGet()) {
            $post['page'] = $this->params()->fromQuery('page');
            $view->form = (new DbibAdmin('Admin', $options))->create($post);
            $view->setTemplate('dcterms/admin'); // default
        } else { // garbage request
            return $this->redirect()->toRoute('home');
        }
        return $view;
    }

    /** see themes/seaview/templates/dcterms/metadata.phtml */
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

            // $meta = $this->container->offsetGet('meta');
            // $post['meta:files'] = $meta['meta:files'] ?? []; 
            $post['meta:files'] = $this->container->offsetGet('meta');

            $form = (new MetaForm('DCTerms', $options))->create($post); 
            if ($form->process($post)) {
                $this->redirect()->toRoute('dcterms-admin');
            }
            // $meta['meta:files'] = $form->files;
            // $this->container->meta = $meta;
            $this->container->meta = $form->files;
            $view->setTemplate('dcterms/metadata');
        } else if ($request->isGet()) { 
            // forwarded from RecordController
            $oid = $this->params()->fromRoute('id'); 
            // or set from admin template
            $oid = empty($oid) ? $this->params()->fromQuery('id') : $oid;
            if (empty($oid)) { 
                // should not happen, but is mostly harmless
                error_log("MetaController: unexpected action");
                return $this->redirect()->toRoute('dcterms-admin');
            } else {
                error_log("edit get request " . $oid);
                $form = (new MetaForm('Document Type', $options))->create(); 
                $data = $form->read($oid);
                if (empty($data)) {
                    error_log('MetaController read failed ' . $oid);
                    $form->create();
                    $view->setTemplate('error/unavailable');
                } else {
                    $form->create($data);
                    // $meta['meta:files'] = $form->files;
                    // $this->container->meta = $meta;
                    $this->container->meta = $form->files;
                    $view->setTemplate('dcterms/metadata');
                }
            }
        } else {
            error_log("MetaController editAction: Zero request unexpected");
            return false;
        }

        $view->form = $form;
        return $view;
    }

    /** Display templates/dcterms/metadata.phtml */
    public function uploadAction() {
        // in case of emergency : disable upload form
        if (empty($this->getConfig('Dbib')['Publish']['domain'])) {
            return $this->redirect()->toRoute('error-unavailable');
        }
        $request = $this->getRequest();

        $this->getViewRenderer()->getHelperPluginManager()->configure(
            (new \Laminas\Form\ConfigProvider())->getViewHelperConfig());

        if ($request->isPost()) {
            $post = array_merge_recursive(
                $request->getPost()->toArray(),
                $request->getFiles()->toArray()
            );

            // anonymous uploads only for theses
            if (isset($post['opus:typeid']) && empty($this->getUser())
                && $post['opus:typeid']!='8') {
                return $this->forceLogin();
            }

            // $meta = $this->container->offsetGet('meta');
            // $post['meta:files'] = $meta['meta:files'] ?? []; 
            $this->container->meta = $form->files;
            $form = new MetaForm('DCTerms', $this->connect()); 
            $form->create($post);
            if ($form->process($post)) {
                error_log("MetaController Overdrive " . $form->end);
                return $this->redirect()->toRoute('home');
            }
            $this->container->meta = $form->files;
        } else {
            $this->container->meta = [];
            if ($this->getUser()) {
                error_log("upload get request " . $this->getUser()->email);
                $data['opus:verification'] = $this->getUser()->email;
                $form->create($data);
            } else {
                error_log("upload get request");
                $form->create();
            }
        }

        $view = $this->createViewModel();
        $view->setTemplate('dcterms/metadata');
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
            $view->setTemplate('dcterms/subject');
        }
        return $view;
    }

    private function connect() {
        $conf = $this->getConfig();
        $dbib = $this->getConfig('Dbib');
        $db = $this->serviceLocator->get('VuFind\DbAdapterFactory')
            ->getAdapterFromConnectionString($dbib->Publish->database);
        $db->query("SET CHARACTER SET 'utf8'")->execute();
        $options = [ 'db' => $db ];
        $options['admin'] = false;
        $options['mailfrom'] = $conf['Site']['email'] ?? false;
        $options['publish'] = $dbib->Publish ?? null;
        $options['solr'] = $conf->Index->url.'/'.$conf->Index->default_core;
        return $options;
    }

    private function isAdmin() {
        $auth = $this->getAuthorizationService();
        if ($auth->isGranted('access.AdminModule')) {
            return true;
        }
        return false;
    }

    private function log($msg) {
        if ($this->dev) {
            error_log('MetaController '.$msg);
        }
    }

}

