<?php
/**
 * REMS Service.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\RemsService;

use VuFind\Auth\Manager;
use Zend\Config\Config;
use Zend\Session\Container;


/**
 * REMS Service.
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RemsService
    implements \VuFindHttp\HttpServiceAwareInterface,
    \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;

    const STATUS_APPROVED = 'approved';
    const STATUS_NOT_SUBMITTED = 'not-submitted';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_CLOSED = 'closed';
    const STATUS_DRAFT = 'draft';
    
    /**
     * Configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Auth manager
     *
     * @var Manager
     */
    protected $auth;
    
    /**
     * Constructor.
     *
     * @param Config         $config  Configuration
     * @param SessionManager $session Session container
     * @param Manager        $auth    Auth manager
     */
    public function __construct(
        Config $config, Container $session = null, Manager $auth
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->auth = $auth;
    }

    /**
     * Register user to REMS
     *
     * @param string $email     Email
     * @param string $firstname First name
     * @param string $lastname  Last name
     *
     * @return bool 
     */
    public function registerUser(
        $email, $firstname = null, $lastname = null, $formParams = []
    ) {
        $commonName = $firstname;
        if ($lastname) {
            $commonName = $commonName ? " $lastname" : $lastname;
        }
        
        // 1. Create user
        $params = [
            'eppn' => $this->getUserId(),
            'mail' => $email,
            'commonName' => $commonName
        ];
        try {
            $this->sendRequest('users/create', $params, 'POST', true);
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        
        // 2. Create draft application
        $catItemId = $this->getCatalogItemId('entitlement');
        $params = ['catalogue-item-ids' => [$catItemId]];

        try {
            $response
                = $this->sendRequest('v2/applications/create', $params, 'POST');
        } catch (\Exception $e) {
            return $e->getMessage();
        }
        if (!isset($response['application-id'])) {
            return 'REMS: error creating draft application';
        }
        $applicationId = $response['application-id'];


        // 3. Save draft
        
        /*
        $params =  [
            'command' => 'submit',
            'catalogue-items' => [$this->getCatalogItemId('registration')],
            'items' =>  ['1' => 'Test 1', '2' => 'Test 2'],
            'licenses' => ['1' => 'approved', '2' => 'approved']
        ];
        
        $client = $this->getClient($userId, 'applications/save', 'POST', $params);
        */

        
        // TODO: use transit for now...
        $body = '["^ ","~:type","~:application.command/save-draft","~:application-id",' . $applicationId . ',"~:field-values",["^ ","~i1","test","~i2","test","~i3","","~i4","","~i5","","~i6","","~i7","","~i8",""],"~:accepted-licenses",["~#set",[1,2]]]';
        
        try {
            $response = $this->sendRequest(
                'applications/command', $params, 'POST', false,
                ['contentType' => 'application/transit+json', 'content' => $body]
            );
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        // 4. Submit application
        $params = [
             'type' => 'rems.workflow.dynamic/submit',
             'application-id' => $applicationId
        ];
        try {
            $response = $this->sendRequest(
                'applications/command', $params, 'POST'
            );
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        $this->savePermissionToSession(
            null, $this->getSessionKey($this->getCatalogItemId('entitlement'))
        );

        return true;        
    }

    /**
     * Check permission
     *
     * @param bool $callApi Call REMS API if permission is not already
     * checked and saved to session.
     *
     * @return bool 
     */
    public function checkPermission($callApi = false)
    {
        $catItemId = $this->getCatalogItemId('entitlement');
        $sessionKey = $this->getSessionKey($catItemId);
        
        if (!$callApi && isset($this->session->{$sessionKey})) {
            return $this->session->{$sessionKey};
        } elseif (!$callApi) {
            return null;
        }

        try {
            $applications = $this->getApplications();
            $status = RemsService::STATUS_NOT_SUBMITTED;

            foreach ($applications as $application) {
                if ($application['catalogItemId'] !== $catItemId) {
                    continue;
                }
                if (isset($application['status'])) {
                    $appStatus = $application['status'];
                    switch($appStatus) {
                    case RemsService::STATUS_SUBMITTED:
                        $status = $appStatus;
                        break;
                    case RemsService::STATUS_APPROVED:
                        $status = $appStatus;
                        break 2;
                    }
                }
            }

            //die("save: $status");
            $this->savePermissionToSession($status, $sessionKey);
            return $status;
        } catch (\Exception $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * Return applications
     *
     * @param string $locale Local for title
     *
     * @return array
     */
    public function getApplications($locale = 'fi')
    {
        $result = $this->sendRequest('v2/applications');
        $catItemId = $this->getCatalogItemId('entitlement');

        $applications = [];
        foreach ($result as $application) {
            $data = ['id' => $catItemId];
            $catalogItemId = $catItemId;
            $id = $application['application/id'];
            $status = $application['application/workflow']
                ['workflow.dynamic/state'] ?? null;
            $status = $this->mapRemsStatus($status);
            $created = $application['application/created'] ?? null;
            $modified = $application['application/modified'] ?? null;
            foreach ($application['application/resources'] as $catItem) {
                if ($catItem['catalogue-item/id'] === $catItemId) {
                    $titles = $catItem['catalogue-item/title'];
                    $title = $titles[$locale] ?? $titles['fi'];
                    break;
                }
            }
            $applications[]
                = compact('id', 'catalogItemId', 'status', 'created', 'modified');
        }

        return $applications;
    }
    
    /**
     * Return REMS user id (eppn) of the current authenticated user.
     *
     * @return string 
     */
    protected function getUserId()
    {
        // TODO remove organisation prefix?
        if (!$user = $this->auth->isLoggedIn()) {
            throw new Exception('REMS: user not logged');
        }
        return $user->username;
    }
    
    /**
     * Send a request to REMS api.
     *
     * @param string $url         URL (relative)
     * @param array  $params      Request parameters
     * @param string $method      GET|POST
     * @param bool   $adminAction Use admin API user id?
     * @param string $body        Request body
     *
     * @return bool 
     */
    protected function sendRequest(
        $url, $params = [], $method = 'GET', $adminAction = false, $body = null
    ) {
        $userId = $adminAction ? $this->config->General->apiAdminUser : $this->getUserId();

        $contentType = $body['contentType'] ?? 'application/json';
        
        $client = $this->getClient($userId, $url, $method, $params, $contentType);
        if (isset($body['content'])) {
            $client->setRawBody($body['content']);
        }

        $formatError = function ($response) use ($client, $params) {
            $err = "RMS: request failed: " . $client->getRequest()->getUriString()
            . ', params: ' . var_export($params, true)
            . ', statusCode: ' . $response->getStatusCode() . ': '
            . $response->getReasonPhrase()
            . ', response content: ' . $response->getBody();

            return $err;
        };
        
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $err = $formatError($response);
            $this->error($err);
            throw new \Exception($err);
        }

        $err = $formatError($response);

        if (!$response->isSuccess() || $response->getStatusCode() !== 200) {
            $this->error($err);
            throw new \Exception($err);
        }

        $response = json_decode($response->getBody(), true);
        // Verify 'success' field for POST requests
        if ($method === 'POST'
            && (!isset($response['success']) || !$response['success'])
        ) {
            $this->error($err);
            throw new \Exception($err);
        }

        return $response;
    }

    /**
     * Return HTTP client
     *
     * @param string $userId      User Id
     * @param string $url         URL (relative)
     * @param string $method      GET|POST
     * @param array  $bodyParams  Body parameters
     * @param string $contentType Content-Type
     *
     * @return string
     */
    protected function getClient(
        $userId, $url, $method = 'GET', $bodyParams = [],
        $contentType = 'application/json'
    ) {
        $url = $this->config->General->apiUrl . '/' . $url;

        $client = $this->httpService->createClient($url);
        $client->setOptions(['timeout' => 30, 'useragent' => 'Finna']);
        $headers = $client->getRequest()->getHeaders();
        $headers->addHeaderLine(
            'Accept', 'application/json'
        );
        $headers->addHeaderLine('x-rems-api-key', $this->config->General->apiKey);
        $headers->addHeaderLine('x-rems-user-id', $userId);


        $body = json_encode($bodyParams);
        $client->setRawBody($body);
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', $contentType);
        
        $client->setMethod($method);
        return $client;
    }

    /**
     * Return session key for a permission
     *
     * @param int $permissionId Id
     *
     * @return string
     */
    protected function getSessionKey($permissionId)
    {
        return "permission-$permissionId";
    }

    /**
     * Save permission to session
     *
     * @param string $status     Permission status
     * @param string $sessionKey Session key
     *
     * @return void
     */
    protected function savePermissionToSession($status, $sessionKey)
    {
        if ($status === null) {
            unset($this->session->{$sessionKey});
        } else {
            $this->session->{$sessionKey} = $status;
        }
    }

    /**
     * Get REMS catalogue item id from configuration
     *
     * @param string $type Catalogue item type
     *
     * @return string|null
     */
    protected function getCatalogItemId($type = 'registration')
    {
        return (int)$this->config->General->catalogItem[$type] ?? null;
    }
    
    /**
     * Map REMS application status
     *
     * @param string $remsStatus REMS status
     *
     * @return string
     */
    protected function mapRemsStatus($remsStatus)
    {
        $statusMap = [
            'approved' => RemsService::STATUS_APPROVED,
            'rems.workflow.dynamic/approved' => RemsService::STATUS_APPROVED,
            'submitted' => RemsService::STATUS_SUBMITTED,
            'applied' => RemsService::STATUS_SUBMITTED,
            'rems.workflow.dynamic/submitted'
                => RemsService::STATUS_SUBMITTED,
            'closed' => RemsService::STATUS_CLOSED,
            'rems.workflow.dynamic/closed' => RemsService::STATUS_CLOSED,
            'rems.workflow.dynamic/draft' => RemsService::STATUS_DRAFT
         ];

        return $statusMap[$remsStatus] ?? 'unknown';
    }
}
