<?php
/**
 * Record Driver Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  RecordDrivers
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace Dpub\RecordDriver;
use Laminas\ServiceManager\ServiceManager;

/**
 * Record Driver Factory Class
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class Factory
{
    /**
     * Factory for SolrOpus record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Opus
     */
    public static function getSolrOpus(ServiceManager $sm)
    {
        $driver = new SolrView(
               $sm->get('VuFind\Config\PluginManager')->get('config'),
               $sm->get('VuFind\Config\PluginManager')->get('Dpub'),
               $sm->get('VuFind\Config\PluginManager')->get('searches'),
               $sm->get('VuFind\DbAdapterFactory')
        );
        // $driver->attachSearchService($sm->get('VuFindSearch\Service'));
        // GH2022-10
        // $driver->attachILS(
        //     $sm->get('VuFind\ILS\Connection'),
        //     $sm->get('VuFind\ILS\Logic\Holds'),
        //     $sm->get('VuFind\ILS\Logic\TitleHolds')
        // );
        // $driver->setIlsBackends(['Solr']);
        return $driver;
    }

    /**
     * Factory for SolrDpub record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Dpub
     */
    public static function getSolrDpub(ServiceManager $sm)
    {
        // $driver = new SolrDpub(
        $driver = new SolrView(
               $sm->get('VuFind\Config\PluginManager')->get('config'),
               $sm->get('VuFind\Config\PluginManager')->get('Dpub'),
               $sm->get('VuFind\Config\PluginManager')->get('searches'),
               $sm->get('VuFind\DbAdapterFactory')
        );
        return $driver;
    }

    /**
     * Factory for SolrOpac record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SolrBox
     */
    public static function getSolrOpac(ServiceManager $sm)
    {
        $driver = new \Dpub\RecordDriver\SolrOpac(
            $sm->get('VuFind\Config\PluginManager')->get('config'),
            null,
            $sm->get('VuFind\Config\PluginManager')->get('searches')
        );
        $driver->attachILS(
            $sm->get('VuFind\ILS\Connection'),
            $sm->get('VuFind\ILS\Logic\Holds'),
            $sm->get('VuFind\ILS\Logic\TitleHolds')
        );
        $driver->setIlsBackends(['Solr']);
        return $driver;
    }

    /**
     * Factory for WorldCat record driver.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCat
     */
    public static function getWorldCat(ServiceManager $sm)
    {
        $wc = $sm->get('VuFind\Config\PluginManager')->get('WorldCat');
        $driver = new WorldCat(
            $sm->get('VuFind\Config\PluginManager')->get('config'), $wc, $wc
        );
        $driver->attachILS(
            $sm->get('VuFind\ILS\Connection'),
            $sm->get('VuFind\ILS\Logic\Holds'),
            $sm->get('VuFind\ILS\Logic\TitleHolds')
        );
        return $driver;
    }
}
