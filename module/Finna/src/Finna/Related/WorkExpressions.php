<?php
/**
 * Related Records: Solr-based work expressions
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2009.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
namespace Finna\Related;

/**
 * Related Records: Solr-based work expressions
 *
 * @category VuFind
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:related_records_modules Wiki
 */
class WorkExpressions implements \VuFind\Related\RelatedInterface
{
    /**
     * Work expressions
     *
     * @var array
     */
    protected $results;

    /**
     * Search service
     *
     * @var \VuFindSearch\Service
     */
    protected $searchService;

    /**
     * Constructor
     *
     * @param \VuFindSearch\Service $search Search service
     */
    public function __construct(\VuFindSearch\Service $search)
    {
        $this->searchService = $search;
    }

    /**
     * Establishes base settings for making recommendations.
     *
     * @param string                            $settings Settings from config.ini
     * @param \VuFind\RecordDriver\AbstractBase $driver   Record driver object
     *
     * @return void
     */
    public function init($settings, $driver)
    {
        if (($workKeys = $driver->tryMethod('getWorkKeys'))
            && $driver->getSourceIdentifier() === 'Solr'
        ) {
            $this->results = $this->searchService->workExpressions(
                $driver->getSourceIdentifier(),
                $driver->getUniqueID(),
                $workKeys
            )->getRecords();
        } else {
            $this->results = [];
        }
    }

    /**
     * Get an array of Record Driver objects representing the other expressions of
     * the record passed to the constructor.
     *
     * @return array
     */
    public function getResults()
    {
        return $this->results;
    }
}
