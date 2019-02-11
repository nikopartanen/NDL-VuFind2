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
     * @return array
     */
    public function getInstitutions()
    {
        $result = parent::getInstitutions();

        if (! $this->preferredLanguage) {
            return $result;
        }
        if ($name = $this->getRepositoryName()) {
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
    public function getBuilding()
    {
        $result = parent::getBuilding();

        if (! $this->preferredLanguage) {
            return $result;
        }
        if ($name = $this->getRepositoryName()) {
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
        if (isset($record->did->origination)) {
            foreach ($record->did->origination->name as $name) {
                $localType = $name->attributes()->localType;
                if ($localType === 'http://www.rdaregistry.info/Elements/u/P60672') {
                    if ($name = $this->getDisplayLabel($name)) {
                        return current($name);
                    }
                }
            }
        }
        return '';
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
            if ($arcRole === 'Arkistonmuodostaja') {
                continue;
            }
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
               'name' => current($this->getDisplayLabel($relation, 'relationentry', true)) //trim((string)$relation->relationentry)
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
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->did->physdesc)) {
            return [];
        }

        return $this->getDisplayLabel($xml->did, 'physdesc', true);
    }
    
    public function getBibliographyNotes()
    {
        return [];
    }
    
    public function getTitleStatement()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->bibliography->p)) {
            return null;
        }
        return current($this->getDisplayLabel($xml->bibliography, 'p', true));
    }

    // TODO rename this
    public function getAccessRestrictGranter()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->accessrestrict->p)) {
            return [];
        }
        $restrictions = [];
        foreach ($xml->accessrestrict as $access) {
            if (isset($access->attributes()->encodinganalog) && in_array($access->attributes()->encodinganalog, ['ahaa:KR7'])
                && isset($access->p->name)
            ) {
                return $this->getDisplayLabel($access->p->name, 'part', true);
            }
        }
    }
        
    /**
     * Get access restriction notes for the record.
     *
     * @return string[] Notes
     */
    public function getAccessRestrictions()
    {
        $xml = $this->getXmlRecord();
        if (!isset($xml->accessrestrict->p)) {
            return [];
        }
        $restrictions = [];
        foreach ($xml->accessrestrict as $access) {
            if (empty($access->attributes())) {
                $restrictions += $this->getDisplayLabel($access, 'p', true);
            } else if (isset($access->attributes()->encodinganalog) && in_array($access->attributes()->encodinganalog, ['ahaa:KR5'])) {
                $restrictions += $this->getDisplayLabel($access, 'p', true);
            }
        }

        return $restrictions;
        //return [$this->getDisplayLabel($xml->accessrestrict, 'p', true)];
    }
    
    /**
     * Return type of access restriction for the record.
     *
     * @param string $language Language
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType($language)
    {
        if (! $restrictions = $this->getAccessRestrictions()) {
            return false;
        }
        $copyright = $restrictions[0];
        $data = [];
        $data['copyright'] = $copyright;
        if ($link = $this->getRightsLink(strtoupper($copyright), $language)) {
            $data['link'] = $link;
        }
        return $data;
    }

    /**
     * Return image rights.
     *
     * @param string $language       Language
     * @param bool   $skipImageCheck Whether to check that images exist
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description' Human readable description (array)
     *   'link'        Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights($language, $skipImageCheck = false)
    {
        if (!$skipImageCheck && !$this->getAllImages()) {
            return false;
        }

        $rights = [];

        if ($type = $this->getAccessRestrictionsType($language)) {
            $rights['copyright'] = $type['copyright'];
            if (isset($type['link'])) {
                $rights['link'] = $type['link'];
            }
        }
        $desc = $this->getAccessRestrictions();
        if ($desc && count($desc)) {
            $rights['description'] = $desc[0];
        }

        return isset($rights['copyright']) || isset($rights['description'])
            ? $rights : false;
    }
    
    /**
     * Return translated repository display name from metadata.
     *
     * @return string
     */
    protected function getRepositoryName()
    {
        $record = $this->getXmlRecord();

        if (isset($record->did->repository->corpname)) {
            foreach ($record->did->repository->corpname as $corpname) {
                if ($name = $this->getDisplayLabel($corpname)) {
                    return current($name);
                }
            }
        }
        return null;
    }

    protected function getDisplayLabel(
        $node, $childNodeName = 'part', $obeyPreferredLanguage = true
    ) {
        if (! isset($node->$childNodeName)) {
            return null;
        }
        $language = $this->preferredLanguage
            ? $this->mapLanguageCode($this->preferredLanguage)
            : null;


        $result = [];
        $languageResult = [];

        foreach ($node->{$childNodeName} as $child) {
            foreach ($child->attributes() as $key => $val) {
                $name = trim((string)$child);
                $result[] = $name;
                
                if ($language === null
                    || ($key === 'lang' && (string)$val === $language)
                ) {
                    $languageResult[] = $name;
                    //$name = trim((string)$node->{$childNodeName});
                }
            }
        }

        if (! empty($languageResult)) {
            return $languageResult;
        } else {
            return $obeyPreferredLanguage ? [] : $allResults;
        }
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
