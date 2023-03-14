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
use Dbib\Publish\MetaForm as MetaForm;
use Dbib\Publish\DbibAdmin as DbibAdmin;

/**
 * Dbib Controller
 *
 */
class DbibController extends \VuFind\Controller\AbstractBase
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
            return $this->redirect()->toRoute('dbib-admin');
        } else {
            return $this->redirect()->toRoute('dbib-upload');
        } 
    }

    /** Used by themes/seaview/templates/dbib/admin.phtml */
    public function adminAction()
    {
        if ($this->isAdmin()) {
            // OK
        } else if ($this->getUser()) {
            return $this->redirect()->toRoute('dbib-upload');
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
            // $view->setTemplate('dbib/admin'); // default
        } else if ($request->isGet()) {
            $post['page'] = $this->params()->fromQuery('page');
            $view->form = (new DbibAdmin('Admin', $options))->create($post);
            // $view->setTemplate('dbib/admin'); // default
        } else { // garbage request
            return $this->redirect()->toRoute('home');
        }
        return $view;
    }

    /** see themes/seaview/templates/dbib/metadata.phtml */
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

            $form = new MetaForm('dbib-data', $options); 
            $form->create($post);
            if ($form->process($post)) {
                $this->redirect()->toRoute('dbib-admin');
            }
            $post['opus:files'] = $form->files;
            $this->container->opus = $post;
            $view->setTemplate('dbib/metadata');
        } else if ($request->isGet()) { 
            // forwarded from RecordController
            $oid = $this->params()->fromRoute('id'); 
            // or set from admin template
            $oid = empty($oid) ? $this->params()->fromQuery('id') : $oid;
            if (empty($oid)) { 
                // should not happen, but is mostly harmless
                error_log("DbibController: unexpected action");
                return $this->redirect()->toRoute('dbib-admin');
            } else {
                error_log("edit get request " . $oid);
                $form = new MetaForm('dbib-data', $options); 
                $data = $form->read($oid);
                if (empty($data)) {
                    error_log('DbibController read failed ' . $oid);
                    $form->create();
                    $view->setTemplate('error/unavailable');
                } else {
                    $this->container->opus = $data;
                    $form->create($data);
                    $form->setData($data);
                    $view->setTemplate('dbib/metadata');
                }
            }
        } else {
            error_log("DbibController editAction: Zero request unexpected");
            return false;
        }

        $view->form = $form;
        return $view;
    }

    /** Display templates/dbib/metadata.phtml */
    public function uploadAction() {
        // in case of emergency : disable upload form
        if (empty($this->getConfig('Dbib')['Publish']['domain'])) {
            return $this->redirect()->toRoute('error-unavailable');
        }
        $request = $this->getRequest();

        $this->getViewRenderer()->getHelperPluginManager()->configure(
            (new \Laminas\Form\ConfigProvider())->getViewHelperConfig());

        $form = new MetaForm('dbib-data', $this->connect()); 

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
                error_log("DbibController Overdrive " . $form->end);
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
        $view->setTemplate('dbib/metadata');
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
            $view->setTemplate('dbib/subject');
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

