<?php
/**
 * AJAX handler for changing pickup location.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Auth\ILSAuthenticator;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\ILS\Connection;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * AJAX handler for changing pickup location.
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ChangePickupLocation extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * ILS Connection
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Patron
     *
     * @var array|bool
     */
    protected $patron;

    /**
     * Constructor
     *
     * @param Connection $connection ILS Connection
     * @param array|bool $patron     Patron or false if not logged in to an ILS
     */
    public function __construct(Connection $connection, $patron)
    {
        $this->connection = $connection;
        $this->patron = $patron;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, internal status code, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        if (!$this->patron) {
            return $this->formatResponse(
                $this->translate('You must be logged in first'),
                self::STATUS_NEED_AUTH
            );
        }

        $requestId = $params->fromQuery('requestId');
        $pickupLocationId = $params->fromQuery('pickupLocationId');
        if (empty($requestId)) {
            return $this->formatResponse(
                $this->translate('bulk_error_missing'),
                self::STATUS_ERROR,
                400
            );
        }

        try {
            $result = $this->connection->checkFunction(
                'changePickupLocation', [$patron]
            );
            if (!$result) {
                return $this->formatResponse(
                    $this->translate('unavailable'),
                    self::STATUS_ERROR,
                    400
                );
            }

            $details = [
                'requestId' => $requestId,
                'pickupLocationId' => $pickupLocationId
            ];
            $results
                = $this->connection->changePickupLocation($this->patron, $details);

            return $this->formatResponse($results, self::STATUS_OK);
        } catch (\Exception $e) {
            $this->setLogger($this->serviceLocator->get('VuFind\Logger'));
            $this->logError('changePickupLocation failed: ' . $e->getMessage());
            // Fall through to the error message below.
        }

        return $this->formatResponse(
            $this->translate('An error has occurred'), self::STATUS_ERROR, 500
        );
    }
}
