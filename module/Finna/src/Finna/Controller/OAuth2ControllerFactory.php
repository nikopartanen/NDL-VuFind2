<?php
/**
 * OAuth2 controller factory.
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
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
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

use League\OAuth2\Server\CryptKey;
use VuFind\Config\Locator;

/**
 * OAuth2 controller factory.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OAuth2ControllerFactory extends \VuFind\Controller\OAuth2ControllerFactory
{
    /**
     * Return a key path from the OAuth2 configuration.
     *
     * Converts the path to absolute as necessary.
     *
     * Finna: Use 'config/finna' as a fallback if local file not found
     *
     * @param string $key Key path to return
     *
     * @return CryptKey
     *
     * @throws \Exception if the setting doesn't exist or is empty.
     */
    protected function getKeyFromConfigPath(string $key): CryptKey
    {
        $keyPath = $this->getOAuth2ServerSetting($key);
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
        return new CryptKey(
            $keyPath,
            null,
            $this->oauth2Config['Server']['keyPermissionChecks'] ?? true
        );
    }
}
