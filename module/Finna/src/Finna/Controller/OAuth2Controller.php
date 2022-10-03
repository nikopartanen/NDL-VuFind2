<?php
/**
 * OAuth2 Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2022.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Controller;

use VuFind\Config\Locator;

/**
 * OAuth2 Controller
 *
 * Provides authorization support for external systems
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2Controller extends \VuFind\Controller\OAuth2Controller
{
    /**
     * Action to retrieve JSON Web Keys
     *
     * Finna: Look for keys also in local/config/finna
     *
     * @see https://www.tuxed.net/fkooman/blog/json_web_key_set.html
     *
     * @return mixed
     */
    public function jwksAction()
    {
        $result = [];
        $keyPath = $this->oauth2Config['Server']['publicKeyPath'] ?? '';
        if (strncmp($keyPath, '/', 1) !== 0) {
            // Convert relative path:
            $relativePath = $keyPath;
            $keyPath = Locator::getLocalConfigPath($relativePath);
            if (null === $keyPath) {
                $keyPath
                    = Locator::getLocalConfigPath($relativePath, 'config/finna');
            }
            if (null === $keyPath) {
                $keyPath = Locator::getBaseConfigPath($relativePath);
            }
        }
        if (file_exists($keyPath)) {
            $keyDetails = openssl_pkey_get_details(
                openssl_pkey_get_public(file_get_contents($keyPath))
            );

            $encodeKeyData = function ($s) {
                return rtrim(
                    str_replace(
                        ['+', '/'],
                        ['-', '_'],
                        base64_encode($s)
                    ),
                    '='
                );
            };

            $result = [
                'keys' => [
                    [
                        'kty' => 'RSA',
                        'n' => $encodeKeyData($keyDetails['rsa']['n']),
                        'e' => $encodeKeyData($keyDetails['rsa']['e']),
                    ],
                ],
            ];
        }

        return $this->getJsonResponse($result);
    }
}
