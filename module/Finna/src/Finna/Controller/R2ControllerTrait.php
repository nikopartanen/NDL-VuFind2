<?php
/**
 * R2 record controller trait.
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
 * R2 record controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait R2ControllerTrait
{
    /**
     * Handles submit of R2 registration forms.
     *
     * @return void
     */
    protected function processR2RegisterForm()
    {
        $recordId = $this->params()->fromQuery('recordId');
        $collection = (boolean)$this->params()->fromQuery('collection', false);
        // TODO: extend form config to support sendMethod = <callback> ?
        
        $session = $this->getR2Session();
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
                            ? 'r2collection-home' : 'r2record-home',
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
            $formId = \Finna\Form\Form::R2_REGISTER_FORM;
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

            // Take firstname, lastname and email from profile if available
            $firstname = !empty($user->firstname)
                ? $user->firstname : $params['firstname'];

            $lastname = !empty($user->lastname)
                ? $user->lastname : $params['lastname'];

            $email = !empty($user->email)
                ? $user->email : $params['email'];

            $success = $rems->registerUser(
                $email,
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

        return null;
    }

    protected function handleAutoOpenRegistration($view)
    {
        $session = $this->getR2Session();

        if ($this->getRequest()->getQuery()->get('register') === '1') {
            $session->autoOpen = true;
            $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
            return $this->redirect()->toRoute(null, ['id' => $id]);
        }

        if (isset($session->autoOpen) && $session->autoOpen === true) {
            $view->autoOpenR2egistration = true;
            unset($session->autoOpen);
        }
        
        return $view;        
    }

    public function getR2Session()
    {
        return new SessionContainer(
            'R2Registration',
            $this->serviceLocator->get('VuFind\SessionManager')
        );

    }
}
