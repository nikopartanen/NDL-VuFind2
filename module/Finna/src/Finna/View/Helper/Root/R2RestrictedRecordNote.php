<?php
/**
 * Helper class for linking between EAD3 records in local and restricted R2 index.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Helper class for linking between EAD3 records in local and restricted R2 index.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class R2RestrictedRecordNote extends \Zend\View\Helper\AbstractHelper
{
    protected $enabled;
    protected $collectionRoutes;
    
    /**
     * Constructor
     *
     * @param bool                $enabled Is R2 enabled?
     * @param \Zend\Config\Config $config  VuFind configuration
     */
    public function __construct(bool $enabled, \Zend\Config\Config $config
    ) {
        $this->enabled = $enabled;
        $this->collectionRoutes = isset($config->Collections->route)
            ? $config->Collections->route->toArray() : null;
    }

    /**
     * Render info box.
     *
     * @param RecordDriver $driver Record driver
     *
     * @return null|html
     */
    public function __invoke($driver)
    {
        if (!$this->enabled || !$driver->hasRestrictedAlternative()) {
            return null;
        }

        $route = 'r2record';
        if ($driver->isCollection()) {
            $route = $this->collectionRoutes[$route] ?? 'collection';
        }

        return $this->getView()->render(
            'Helpers/R2RestrictedRecordNote.phtml',
            ['route' => $route, 'id' => $driver->getUniqueID()]
        );
    }
}
