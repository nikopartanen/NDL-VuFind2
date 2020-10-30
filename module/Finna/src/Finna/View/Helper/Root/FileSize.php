<?php
/**
 * File size view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * File size view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FileSize extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Array of translated digital information units.
     *
     * @var array
     */
    protected $units = null;

    /**
     * Returns a human readable file size converted to the most appropriate
     * translated unit.
     *
     * @param int $bytes    File size in bytes.
     * @param int $decimals How many decimal places?
     *
     * @return string
     */
    public function __invoke($bytes, $decimals = 1)
    {
        if (!is_numeric($bytes)) {
            return $bytes;
        }
        if (!$this->units) {
            $transEsc = $this->getView()->plugin('transEsc');
            $this->units = [
                $transEsc('digital_information_unit_B'),
                $transEsc('digital_information_unit_KB'),
                $transEsc('digital_information_unit_MB'),
                $transEsc('digital_information_unit_GB'),
                $transEsc('digital_information_unit_TB'),
                $transEsc('digital_information_unit_PB'),
                $transEsc('digital_information_unit_EB'),
                $transEsc('digital_information_unit_ZB'),
                $transEsc('digital_information_unit_YB')
            ];
        }
        $exponent = min(floor(log($bytes) / log(1000)), count($this->units) - 1);
        $localizedNumber = $this->getView()->plugin('localizedNumber');
        $value = $localizedNumber(($bytes / pow(1000, $exponent)), $decimals);
        $unit = $this->units[$exponent];

        return $value . ' ' . $unit;
    }
}
