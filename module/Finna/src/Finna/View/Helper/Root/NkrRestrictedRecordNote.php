<?php
/**
 * Helper class for linking between EAD3 records in local and restricted NKR index.
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
 * Helper class for linking between EAD3 records in local and restricted NKR index.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class NkrRestrictedRecordNote extends \Zend\View\Helper\AbstractHelper
{
    protected $collectionRoutes;
    
    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config
    ) {
        $this->collectionRoutes = isset($config->Collections->route)
            ? $config->Collections->route->toArray() : null;
    }

    /**
     * Render info box.
     *
     * @return null|html
     */
    public function __invoke($driver)
    {
        if (!$driver->hasRestrictedAlternative()) {
            return null;
        }

        $route = 'nkrrecord';
        if ($driver->isCollection()) {
            $route = $this->collectionRoutes[$route] ?? 'collection';
        }

        return $this->getView()->render(
            'Helpers/nkrRestrictedRecordNote.phtml',
            ['route' => $route, 'id' => $driver->getUniqueID()]
        );
    }
}
