<?php
/**
 * References tab
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  RecordTabs
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace Dpub\RecordTab;
use VuFind\RecordTab\AbstractBase as AbstractBase;

/**
 * References tab
 *
 * @category VuFind2
 * @package  RecordTabs
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class References extends AbstractBase
{
    /**
     * Is this tab active?
     *
     * @var bool
     */
    protected $active = false;

    /**
     * Constructor
     *
     * @param bool $active Is this tab active?
     */
    public function __construct($active)
    {
        $this->active = $active;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'References';
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active;
    }

    /**
     * Is this tab initially visible?
     *
     * @return bool
     */
    public function isVisible()
    {
        // in this case there is no downside to keeping it hidden
        // until there is content
        $refs = $this->getRecordDriver()->tryMethod('hasReferences');
        return !empty($refs);
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        return false;
    }

}
