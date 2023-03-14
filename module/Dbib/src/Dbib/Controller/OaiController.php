<?php
/**
 * OAI Module Controller
 *
 * PHP Version 7
 *
 * Copyright (C) Villanova University 2011.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace Dbib\Controller;
use VuFind\Controller\AbstractSearch;

/**
 * OAIController Class
 *
 * Controls the OAI server
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class OaiController extends \VuFind\Controller\OaiController
{
    /**
     * Display OAI server form.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function homeAction()
    {
        // no action needed
        return $this->createViewModel();
    }

    /**
     * Standard OAI server.
     *
     * @return \Laminas\Http\Response
     */
    public function authserverAction()
    {
        return $this->handleOAI('VuFind\OAI\Server\Auth');
    }

    /**
     * Standard OAI server.
     *
     * @return \Laminas\Http\Response
     */
    public function serverAction()
    {
        return $this->handleOAI('Dbib\OAI\Server');
    }

}
