<?php
/**
 * Doklief Controller
 *
 * PHP version 7
 *
 * Copyright (C) Abstract Power 2021.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Dbib\Controller;
use VuFind\Exception\Forbidden as ForbiddenException,
    VuFind\Exception\Mail as MailException,
    Laminas\ServiceManager\ServiceLocatorInterface,
    Laminas\Session\Container;
use Laminas\Db\Adapter\Adapter;
use Dbib\Doklief\OpusDoklief as OpusDoklief;

/**
 * Doklief Controller
 *
 * @category VuFind2
 * @package  Controller
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public 
 * @link     http://vufind.org   Main Site
 *
 */
class DokliefController extends \VuFind\Controller\MyResearchController
{

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
     * Login Action
     *
     * @return mixed
     */
    public function loginAction()
    {
        // error_log('DokliefController::loginAction');
        // If this authentication method doesn't use a VuFind-generated login
        // form, force it through:
        if ($this->getSessionInitiator()) {
            // Don't get stuck in an infinite loop -- if processLogin is already
            // set, it probably means Home action is forwarding back here to
            // report an error!
            //
            // Also don't attempt to process a login that hasn't happened yet;
            // if we've just been forced here from another page, we need the user
            // to click the session initiator link before anything can happen.
            if (!$this->params()->fromPost('processLogin', false)
                && !$this->params()->fromPost('forcingLogin', false)
            ) {
                $this->getRequest()->getPost()->set('processLogin', true);
                // This one is different from parent
                return $this->forwardTo('Doklief', 'Home');
            }
        } else if ($this->getUser()) {
            // already logged in : this helps
            return $this->forwardTo('Doklief', 'Home');
        }

        // Store the home URL as a login followup action
        $this->followup()->clear('url');
        // $url = $this->getServerUrl();
        $url = $this->url()->fromRoute('doklief-home');
        $this->followup()->store([], $url);

        // Make request available to view for form updating:
        $view = $this->createViewModel();
        $view->request = $this->getRequest()->getPost();
        return $view;
    }

