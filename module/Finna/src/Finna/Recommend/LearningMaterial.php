<?php
/**
 * Learning Material Recommendations Module.
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
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
namespace Finna\Recommend;

use Finna\View\Helper\Root\SearchTabs;
use VuFind\Recommend\RecommendInterface;

/**
 * Learning Material Recommendations Module.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class LearningMaterial implements RecommendInterface
{
    const LM_FILTER = '~format_ext_str_mv:0/LearningMaterial/';

    protected $searchTabs;

    protected $tabUrl = false;

    /**
     * LearningMaterial constructor.
     *
     * @param SearchTabs $searchTabs "Search tabs" view helper
     */
    public function __construct($searchTabs)
    {
        $this->searchTabs = $searchTabs;
    }

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Laminas\Stdlib\Parameters $request Parameter object representing user
     *                                            request.
     *
     * @return void
     */
    public function init($params, $request)
    {
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        if ($this->hasLearningMaterialFilter($results)) {
            $view = $this->searchTabs->getView();
            $view->results = $results;
            $tabConfig
                = $this->searchTabs->getTabConfigForParams($results->getParams());
            foreach ($tabConfig as $tab) {
                if (isset($tab['id']) && $tab['id'] == 'L1' && isset($tab['url'])) {
                    $this->tabUrl = $tab['url'];
                    break;
                }
            }
        }
    }

    /**
     * Does the object contain a Learning Material filter?
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return bool
     */
    protected function hasLearningMaterialFilter($results)
    {
        return $results->getParams()->hasFilter(self::LM_FILTER);
    }

    /**
     * Returns the tab url to use in the recommendation.
     *
     * @return string|false
     */
    public function getTabUrl()
    {
        return $this->tabUrl;
    }
}
