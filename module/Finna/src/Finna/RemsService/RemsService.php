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

use VuFindHttp\HttpService;
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
    implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;

    const STATUS_APPROVED = 'approved';
    const STATUS_NOT_SUBMITTED = 'not-submitted';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_CLOSED = 'closed';
    
    /**
     * HTTP service
     *
     * @var VuFind\Http
     */
    protected $http;

    /**
     * Configuration
     *
     * @var Config
     */
    protected $config;

    protected $url;

    protected $session;

    const USER_ID = 'finna-test-user-8';
    
    /**
     * Constructor.
     *
     * @param Config         $config         Configuration
     * @param HttpService    $http           Http service
     * @param SessionManager $sessionManager Session manager
     */
    public function __construct(
        Config $config, HttpService $http, Container $session = null
    ) {
        $this->config = $config;
        $this->http = $http;
        $this->session = $session;
    }

    public function registerUser($userId, $email, $name)
    {
        $userId = RemsService::USER_ID;

        $params = ['eppn' => $userId, 'mail' => $email, 'commonName' => $name];
        $client = $this->getClient(
            $this->config->apiAdminUser, 'users/create', 'POST', $params
        );
        $response = $client->send();
        $response = json_decode($response->getBody(), true);
        $success = $response['success'] ?? false;

        if (! $success) {
            return ['success' => false];
        }

        $params =  [
            'command' => 'submit',
            'catalogue-items' => [(int)$this->config->resource],
            'items' =>  ['1' => 'Test 1', '2' => 'Test 2'],
            'licenses' => ['1' => 'approved', '2' => 'approved']
        ];

        $client = $this->getClient($userId, 'applications/save', 'POST', $params);
        $response = $client->send();
        $response = json_decode($response->getBody(), true);

        $success = $response['success'] ?? false;
        
        if ($success && isset($response['state'])) {
            $status = $this->mapRemsStatus($response['state']);
            $this->savePermissionToSession($status);
        }
        
        return $success;
    }

    public function checkPermission($userId, $callApi = false)
    {
        //        return RemsService::STATUS_NOT_SUBMITTED;
        
        $userId = RemsService::USER_ID;

        $resourceId = (int)$this->config->resource;
        $sessionKey = $this->getSessionKey($resourceId);
        $status = null;
        $error = false;
        
        if (isset($this->session->{$sessionKey})) {
            return $this->session->{$sessionKey};
        } elseif (!$callApi) {
            return null;
        }

        //return "from ses: " . var_export($status, true);

        if ($callApi) {
            //        if ($in_array($status, [null, RemsService::STATUS_NOT_SUBMITTED])) {
            try {
                $result = $this->sendRequest('applications', $userId);
                $statusMap = [
                    'approved' => RemsService::STATUS_APPROVED,
                    'rems.workflow.dynamic/approved' => RemsService::STATUS_APPROVED,
                    'submitted' => RemsService::STATUS_SUBMITTED,
                    'rems.workflow.dynamic/submitted'
                        => RemsService::STATUS_SUBMITTED,
                    'closed' => RemsService::STATUS_CLOSED,
                    'rems.workflow.dynamic/closed' => RemsService::STATUS_CLOSED
                ];

                $status = RemsService::STATUS_NOT_SUBMITTED;
                foreach ($result as $application) {
                    $application = $application;
                    $resourceFound = false;
                    if (isset($application['catalogue-items'])) {
                        foreach ($application['catalogue-items'] as $catItem) {
                            if ($catItem['resource-id'] === $resourceId) {
                                $resourceFound = true;
                                break;
                            }
                        }
                    }
                    
                    if ($resourceFound) {
                        $status = $application['state'];
                        $status = $this->mapRemsStatus($status); //$statusMap[$status] ?? 'unknown';
                        if ($status === RemsService::STATUS_APPROVED) {
                            break;
                        }
                    }
                }
                //$status = RemsService::STATUS_CLOSED;
                $this->savePermissionToSession($status);

                return $status;
            } catch (\Exception $e) {
                return $e->getMessage();
            }
        }

        return null;
    }

    protected function sendRequest($url, $userId, $method = 'GET')
    {
        $client = $this->getClient($userId, $url, $method);
        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $this->error(
                "REMS request for '$url' failed: " . $e->getMessage()
            );
            return "err: " . $e->getMessage();
            throw new \Exception('Problem calling REMS API');
        }
        if (!$response->isSuccess()) {
            if (in_array((int)$response->getStatusCode(), [401, 403])) {
                return false;
            }
            $this->error(
                "Request for '" . $client->getRequest()->getUriString()
                . "' did not succeed: "
                . $response->getStatusCode() . ': '
                . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody()
            );

            return "Request for '" . $client->getRequest()->getUriString()
                . "' did not succeed: "
                . $response->getStatusCode() . ': '
                . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody();

            throw new \Exception('Problem calling REMS API');
        }

        return json_decode($response->getBody(), true);
    }

    protected function getSessionKey($permissionId)
    {
        return "permission-$permissionId";
    }

    protected function getClient($userId, $url, $method = 'GET', $bodyParams = [])
    {
        $url = $this->config->apiUrl . '/' . $url;

        $client = $this->http->createClient($url);
        $client->setOptions(['timeout' => 30, 'useragent' => 'Finna']);
        $headers = $client->getRequest()->getHeaders();
        $headers->addHeaderLine(
            'Accept', 'application/json'
        );
        $headers->addHeaderLine('x-rems-api-key', $this->config->apiKey);
        $headers->addHeaderLine('x-rems-user-id', $userId);


        $body = json_encode($bodyParams);
        $client->setRawBody($body);
        $client->getRequest()->getHeaders()->addHeaderLine('Content-type', 'application/json');
        
        $client->setMethod($method);
        return $client;
    }

    protected function savePermissionToSession($status)
    {
        $resourceId = (int)$this->config->resource;
        $sessionKey = $this->getSessionKey($resourceId);
        $this->session->{$sessionKey} = $status;
    }

    protected function mapRemsStatus($remsStatus)
    {
        $statusMap = [
            'approved' => RemsService::STATUS_APPROVED,
            'rems.workflow.dynamic/approved' => RemsService::STATUS_APPROVED,
            'submitted' => RemsService::STATUS_SUBMITTED,
            'rems.workflow.dynamic/submitted'
                => RemsService::STATUS_SUBMITTED,
            'closed' => RemsService::STATUS_CLOSED,
            'rems.workflow.dynamic/closed' => RemsService::STATUS_CLOSED
         ];

        return $statusMap[$remsStatus] ?? 'unknown';
    }
}
