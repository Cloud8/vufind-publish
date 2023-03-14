<?php
/**
 * Record Tab Factory Class
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace Dbib\RecordTab;
use Laminas\ServiceManager\ServiceManager;

/**
 * Record Tab Factory Class
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 * @codeCoverageIgnore
 */
class Factory
{

    /**
     * Factory for References tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reviews
     */
    public static function getReferences(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        // Only instantiate the loader if the feature is enabled:
        if (isset($config->Content->references)) {
            $loader = $sm->get('VuFind\Content\PluginManager')->get('reviews');
        } else {
            $loader = null;
        }
        return new References($loader, static::getHideSetting($config, 'references'));
    }

    /**
     * Factory for Citations tab plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Reviews
     */
    public static function getCitations(ServiceManager $sm)
    {
        $config = $sm->get('VuFind\Config\PluginManager')->get('config');
        // Only instantiate the loader if the feature is enabled:
        if (isset($config->Content->citations)) {
            $loader = $sm->get('VuFind\Content\PluginManager')->get('reviews');
        } else {
            $loader = null;
        }
        return new Citations($loader, static::getHideSetting($config, 'citations'));
    }

    /**
     * Support method for construction of AbstractContent objects -- should we
     * hide this tab if it is empty?
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     * @param string              $tab    Name of tab to check config for
     *
     * @return bool
     */
    protected static function getHideSetting(\Laminas\Config\Config $config, $tab)
    {
        // TODO: can we move this code out of the factory so it's more easily reused?
        $setting = isset($config->Content->hide_if_empty)
            ? $config->Content->hide_if_empty : false;
        if ($setting === true || $setting === false
            || $setting === 1 || $setting === 0
        ) {
            return (bool)$setting;
        }
        if ($setting === 'true' || $setting === '1') {
            return true;
        }
        $hide = array_map('trim', array_map('strtolower', explode(',', $setting)));
        return in_array(strtolower($tab), $hide);
    }

}
