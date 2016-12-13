<?php
/**
 * SOLR backend.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\Solr;

use VuFindSearch\Query\AbstractQuery;
use VuFindSearch\Query\Query;

use VuFindSearch\ParamBag;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use VuFindSearch\Backend\Solr\Response\Json\Terms;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Feature\SimilarInterface;
use VuFindSearch\Feature\RetrieveBatchInterface;
use VuFindSearch\Feature\RandomInterface;

use VuFindSearch\Backend\Exception\BackendException;
use VuFindSearch\Backend\Exception\RemoteErrorException;

use VuFindSearch\Exception\InvalidArgumentException;

/**
 * SOLR backend.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Backend extends \VuFindSearch\Backend\Solr\Backend
{
    /**
     * Return similar records.
     *
     * @param string   $id     Id of record to compare with
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function similar($id, ParamBag $defaultParams = null)
    {
        // Hack to work around Solr bugs in the MLT Handlers
        if ($this->getSimilarBuilder()->mltHandlerActive()) {
            // Find interesting terms first
            $params = $defaultParams ? clone($defaultParams) : new ParamBag();
            $this->injectResponseWriter($params);
            $params->set('q', sprintf('id:"%s"', addcslashes($id, '"')));
            $params->set('qt', 'morelikethis');
            $params->set('mlt.interestingTerms', 'details');
            $params->set('rows', 0);
            $response = $this->connector->similar($id, $params);
            $results = json_decode($response, true);
            if (isset($results['interestingTerms'])) {
                $params = $defaultParams ? clone($defaultParams) : new ParamBag();
                $this->injectResponseWriter($params);
                $params->mergeWith(
                    $this->getSimilarBuilder()
                        ->buildInterestingTermQuery($results['interestingTerms'])
                );
                $response = $this->connector->search($params);
            }
        } else {
            $params = $defaultParams ? clone($defaultParams) : new ParamBag();
            $this->injectResponseWriter($params);
            $params->mergeWith($this->getSimilarBuilder()->build($id, $params));
            $response = $this->connector->similar($id, $params);
        }
        $collection = $this->createRecordCollection($response);
        $this->injectSourceIdentifier($collection);
        return $collection;
    }
}
