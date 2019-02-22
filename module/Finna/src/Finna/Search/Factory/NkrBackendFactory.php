<?php

/**
 * Abstract factory for Nkr backends.
 *
 * PHP version 7
 *
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use Interop\Container\ContainerInterface;

/**
 * Abstract factory for Nkr backends.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class NkrBackendFactory
    extends SolrDefaultBackendFactory
{
    protected $nkrConfig;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->facetConfig = 'foo';

        parent::__construct();
        $this->facetConfig = 'facets-nkr';
        $this->searchConfig = 'searches-nkr';
        $this->searchYaml
            = $this->nkrConfig->General->config->searchSpecs ?? $this->searchYaml;
    }

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name (unused)
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $this->nkrConfig = $sm->get('VuFind\Config\PluginManager')->get('Nkr');
        $this->solrCore = $this->nkrConfig->Index->default_core;
        return parent::__invoke($sm, $name, $options);
    }

    /**
     * Get the Solr URL.
     *
     * @param string $config name of configuration file (null for default)
     *
     * @return string|array
     */
    protected function getSolrUrl($config = null)
    {
        $url = $this->nkrConfig->Index->url;
        return "$url/" . $this->solrCore;
    }

}
