<?php
/**
 * Nkr record controller trait.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Zend\Session\Container as SessionContainer;

/**
 * Nkr record controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait NkrrecordControllerTrait
{
    protected function handleAutoOpenRegistration($view)
    {
        $session = $this->getNkrSession();

        if ($this->getRequest()->getQuery()->get('register') === '1') {
            $session->autoOpen = true;
            $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
            return $this->redirect()->toRoute(null, ['id' => $id]);
        }

        if (isset($session->autoOpen) && $session->autoOpen === true) {
            $view->autoOpenNkrRegistration = true;
            unset($session->autoOpen);
        }
        
        return $view;        
    }

    public function getNkrSession()
    {
        return new SessionContainer(
            'nkrRegistration',
            $this->serviceLocator->get('VuFind\SessionManager')
        );

    }
}
