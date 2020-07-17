<?php
/**
 * Model for LRMI records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2013-2020.
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
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Model for LRMI records in Solr.
 *
 * @category VuFind
 * @package  RecordDrivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Jaro Ravila <jaro.ravila@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
class SolrLrmi extends SolrQdc
{
    /**
     * File formats that are downloadable
     *
     * @var array
     */
    protected $downloadableFileFormats = [
        'pdf', 'pptx', 'ppt', 'docx', 'mp4', 'mp3',
        'avi', 'odt', 'rtf', 'txt', 'odp'
    ];

    /**
     * Usage rights map
     *
     * @var array
     */
    protected $usageRightsMap = [
        'CCBY4.0' => 'CC BY 4.0',
        'CCBYSA4.0' => 'CC BY-SA 4.0',
        'CCBYND4.0' => 'CC BY-ND 4.0',
        'CCBYNCND4.0' => 'CC BY-NC-ND 4.0',
        'CCBYNCSA4.0' => 'CC BY-NC-SA 4.0',
        'CCBYNC4.0' => 'CC BY-NC-SA 4.0'
    ];

    /**
     * Base url for AOE record page
     */
    protected $aoeUrl = 'https://aoe.fi/#/materiaali/';

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
    public function getAccessRestrictionsType($language = 'fi')
    {
        $xml = $this->getSimpleXML();
        $rights = [];
        if (!empty($xml->rights)) {
            $copyrights = (string)$xml->rights;
            $rights['copyrights']
                = $this->usageRightsMap[$copyrights] ?? $copyrights;
            $rights['link'] = $this->getRightsLink($rights['copyrights'], $language);
            return $rights;
        }
        return false;
    }

    /**
     * Get descriptions
     *
     * @return array descriptions with languages as keys
     */
    public function getDescription()
    {
        $xml = $this->getSimpleXML();
        $descriptions = [];
        $desc = '';
        foreach ($xml->description as $d) {
            $desc = (string)$d;
            $lang = (string)$d['lang'];
            if ($lang === 'en') {
                $lang = 'en-gb';
            }
            $descriptions[$lang] = $desc;
        }
        return $descriptions;
    }

    /**
     * Get educational audiences
     *
     * @return array
     */
    public function getEducationalAudiences()
    {
        return $this->fields['educational_audience_str_mv'] ?? '';
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $xml = $this->getSimpleXML();
        $result = [];
        if (!empty($xml->author)) {
            foreach ($xml->author->person as $author) {
                $result[] = [
                  'name' => $author->name,
                  'affiliation' => $author->affiliation
                ];
            }
            foreach ($xml->author->organization as $org) {
                $result[] = [
                  'name' => $org->legalName
                ];
            }
        }
        return $result;
    }

    /**
     * Return educational levels
     *
     * @return array
     */
    public function getEducationalLevels()
    {
        return isset($this->fields['educational_level_str_mv']) ?
            $this->fields['educational_level_str_mv'] : [];
    }

    /**
     * Return url to AOE record page based on the
     * record ID or false if id is not provided
     *
     * @return string|boolean
     */
    public function getAoeLink()
    {
        $xml = $this->getSimpleXML();
        if ($id = $xml->recordID) {
            $url = $this->aoeUrl . $id;
            return $url;
        }
        return false;
    }

    /**
     * Get educational subjects
     *
     * @return array
     */
    public function getEducationalSubjects()
    {
        return $this->fields['educational_subject_str_mv'] ?? [];
    }

    /**
     * Get educational material type
     *
     * @return array
     */
    public function getEducationalMaterialType()
    {
        return $this->fields['educational_material_type_str_mv'] ?? [];
    }

    /**
     * Check if provided filetype is allowed for download
     *
     * @param string $format file format
     *
     * @return boolean
     */
    public function checkAllowedFileFormat($format)
    {
        if (in_array($format, $this->downloadableFileFormats)) {
            return true;
        }
        return false;
    }

    /**
     * Get file format
     *
     * @param string $filename file name
     *
     * @return string
     */
    public function getFileFormat($filename)
    {
        $parts = explode('.', $filename);
        $ext = array_pop($parts);
        return $ext;
    }

    /**
     * Return array of materials with keys:
     * -url: download link for allowed file types, otherwise empty
     * -title: material title
     * -format: material format
     *
     * @return array
     */
    public function getMaterials()
    {
        $xml = $this->getSimpleXML();
        $materials = [];
        foreach ($xml->material as $material) {
            if (isset($material->format)) {
                $format = $this->getFileFormat((string)$material->name);
                $url = $this->checkAllowedFileFormat($format)
                    ? (string)$material->url : '';
                $title = 'Title Placeholder';
                $materials[] = compact('url', 'title', 'format');
            }
        }
        return $materials;
    }

    /**
     * Get creation date
     *
     * @return string|false
     */
    public function getDateCreated()
    {
        $xml = $this->getSimpleXML();
        if ($created = $xml->dateCreated) {
            return $this->dateConverter->convertToDisplayDate('Y-m-d H:i', $created);
        }
        return false;
    }

    /**
     * Get last modified date
     *
     * @return string|false
     */
    public function getDateModified()
    {
        $xml = $this->getSimpleXML();
        if ($mod = $xml->dateModified) {
            return $this->dateConverter->convertToDisplayDate('Y-m-d H:i', $mod);
        }
        return false;
    }

    /**
     * Get educational use
     *
     * @return array
     */
    public function getEducationalUse()
    {
        $xml = $this->getSimpleXML();
        $uses = [];
        if ($eduUses = $xml->educationalUse) {
            foreach ($eduUses as $use) {
                $uses[] = $use;
            }
        }
        return $uses;
    }

    /**
     * Get educational aim
     *
     * @return array
     */
    public function getEducationalAim()
    {
        $aims = [];
        if (isset($this->fields['educational_aim_str_mv'])) {
            foreach ($this->fields['educational_aim_str_mv'] as $aim) {
                $aims[] = $aim;
            }
            return $aims;
        }
        return false;
    }

    /**
     * Get accessibility features
     *
     * @return array
     */
    public function getAccessibilityFeatures()
    {
        $xml = $this->getSimpleXml();
        $features = [];
        foreach ($xml->accessibilityFeature as $feature) {
            $features[] = $feature;
        }
        return $features;
    }

    /**
     * Get accessibility hazards
     *
     * @return array
     */
    public function getAccessibilityHazards()
    {
        $xml = $this->getSimpleXML();
        $hazards = [];
        foreach ($xml->accessibilityHazard as $hazard) {
            $hazards[] = $hazard;
        }
        return $hazards;
    }
}
