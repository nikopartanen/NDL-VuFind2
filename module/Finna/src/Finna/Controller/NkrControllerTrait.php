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

use Finna\RemsService\RemsService;
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
trait NkrControllerTrait
{
    protected $nkrRegisterForm = 'NkrRegister';

    /**
     * Handles submit of REMS forms.
     *
     * @return void
     */
    protected function processNkrRegisterForm()
    {
        $recordId = $this->params()->fromQuery('recordId');
        $collection = (boolean)$this->params()->fromQuery('collection', false);
        // TODO: extend form config to support sendMethod = <callback> ?
        
        $session = $this->getNkrSession();
        // TODO: check if authenticated with required method
        if (!($user = $this->getUser())) {
            $session->inLightbox = $this->inLightbox();
            $session->recordId = $recordId;
            $session->collection = $collection;
            return $this->forceLogin();
        } else {
            $rems = $this->serviceLocator->get('Finna\RemsService\RemsService');
            $showRegisterForm
                = RemsService::STATUS_NOT_SUBMITTED
                === $rems->checkPermission(true);
            
            if (!$showRegisterForm) {
                $inLightbox = $session->inLightbox;
                $recordId = $session->recordId ?? null;
                $collection = $session->collection ?? false;
                unset($session->inLightbox);
                unset($session->recordId);
                unset($session->collection);
                
                if ($inLightbox) {
                    $response = $this->getResponse();
                    $response->setStatusCode(205);
                    return '';
                } else {
                    if ($recordId) {
                        return $this->redirect()->toRoute(
                            $collection
                            ? 'nkrcollection-home' : 'nkrrecord-home',
                            ['id' => $recordId]
                        );
                    } else {
                        return $this->redirect()->toRoute('search-home');
                    }
                }
            }
        }
        
        if ($this->formWasSubmitted('submit')) {
            $user = $this->getUser();
            
            $form = $this->serviceLocator->get('VuFind\Form\Form');
            $formId = $this->nkrRegisterForm;
            $form->setFormId($formId);

            $view = $this->createViewModel(compact('form', 'formId', 'user'));
            $view->setTemplate('feedback/form');
            $params = $this->params()->fromPost();
            $form->setData($params);
            
            if (! $form->isValid()) {
                return $view;
            }

            $rems = $this->serviceLocator->get('Finna\RemsService\RemsService');
            
            $formParams = [];
            foreach (['usage_purpose', 'usage_desc']
                as $param
            ) {
                $formParams[$param] = $this->translate($params[$param]) ?? null;
            }

            $firstname = !empty($user->firstname)
                ? $user->firstname : $params['firstname'];

            $lastname = !empty($user->lastname)
                ? $user->lastname : $params['lastname'];

            $success = $rems->registerUser(
                $user->email,
                $firstname,
                $lastname,
                $formParams
            );
            
            if ($success !== true) {
                return $success;
            }
            
            
            // Request lightbox to refresh page
            $response = $this->getResponse();
            $response->setStatusCode(205);
            
            return '';
        }
    }

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