    /*
     * Home action : Document delivery 
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        // error_log('DokliefController::homeAction');
        $auth = $this->getAuthorizationService();
        if ($auth->isGranted('access.AdminModule')) {
            return $this->redirect()->toRoute('doklief-admin');
            // return $this->forwardTo('Doklief', 'Admin');
        } else if ($auth->isGranted('access.StreamView')) {
            $options = $this->connect(); 
            $view = $this->createViewModel();
            $request = $this->getRequest();

            if ($request->isGet()) {
                $view->form = (new OpusDoklief('Doklief', $options))->create();
                // $view->setTemplate('doklief/home'); // implicit
            } else { 
                error_log('garbage request to DokliefController::homeAction');
                return $this->redirect()->toRoute('error-permissiondenied');
            }
            return $view;
        } else if ($this->getUser()) {
            error_log('GH2021-02 doklief denied '. $this->getUser()->email);
            return $this->redirect()->toRoute('error-permissiondenied');
        } else {
            return $this->redirect()->toRoute('doklief-login');
        }
    }

    /*
     * View action : Document delivery 
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function viewAction()
    {
        $auth = $this->getAuthorizationService();
        if ($auth->isGranted('access.StreamView')) {
            $options = $this->connect();
            $docid = $this->params()->fromQuery('q') ?? null;
            $doklief = (new OpusDoklief('Admin', $options));
            $count = $this->params()->fromQuery('c') ?? null;
            if (empty($count)) {
                $doklief->countDownload($docid); 
            } else {
                // Nothing -- download by admin
                error_log('DokliefController::viewAction '. $docid.'#'.$count);
            }
            $file = $options['files'] . '/' . $docid;
            error_log('stream ['.$file.']');
            $stream = new DataStream($file, 'application/pdf');
            $stream->start();
            // return $this->forwardTo('Opus', 'view', ['q' => $docid]);
        } else {
            return $this->redirect()->toRoute('error-permissiondenied');
        }
    }

    /*
     * Admin action : Document administration 
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function adminAction()
    {
        $auth = $this->getAuthorizationService();
        if ($this->isAdmin()) {
            // OK
        } else if ($this->getUser()) {
            return $this->redirect()->toRoute('doklief-home');
        } else {
            // after logout or so
            return $this->redirect()->toRoute('doklief-home');
            // return $this->redirect()->toRoute('error-permissiondenied');
        }

        $this->getViewRenderer()->getHelperPluginManager()->configure(
            (new \Laminas\Form\ConfigProvider())->getViewHelperConfig());

        $options = $this->connect();
        $view = $this->createViewModel();
        $request = $this->getRequest();

        if ($request->isPost()) {
            $post = $request->getPost()->toArray();
            // Markdown support
            if (isset($post['opus:page']) && $post['opus:page']==9) {
                // $this->layout()->setTemplate('layout/lightbox');
                return $this->redirect()->toRoute('content-page', 
                    ['page' => 'doklief']);
            } 

            // Mix in account information if required
            if (isset($post['opus:action']) && $post['opus:action']=='Account')
            {
                $user = trim($post['opus:username']) ?? null;
                $email = trim($post['opus:email']) ?? null;
                if (empty($email) && !empty($user) && strlen($user)==8) {
                    $post['opus:email'] = $this->getEmailFromILS($user);
                }
            } 

            $view->form = (new OpusDoklief('Admin', $options))->create($post);
        } else if ($request->isGet()) {
            $view->form = (new OpusDoklief('Admin', $options))->create();
            // $view->setTemplate('doklief/admin'); // implicit
        } else { // garbage request
            return $this->redirect()->toRoute('home');
        }
        return $view;
    }

    /** 
      * Database connection and settings from init-file
      */
    private function connect() {
        $conf = $this->getConfig();
        $dbib = $this->getConfig('Dbib');

        // use vufind database
        $db = $this->serviceLocator->get('VuFind\DbAdapterFactory')
            ->getAdapterFromConnectionString($conf->Database->database);
        $db->query("SET CHARACTER SET 'utf8'")->execute();
        $options = [ 'db' => $db ];
        if ($this->getUser()) {
            $options['username'] = $this->getUser()->username;
            $options['email'] = $this->getUser()->email;
        }
        if (empty($dbib['Doklief']['data'])) {
            $options['files'] = false;
            error_log('GH2021 bad : misconfigured doklief');
        } else {
            $options['files'] = $dbib['Doklief']['data'] ?? null;
            // error_log('GH2021 doklief '.$options['files']);
            $options['mailfrom'] = $dbib['Doklief']['mailfrom'] ?? null;
            $options['ftp'] = $dbib['Doklief']['ftp'] ?? null;
        }
        $options['fview'] = $this->getServerUrl('doklief-view');
        $options['days'] = $dbib['Doklief']['days'] ?? 14;
        $options['ext'] = $dbib['Doklief']['ext'] ?? null;
        return $options;
    }

    private function isAdmin() {
        $auth = $this->getAuthorizationService();
        if ($auth->isGranted('access.AdminModule')) {
            return true;
        }
        return false;
    }

    /** ILS email support */
    private function getEmailFromILS($barcode) {
        $email = '';
        $config = $this->getConfig('LBS4');
        if (isset($config['Catalog']['database'])) {
            $dsn = "dblib:host=" . $config['Catalog']['sybase'] . ";"
                 . "dbname=" . $config['Catalog']['database'];
            $db = new Adapter([
                'host' => $config['Catalog']['sybase'],
                'username' => $config['Catalog']['username'],
                'password' => $config['Catalog']['password'],
                'database' => $config['Catalog']['database'],
                'driver' => 'Pdo', 'dsn' => $dsn, 'driver_options' => []]);
             $sql = 'SELECT email_address from borrower where borrower_bar=\''
                 .$barcode.'\''; // sybase is picky about quotes
             error_log('DokliefController ILS query ['.$sql.']');
             try {
                 $row = $db->query($sql)->execute()->current();
                 $email = $row['email_address'];
             } catch (\Exception $e) {
                error_log($e->getMessage());
             }
        } else {
            throw new ILSException('No Database.');
        }

        return $email;
    }

    /*
    private function log($msg) {
        $logger = new \VuFind\Log\Logger();
		$logger->addWriter('stream', null, array('stream' => 'php://output'));
		if (is_array($msg)) {
		    //$logger->log(\VuFind\Log\Logger::INFO, implode($msg));
			$data = json_encode($msg);
		    $logger->log(\VuFind\Log\Logger::INFO, $data);
			error_log($data);
		} else {
		    $logger->log(\VuFind\Log\Logger::INFO, $msg);
		}
	}
    */
}

