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
            $arcRole = (string)$relation->attributes()->arcrole;
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
            }
            $result[] = [
               'id' => (string)$relation->attributes()->href,
               'type' => 'author-id',
               'role' => $role,
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
}
