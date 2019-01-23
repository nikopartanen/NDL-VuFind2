<?php
/**
 * Model for EAD3 records in Solr.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2012-2017.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for EAD3 records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Eoghan O'Carragain <Eoghan.OCarragan@gmail.com>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Lutz Biedinger <lutz.Biedinger@gmail.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrEad3 extends SolrEad
{
    /**
     * Get the institutions holding the record.
     *
     * @param string $language User language version (locale)
     *
     * @return array
     */
    public function getInstitutions($language = null)
    {
        $result = parent::getInstitutions();

        if (! $language) {
            return $result;
        }
        if ($name = $this->getRepositoryName($language)) {
            return [$name];
        }

        return $result;
    }

    /**
     * Return building from index.
     *
     * @param string $language User language version (locale)
     *
     * @return array
     */
    public function getBuilding($language = 'swe')
    {
        $result = parent::getBuilding();

        if (! $language) {
            return $result;
        }
        if ($name = $this->getRepositoryName($language)) {
            return [$name];
        }

        return $result;
    }

    /**
     * Return an array of associative URL arrays with one or more of the following
     * keys:
     *
     * <li>
     *   <ul>desc: URL description text to display (optional)</ul>
     *   <ul>url: fully-formed URL (required if 'route' is absent)</ul>
     *   <ul>route: VuFind route to build URL with (required if 'url' is absent)</ul>
     *   <ul>routeParams: Parameters for route (optional)</ul>
     *   <ul>queryString: Query params to append after building route (optional)</ul>
     * </li>
     *
     * @return array
     */
    public function getURLs()
    {
        $urls = [];
        $url = '';
        $record = $this->getXmlRecord();
        foreach ($record->did->xpath('//daoset/dao') as $node) {
            $url = (string)$node->attributes()->href;
            $desc = $node->attributes()->linktitle ?? $url;
            if (!$this->urlBlacklisted($url, $desc)) {
                $urls[] = [
                    'url' => $url,
                    'desc' => $desc
                ];
            }
        }
        $urls = $this->checkForAudioUrls($urls);
        return $urls;
    }

    /**
     * Get origination
     *
     * @return string
     */
    public function getOrigination()
    {
        $record = $this->getXmlRecord();
        return isset($record->did->origination->name->part)
            ? (string)$record->did->origination->name->part : '';
    }

    /**
     * Get extended origination info
     *
     * @return string
     */
    public function getOriginationExtended()
    {
        $record = $this->getXmlRecord();
        if (!isset($record->did->origination->name)
            || !isset($record->did->origination->name->attributes()->identifier)
        ) {
            return false;
        }
        return [
           'name' => $this->getOrigination(),
           'id' => $record->did->origination->name->attributes()->identifier,
           'type' => 'corporate-author-id'
        ];
    }

    /**
     * Return contributors
     *
     * @return array|null
     */
    public function getContributors()
    {
        $result = [];
        $xml = $this->getXmlRecord();
        if (!isset($xml->did->controlaccess->name)) {
            return $result;
        }

        foreach ($xml->did->controlaccess->name as $name) {
            $data = [
               'id' => $name->attributes()->identifier,
               'type' => 'author-id',
               'role' => $name->attributes()->relator
            ];
            if (isset($name->part)) {
                foreach ($name->part as $part) {
                    if ($part->attributes()->localtype == 'Ensisijainen nimi') {
                        // Assume first entry is the current name
                        $data['name'] = (string)$part;
                        $result[] = $data;
                        break;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $result = [];
        $xml = $this->getXmlRecord();
        if (!isset($xml->relations->relation)) {
            return $result;
        }

        foreach ($xml->relations->relation as $relation) {
            $type = (string)$relation->attributes()->relationtype;
            if ('cpfrelation' !== $type) {
                continue;
            }
            $role = '';
            $arcRole = trim((string)$relation->attributes()->arcrole);
            /*
            switch ($arcRole) {
            case '':
            case 'http://www.rdaregistry.info/Elements/u/P60672':
                $role = 'pro';
                break;
            case 'http://www.rdaregistry.info/Elements/u/P60434':
                $role = 'spk';
                break;
            case 'http://www.rdaregistry.info/Elements/u/P60444':
                $role = 'aut';
                break;
            case 'http://www.rdaregistry.info/Elements/u/P60429':
                $role = 'rcd';
                break;
            case 'http://www.rdaregistry.info/Elements/u/P60434':
                $role = 'drt';
                break;
            default:
            }
            if ('' === $role) {
                continue;
                }*/
            $result[] = [
               'id' => (string)$relation->attributes()->href,
               'type' => 'author-id',
               'role' => $arcRole,
               'name' => trim((string)$relation->relationentry)
            ];
        }
        return $result;
    }

    
    public function getRelatedItems()
    {
        return [
            'parents' => ['ahaa-ng.EAD_6336445_87831063', 'fsd.FSD_ess'],
            'children' => ['ahaa-ng.EAD_6336445_87831063', 'fsd.FSD_ess'],
            'continued-from' => ['ahaa-ng.EAD_6336445_87831063', 'fsd.FSD_ess'],
            'other' => ['ahaa-ng.EAD_6336445_87831063', 'fsd.FSD_ess']
        ];
    }

    public function getLocations()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->altformavail->altformavail)) {
            return [];
        }

        $result = [];
        foreach ($xml->altformavail->altformavail as $altform) {
            $id = $altform->attributes()->id;
            $owner = $label = $serviceLocation = null;
            foreach ($altform->list->defitem as $defitem) {
                $type = $defitem->label;
                $val = (string)$defitem->item;
                switch($type) {
                case 'Tekninen tyyppi':
                    $label = $val;
                    break;
                case 'Säilyttävä toimipiste':
                    $owner = $val;
                    break;
                case 'Tietopalvelun tarjoamispaikka':
                    $serviceLocation = $val;
                    break;
                }
            }
            
            if (!$id || !$owner || !$label) {
                continue;
            }

            if (!isset($result[$owner]['items'])) {
                $result[$owner] = [
                    'providesService' =>
                        $serviceLocation === $owner ? true : $serviceLocation,
                    'items' => []
                ];
            }

            $result[$owner]['items'][] = compact('label', 'id');
        }


        //echo ("locations: " . var_export($result, true));
        
        return $result;
    }

    public function getUnitIds()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->did->unitid)) {
            return [];
        }

        $ids = [];
        foreach ($xml->did->unitid as $id) {
            $label = (string)$id->attributes()->label;
            $val = (string)$id;
            if (!$val) {
                $val = (string)$id->attributes()->identifier;
            }
            if (!$label || !$val) {
                continue;
            }
            $ids[$label] = $val;
        }

        return $ids;
    }

    /**
     * Return translated repository display name from metadata.
     *
     * @param string $language Language code
     *
     * @return string
     */
    protected function getRepositoryName($language)
    {
        $language = $this->mapLanguageCode($language);
        $record = $this->getXmlRecord();

        if (isset($record->did->repository->corpname)) {
            foreach ($record->did->repository->corpname as $corpname) {
                if (! isset($corpname->part)) {
                    continue;
                }
                foreach ($corpname->part->attributes() as $key => $val) {
                    if ($key === 'lang' && (string)$val === $language) {
                        return (string)$corpname->part;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Convert Finna language codes to EAD3 codes.
     *
     * @param string $languageCode Language code
     *
     * @return string
     */
    protected function mapLanguageCode($languageCode)
    {
        $langMap = ['fi' => 'fin', 'sv' => 'swe', 'en-gb' => 'eng'];
        return $langMap[$languageCode] ?? $languageCode;
    }
}
