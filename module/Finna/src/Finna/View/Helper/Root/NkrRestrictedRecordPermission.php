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

use Finna\RemsService\RemsService;

/**
 * Helper class for linking between EAD3 records in local and restricted NKR index.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class NkrRestrictedRecordPermission extends \Zend\View\Helper\AbstractHelper
{
    protected $rems;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(RemsService $rems
    ) {
        $this->rems = $rems;
    }

    /**
     * Render info box.
     *
     * @return null|html
     */
    public function __invoke($driver, $autoOpen = false, $user = null)
    {
        if (!$driver->hasRestrictedMetadata()) {
            return null;
        }

        $params = ['user' => $user, 'autoOpen' => $autoOpen];
        
        if ($user !== false) {
            $status = $this->rems->checkPermission('user', false);

            // TODO allow new submit if permission has been closed?
            $notSubmitted = in_array(
                $status,
                [null,
                 RemsService::STATUS_CLOSED,
                 RemsService::STATUS_NOT_SUBMITTED]
            );
            $params += [
                'id' => $driver->getUniqueID(),
                'status' => $status,
                'notSubmitted' => $notSubmitted,
                'callApi' => $status === null
             ];
        }
        
        return $this->getView()->render(
            'Helpers/nkrRestrictedRecordPermission.phtml', $params
        );
    }
}
