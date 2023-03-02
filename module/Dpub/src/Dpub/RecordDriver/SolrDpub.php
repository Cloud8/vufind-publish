<?php
/**
 * Model for Repository records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) Abstract Technologies 2013 - 2020.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */

namespace Dpub\RecordDriver;
use VuFind\RecordDriver\SolrDefault as SolrDefault;
use VuFind\RecordDriver\Feature as Feature;
use VuFind\Connection\Manager as ConnectionManager;
use VuFindHttp\HttpService as Service;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use DOMDocument, XSLTProcessor;
use SimpleXMLElement,VuFind\SimpleXML;

/**
 * @category VuFind
 * @package RecordDrivers
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public 
 * @title Driver for Repository records in Solr.
 */
class SolrDpub extends SolrDefault 
{

    use Feature\IlsAwareTrait;

    private $db; 
    private $domain;
    protected $config;

    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null, $adapterFactory = null) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings, $adapterFactory);
        $this->config = $recordConfig;
        $this->db = $adapterFactory->getAdapterFromConnectionString($this->config->Publish['database']);
        $this->domain = $this->config->Publish['domain'] ?: 1;
        $this->count = 0;
    }

    /**
     * Get an array of all the languages associated with the record.
     *
     * @return array
     */
    /*
    public function getLanguages()
    {
        $languages = isset($this->fields['language']) ?
            $this->fields['language'] : array();
        foreach($languages as &$lang) {
            $lang = $this->translate($lang);
        }
        return $languages;
    }
    */

    /**
     * Get access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        $rdf = $this->getRDFXML();
        $label = null;
        if (!empty($rdf) && isset($this->fields['rights_str'])) {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $dcterms = 'http://purl.org/dc/terms/';
			$rdfs = 'http://www.w3.org/2000/01/rdf-schema#';
			// $rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
			$xml = simplexml_import_dom($dom);
			$xml->registerXPathNamespace('dcterms',$dcterms);
			$xml->registerXPathNamespace('rdfs',$rdfs);
			// $xml->registerXPathNamespace('rdf',$rdf);
            $qpath = "//dcterms:mediator/dcterms:Agent/rdfs:label";
			$label = current($xml->xpath($qpath));
        }

        $result = [];
        if (isset($this->fields['rights_str'])) {
            $ips = explode(" ", $this->fields['rights_str']);
            foreach ($ips as $ip) {
                if ($ip=='ViewOnly') {
                    continue; // spec
                }
                if(empty($label)) {
                    $result[] = $ip; // $this->fields['rights_str'];
                } else {
                    $result[] = $label;
                }
            }
        }
        return $result;
    }

    /**
     * Get access restrictions for the record.
     * Used by RecordController.php
     *
     * @return array
     */
    public function getRestrictions()
    {
        $result = [];
        if (isset($this->fields['rights_str'])) {
            $parts = explode(' ', $this->fields['rights_str']);
            $result = ['rights' => $parts];
            if (isset($this->fields['uri_str'])) {
                $result += ['uri' => $this->fields['uri_str']]; 
            }
        }
        return $result;
    }

    protected function filter() {
        $filter = function ($url) {
            // $desc = substr($url, strrpos($url, '/') + 1);
            $desc = $this->translate('Online Access');
            if (substr($url, -9) == 'graph.pdf') {
                $desc = basename($url); // see eb/2020/0450
                $desc = substr($desc, 0, strpos($desc,'.'));
            } else if (substr($url, -4) == '.pdf') {
                $desc = $this->translate('PDF Full Text');
            } else if (substr($url, 0, 7) == '/ep/000') {
                $desc = $this->translate('Get full text');
            } else if (substr($url, -4) == '.zip') {
                $desc = $this->translate('View Record');
            } else if (substr($url, -4) == '.xml') {
                $url = 'https://dfg-viewer.de/show/?tx_dlf[id]='
                     . urlencode($url);
                $desc = 'DFG-Viewer';
            } else if (substr($url, -5) == '/html') {
                $url = $url . '/index.html';
                $desc = $this->translate('HTML Full Text');
            } else if (substr($url, -10) == 'index.html') {
                $desc = $this->translate('HTML Full Text');
            } else if (substr($url, -4) == '.jpg') {
                $desc = $this->translate('Online Access');
            } else if (substr($url, -4) == '.mp4') {
                $desc = $this->translate('Video');
            } else if (substr($url, -5) == '.docx') {
                $desc = $this->translate('Archival Material');
            } else if (substr($url,0,1) == '[') { // Markdown opus:manuscript
                $x = strpos($url,'](');
                $desc = $this->translate(substr($url, 1, $x-1));
                $url = substr($url,$x+2,strlen($url)-$x-3);
            } else if (substr($url, -9) == '/retrieve') { // DSpace
                $desc = $this->translate('Get full text');
            } else if (strpos($url, '/view/')>0) {
                $desc = $this->translate('Online Access');
            } else if (strpos($url, 'download')>0) {
                $desc = $this->translate('PDF Full Text');
            } else if (strpos($url, '/ep/')>0) {
                $desc = $this->translate('Online Access');
            } else if (strpos($url, 'https://doi.org')===0) {
                // desc == url mangled somewhere
                // $desc = '['.$url.']';
                // $desc = '➜ '.$url;
                $desc = substr($url,8);
            }

            if (isset($this->fields['rights_str'])) {
                // $desc = substr($url, -4) == '.pdf' ? 
                //     $this->translate('PDF Full Text')
                //     : $this->translate('Online Access');
                return ['route'  => 'record-restricted', 
                    'routeParams' => [ 'id' => $this->getUniqueId(),
                        'q' => $url ], 'desc' => $desc ] ;
            } else {
                return [ 'url'  => $url, 'desc' => $desc];
            }
            /*
            } else if (substr($url, -5) == '.epub') {
                return [ 'route' => 'record-view',
                    'routeParams' => [ 'id' => $this->getUniqueId(), 
                        'q' => $url], 'desc' => 'Epub'];
            } else if (substr($url, -4) == '.mp4') {
                return [ 'route' => 'record-view',
                    'routeParams' => [ 'id' => $this->getUniqueId(), 
                        'q' => $url], 'desc' => 'Video'];
            */
        };
        return $filter;
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
    public function getURLs() {
        if (isset($this->fields['url']) && is_array($this->fields['url'])) {
            // sort($this->fields['url']);
            $result = array_map($this->filter(), $this->fields['url']);
            return $result;
        }
        return [];
    }

    private function getSolrRealTimeHoldings()
    {
        $notes = $this->getNotes();
        $identifier = $this->getIdentifier();
        $item = [
            'id' => $identifier, 
            'status' => '',
            // 'location' => '',
            'reserve' => 'N',
            'callnumber' =>  $identifier,
            'number' => '',
            // 'barcode' => '',
            // 'item_notes' => ['Praesenzbestand'],
            'is_holdable' => false,
        ];
        $holding = [
            'location' => '',
            'items' => [$item],
            'textfields' => $notes
        ];
        // $holdings = $this->getTestHoldings();
        $holdings['holdings'][] = $holding;
        return $holdings;
    }

    /** get some notes and statistics from database
      * or ILS if attached (see Factory)
      */
    public function getRealTimeHoldings()
    {
        $holdings = $this->hasILS() ? $this->holdLogic->getHoldings(
            $this->getUniqueID(),
            $this->tryMethod('getConsortialIDs')
        ) : [];

        $uid = $this->fields['callnumber-sort'] ?? null;
        if (empty($holdings)) {
            $holdings = $this->getSolrRealTimeHoldings();
        } else {
            // error_log('GH2022-10-28 use hold logic ' . $uid);
            $holding = null;
            $notes = $this->getNotes();
            $identifier = $this->getIdentifier();
            if (isset($holding['textfields']['holdings_notes'][0])) {
                $downloads = $holding['textfields']['holdings_notes'][0];
                $notes['Downloads'] = [$downloads];
                unset($holding['textfields']['holdings_notes']);
            }
            $holding['textfields'] = $notes;
        }

        // var_dump($holdings);
        // return $holdings['holdings'][] = [];
        return $holdings;
    }

    // https://vufind.org/wiki/development:plugins:ils_drivers#getholding
    /*
    private function getTestHoldings() {
        $item = [ 
            'id' => '424285169', 
            'status' => '',
            'location' => 'Nowhere',
            'reserve' => 'N',
            'callnumber' => 'XXX',
            'number' => 'YYY',
            'barcode' => 'barcode',
            'item_notes' => ['Praesenzbestand'],
            'is_holdable' => false,
        ];
        $holding = [
            'location' => '',
            'items' => [$item],
            // 'textfields' => $notes
        ];
        $holdings['holdings'][] = $holding;
        return $holdings;
        // return $holdings['holdings'][] = [];
    }
    */

    private function getIdentifier() {
        $identifier = '';
        if (isset($this->fields['ctrlnum'])) { // opus:manuscript
            foreach($this->fields['ctrlnum'] as $signatur) {
                $identifier = $signatur;
            }
        } else {
            $urn = $this->getUrn();
            if (!empty($urn)) {
                $identifier = $urn;
            }
        }
        return  $identifier;
    }

    private function getNotes() {
        $notes = [];
        if (isset($this->fields['first_indexed'])) {
            $issued = substr($this->fields['first_indexed'],0,10);
            $notes['Dpub::Date published'] = [$issued];
        }

        if (isset($this->fields['external_str_mv'])) {
            foreach($this->fields['external_str_mv'] as $field) {
                $link = $this->linkify($field);
                if (count($link)==2) {
                    $notes[$this->translate($link[0])] = [$link[1]];
                }
            }
        }

        if ($this->hasILS()) {
            // Use Downloads from ILS
        } else foreach($this->getDownloads() as $key => $val) {
            $notes[$key] = [$val];
        }

        $txt = $this->getPermission();
        if (empty($txt)) {
            // Skip
        } else {
            $notes[$this->translate('License')] = [$txt];
        }

        /* currently not supported
        if (isset($this->fields['version_str_mv'])) {
            foreach($this->fields['version_str_mv'] as $version) {
                $label = substr($version, strrpos($version,'/')+1);
                $link = substr($version, strpos($version,':')+1);
                $notes['Version '.$label][] = $link;
            }
        }
        */

        if (isset($this->fields['uri_str'])) { // about
            $notes['Access URL'][] = $this->fields['uri_str'];
        }

        if (isset($this->fields['doi_str_mv'])) { 
            $doi = current($this->fields['doi_str_mv']);
            // if (str_contains($doi, '10.48643/b4tm-')) {
            //     // corvey:manuscript
            //     $notes['Corvey Digital'][] = $doi;
            // } else {
                $notes['Access URL'][] = $doi;
            // }
        } 

        return $notes;
    }

    /**
     * Get an array of lines from the table of contents.
     *
     * @return array
     */
    public function getTOC()
    {
        // see templates/RecordTab/toc.phtml : 
        // non-array content is treated as raw HTML
        if (isset($this->fields['contents'])) {
            $content = $this->fields['contents'];
            if (count($content) == 1) {
		        return current($content);
            } else {
		        return $content;
            }
        } else {
            return [];
        }
    }

    /**
     * Get the value of whether or not this is a top level container record
     *
     * @return bool
     */
    public function isToplevel()
    {
        if (isset($this->fields['hierarchy_top_id'])) { 
            $oid = $this->getUniqueId();
            $b = in_array($oid, $this->fields['hierarchy_top_id']);
            // error_log('GH2023-02 isToplevel '.$b.' '.$oid);
            return in_array($oid, $this->fields['hierarchy_top_id']);
        } else {
            return false;
        }
    }

    /**
     * Get the container record id.
     *
     * @return string Container record id (empty string if none)
     */
    public function getContainerRecordID()
    {
        // Unsupported by default
        $result = '';
        if (isset($this->fields['container_title'])) { 
            if (isset($this->fields['hierarchy_top_title']) 
                && isset($this->fields['hierarchy_top_id'])) { 
                $key = array_search($this->fields['container_title'], $this->fields['hierarchy_top_title']);
                if ($key===0) {
                    $result = current($this->fields['hierarchy_top_id']);
                } else if (!empty($key)) {
                    $result = $this->fields['hierarchy_top_id'][$key];
                } else if (isset($this->fields['is_hierarchy_id'])) {
                    $result = $this->fields['is_hierarchy_id']; // Journal
                } else {
                    // error_log('GH2020-08 Zero ContainerRecordID');
                }
            }
        }
        return $result;
    }

    /**
     * Get an associative array (id => title) of collections containing this record.
     * Suppress collection entry for serials to avoid duplicates
     *
     * @return array
     */
    public function getContainingCollections()
    {
        $colls = parent::getContainingCollections();
        if (empty($this->fields['container_title'])) {
            //
        } else if (is_array($colls)) {
            foreach($colls as $key=>$val) {
                if ($val == $this->fields['container_title']) {
                    unset($colls[$key]); // filter out serials
                }
            }
        }
        return $colls;
    }

    /**
     * Returns true if the record supports real-time AJAX status lookups.
     *
     * @return bool
     */
    public function supportsAjaxStatus()
    {
        return false;
    }

    /** IIIF manifest export */
    public function getIIIF() {
        $rdf = $this->getRDFXML();
        $res = '{}';
        if (!empty($rdf)) {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $dcterms = 'http://purl.org/dc/terms/';
            $dctypes = 'http://purl.org/dc/dcmitype/';
			$xml = simplexml_import_dom($dom);
			$xml->registerXPathNamespace('dcterms',$dcterms);
            $q = "//dcterms:hasFormat/dctypes:Text/@rdf:about";
            $m = current($xml->xpath($q));
            if (empty($m) || substr($m, -8)!=='manifest') {
                $xslt = $this->config->View['manifest'];
                // error_log('Create manifest ' . $m . ' ' . $xslt);
                $res = $this->transform($dom, $xslt);
            } else { // corvey:manuscript
                error_log('Foreign manifest ' . $m);
                // No stinking CERTS :
                $a = [ 'ssl' => ['verify_peer' => false, 
                    'verify_peer_name' => false, ]];
                $res = file_get_contents($m, false, stream_context_create($a));
            }
        }
        return $res;
    }

    /** METS / TEI export */
    public function getMetsTei() {
        $rdf = $this->getRDFXML();
        $result = '{}';
        if (!empty($rdf)) {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $xslt = $this->config->View['teimets'];
            $result = $this->transform($dom, $xslt);
        }
        return $result;
    }

    /** METS / LZA export Rosetta */
    public function getMetsLza() {
        $rdf = $this->getRDFXML();
        $result = '{}';
        if (!empty($rdf)) {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $xslt = $this->config->OAI['metslza'];
            $result = $this->transform($dom, $xslt);
            // Rosetta HACK
            $x = '<dc:record xmlns:dc="http://purl.org/dc/elements/1.1/">';
            $y = '<dc:record xmlns:dc="http://purl.org/dc/elements/1.1/"'
               . ' xmlns:dcterms="http://purl.org/dc/terms/"'
               . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
            $result = str_replace($x, $y, $result);
        }
        return $result;
    }

    /** JSON export */
    /*
    public function getJson() {
        $rdf = $this->getRDFXML();
        $xml = simplexml_load_string($rdf);
        $json = '{}';
        // $json = json_decode(json_encode((array) $xml), 1);
        // $obj = new stdclass();
        // $obj->webservice[] = $xml;
        // $data = json_encode($obj);
        return $json;
    }
    */

    /** RDF export */
    public function getRDFXML() {
        $rdf = $this->getSolrRDF();
        if (empty($rdf)) {
            $rdf = $this->getCachedRDF();
        }
        if (empty($rdf) || substr($rdf,1,3)!=='rdf') {
            $url = $this->getRDFLink();
            error_log('Nothing to read from [' . $url . ']');
            // make sure nothing bad happens
            $rdf = '<rdf:RDF '
                 . 'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                 . '</rdf:RDF>';
        }
        return $rdf;
    }

    // Deprecated: file based rdf description
    private function getCachedRDF() {
        $url = $this->getRDFLink();
        $data = null;
        if (file_exists($url)) {
            $data = file_get_contents($url);
        } else if (empty($url)) {
            error_log('Zero url [' . $this->getUniqueId() . ']');
        } else if (strpos($url,'/', 8) === false) {
            error_log('Bad url [' . $url . ']');
        } else {
            $dir = substr($url, strpos($url,'/', 8)). '/*.rdf';
            $files = glob($_SERVER['DOCUMENT_ROOT'] . $dir);
            $file = reset($files);
            if (file_exists($file)) {
                $data = file_get_contents($file);
            }
        } 
        return $data;
    }

    private function getSolrRDF() {
        $rdf = null;
        if (isset($this->fields['fullrecord'])) {
            $rdf = $this->fields['fullrecord'];
        }
        return $rdf;
    }

    private function getRDFLink() {
        $url = isset($this->fields['url']) ? $this->fields['url'][0] : null;
        $uri = isset($this->fields['uri_str']) ? $this->fields['uri_str']:null; 
        if (!empty($url) && substr($url,0,7) == 'file://') {
            $url = substr($url,7);
            $url = substr_replace($url, '.rdf', -4);
            if (ctype_upper(substr($url,0,1))) {
                if (isset($this->config->Doklief['dbox'])) {
                    $url = $this->config->Doklief['dbox'] . '/' . $url;
                }
            }
            return $url;
        } else {
            return $uri;
        }
    }

    public function getPicaRecord()
    {
        $rec  = '0500 Oau'.PHP_EOL;
        $rec .= '1100 '.current($this->getPublicationDates()).PHP_EOL;
        $urn = $this->getUrn();
        if (!empty($urn)) {
            $rec .= '2050 ##0##'.$urn.PHP_EOL;
        }
        if (isset($this->fields['doi_str_mv'])) {
            $doi = current($this->fields['doi_str_mv']);
            $doi = substr($doi, strpos($doi, '/', 8)+1);
            $rec .= '2051 ##0##'.$doi.PHP_EOL;
        }
        foreach($this->getPrimaryAuthors() as $primary) {
            $rec .= '3000 '.$primary.PHP_EOL;
        }
        $rec .= '4000 '.$this->getTitle().PHP_EOL;
        if (isset($this->fields['uri_str'])) {
            $rec .= '4085 ##0##=s MB=u '.$this->fields['uri_str']."=x H\n";
        }
        if (empty($this->fields['dewey-raw'])) {
            //
        } else if (is_array($this->fields['dewey-raw'])) {
            $rec .= '5050 |'.current($this->fields['dewey-raw']).PHP_EOL;
        }
        if (empty($this->fields['topic'])) {
            //
        } else if (is_array($this->fields['topic'])) {
            foreach($this->fields['topic'] as $topic) {
                $rec .= '5584 '. $topic.PHP_EOL;
            }
        }
        if (isset($this->fields['uuid_str_mv'])) {
            foreach($this->fields['uuid_str_mv'] as $uid) {
                if (substr($uid,0,5) === 'opus:') {
                    $rec .= $uid.PHP_EOL;
                }
            }
        }
        return $rec;
    }

    public function getGeneralNotes()
    {
        $res = [];
        $count = 0;
        if (isset($this->fields['description_str_mv'])) {
            arsort($this->fields['description_str_mv']);
            foreach($this->fields['description_str_mv'] as $field) {
                $link = $this->linkify($field);
                // error_log('field: ' . $field);
                if (count($link)==2) {
                    $res[] = '<a href="'.$link[1].'">'.$link[0].'</a>';
                } else if ($count>0) {
                    $res[] = '<br/>' . $field;
                } else {
                    $res[] = $field;
                }
                $count++;
            }
        } 
        return $res;
    }

    /* reference extension : used by record landing page */
    public function hasReferences()
    {
        if (isset($this->fields['references_str'])) {
            return true; // since 2020-04
        }
        return false;
    }

    /** reference extension */
    public function getReferences()
    {
        $agent = $_SERVER['HTTP_USER_AGENT'];
        $white = strpos($agent, 'Mozilla') !== false;
        $black = stripos($agent, 'Crawl') !== false;
        $black = $black ? $black : stripos($agent, 'Bot') !== false;
        $black = $black ? $black : stripos($agent, 'spider') !== false;

        if (empty($white) || !empty($black)) {
            error_log('Zero refs for ' . $white . ' ' . $agent);
        } else if (isset($this->fields['fullrecord'])) {
            // error_log('Load refs from fullrecord ' . $agent);
            return $this->getRDFReferences();
        } else if (isset($this->fields['references_str'])) {
            error_log('Load refs from dump ' . $agent);
            return $this->getRDFReferences();
            // return $this->getSolrReferences();
        } 
        return [];
    }

    private function getRDFReferences()
    {
        $result = [];
        $rdf = $this->getRDFXML();
        if (empty($rdf)) {
             // Zero
        } else {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $dct = 'http://purl.org/dc/terms/';
			$rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
            $i = 1;
			$xml = simplexml_import_dom($dom);
			$xml->registerXPathNamespace('dct',$dct);
			$xml->registerXPathNamespace('rdf',$rdf);
			$refs = $xml->xpath("//dct:references/rdf:Seq/rdf:li");
            foreach ($refs as $item) {
				$childs = $item->children($dct);
				if ($childs->count()==0) { // internal reference
					$url = $item->attributes($rdf)['resource'];
                    if (0===strpos($url, 'http://localhost/ref/')) {
                        //
                    } else {
                        $result[] = array('ref'=> $i++.". ".$url,'url'=> $url);
                    }
				} else {
				    $url = $childs->attributes($rdf)[0];
                    $cite = $childs->children($dct)->bibliographicCitation;
				    if (substr($url,0,17)=='http://localhost/') {
                        $title = $childs->children($dct)->title;
                        //$url = 'http://scholar.google.de/scholar?hl=de&q='
                        $url = 'https://www.base-search.net/Search/Results'
                             . '?lookfor=' . urlencode($title);
				    }
                    $result[] = array('ref' => $i++.". ".$cite, 'url' => $url);
				}
			}
        }
        return $result;
    }

    /*
    private function getSolrReferences()
    {
        $result = [];
        $i = 0;
        foreach($this->fields['ref_str_mv'] as $field) {
            $i++;
            $ref = explode(' :: ', $field, 2);
            if (count($ref)>1 && substr($ref[1],0,4)==='http') {
                $url = $ref[1];
                $result[] = array('ref' => $i.". ".$ref[0], 'url' => $url);
            } else if (count($ref)>1) {
                $url = 'https://www.base-search.net/Search/Results'
                       . '?lookfor=' . urlencode($ref[1]);
                $result[] = array('ref' => $i.". ".$ref[0], 'url' => $url);
            } else { 
                $result[] = array('ref' => $i.". ".$field );
            }
        }
        return $result;
    }
    */

    /* called from record landing page - should be fast. */
    public function hasCitations()
    {
        if (isset($this->fields['cites_str'])) {
            return ['a', 'b'];
        } 
        return [];
    }

    /** citation extension : parse and make it easy to render as link */
    public function getCitations()
    {
        // if (isset($this->fields['cites_str_mv']) 
        //     && is_array($this->fields['cites_str_mv'])) {
        //     $result = [];
        //     foreach($this->fields['cites_str_mv'] as $field) {
        //         $url = substr($field, strpos($field,'/', 8));
        //         $result[] = array('ref' => $field, 'url' => $url);
        //     }
        //     return $result;
        // }
        $result = [];
        $rdf = $this->getRDFXML();
        if (empty($rdf)) {
             // Zero
        } else {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $dct = 'http://purl.org/dc/terms/';
			$rdf = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#';
            $i = 1;
			$xml = simplexml_import_dom($dom);
			$xml->registerXPathNamespace('dct',$dct);
			$xml->registerXPathNamespace('rdf',$rdf);
			$refs = $xml->xpath("//dct:isReferencedBy/rdf:Seq/rdf:li");
            foreach ($refs as $item) {
                $childs = $item->children($dct);
                if ($childs->count()==0) {
                    //
                } else {
                    $url = $childs->attributes($rdf)[0];
                    $title = $childs->children($dct)->title;
                    // error_log($title . ' : ' . $url);
                    $result[] = ['ref' => $i++.". ".$title, 'url' => $url];
				    $childs = $item->children($dct);
                }
			}
        }
        return $result;
    }

    /**
     * Get an array of physical descriptions of the item.
     *
     * @return array
     */
    public function getPhysicalDescriptions()
    {
        $res = [];
        if (isset($this->fields['physical'])) {
            foreach($this->fields['physical'] as $field) {
                if (is_numeric($field)) {
                    $res[] = $field.' '.$this->translate('pages');
                } else if (substr_count($field, ':')==2) {
                    $res[] = $field.' '.$this->translate('duration');
                } else {
                    $res[] = $field;
                }
            }
        }
        return $res;
    }

    /** return string with licensing information */
    public function getPermission()
    {
        if (isset($this->fields['license_str'])) {
            $lic = $this->fields['license_str'];
            if (strpos($lic, '/InC/') > 0) {
                // restricted 
                return $this->translate('Copyright') . ' ' . $lic;
            } else if (strpos($lic, '/NoC-OKLR/') > 0) {
                // digitized material
                return $this->translate('Dpub::Digitized') .' ('.$lic.')';
            } else {
                return $lic;
            }
        }
        return false;
    }

    /** retrieve additional info from database */
    public function getDownloads()
    {
        $result = [];
        $sql = null;
        $uid = $this->fields['callnumber-sort'] ?? null;
        if (empty($uid)) {
            error_log('stat failed  with empty uid');
        } else {
            // 5 Year stat 
            $dom = $this->domain;
            $sql = 'select GROUP_CONCAT(stat, " (", year, ")"'
                . ' ORDER BY year desc SEPARATOR ", ") as Downloads'
                . ' from opus_logstat where uid="'.$uid.'" and domain='.$dom;
            // error_log('logstat ['.$uid.']');
        } 

        $data = [];
        if (empty($sql)) {
            //
        } else {
            $stmt = $this->db->query($sql);
            $data = $stmt->execute()->getResource()->fetch();
        }

		foreach($data as $key => $value) {
            if (is_int($key)) {
                // 
            } else if (empty($value)) {
                //
            } else if ($key==='Created') {
                $result += ['Publication Date' => $value];
            } else if ($key==='Source' && strpos($value,'/')===0) {
                // indexed as detail
            } else {
                $result += [$key => $value];
            }
        }
        return $result;
    }

    /** OAI support: oai_dc epicur RDF xMetaDissPlus */
    public function getXML($format, $baseUrl = null, $recordLink = null) {
        if ($format == 'oai_dc') {
            return $this->getOAI_DC($format, $baseUrl, $recordLink);
        }
        if (isset($this->fields['uri_str'])) {
            if ($format == 'epicur') {
                return $this->getOAIEpicur();
            } else if ($format == 'xMetaDissPlus') {
                return $this->getXMDP();
            } else if ($format == 'rdf') {
                return $this->getOAIRDF();
            } else if ($format == 'ore') {
                return $this->getOAIORE();
            } else if ($format == 'datacite') {
                return $this->getDataCite();
            } else if ($format == 'mets') {
                return $this->getMetsTei();
            } else if ($format == 'lza') {
                return $this->getMetsLza();
            }
        }
        return '';
    }

    public function getXMDP() {
        $result = ''; // zero data
		if (isset($this->fields['oai_set_str_mv'])) {
            if (in_array('xMetaDissPlus', $this->fields['oai_set_str_mv'])) {
                $xslt = $this->config->OAI['rdf2xmdp'];
                if (empty($xslt)) {
                    $result = $this->getCachedXMDP();
                } else {
                    $rdf = $this->getRDFXML();
                    if (empty($rdf)) {
                        error_log('Zero RDF, total disaster');
                        $result = $this->getCachedXMDP();
                    } else {
                        $dom = new DOMDocument();
                        $dom->loadXML($rdf);
                        $result = $this->transform($dom, $xslt);
                    }
                }
            }
        }
        return $result;
    }

    private function getCachedXMDP() {
        $uri = isset($this->fields['uri_str']) ? $this->fields['uri_str']:null; 
        if (empty($uri)) {
            return '';
        }
        $dir = $_SERVER['DOCUMENT_ROOT'] . substr($uri, strpos($uri,'/', 8));
        $data = glob($dir . '/xmdp*.xml');
        $data = empty($data) ? glob($dir .'/*.xmdp') : $data;
        $about = reset($data);
        if (empty($about)) {
            return '';
        } else if (file_exists($about)) {
            return file_get_contents($about);
        } else { // skip records if not prepared
            return '';
        } 
        return ''; // no data -- return false to throw OAIServer Exception
    }

    /** see http://datacite.org/schema/kernel-4 : used as export format */
    public function getDataCite() {
        $result = null;
        if (empty($this->fields['doi_str_mv'])) {
		    //
        } else {
            $rdf = $this->getRDFXML();
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            $xslt = $this->config->OAI['rdfDCite'];
            $result = $this->transform($dom, $xslt);
        } 
        if (empty($result) && isset($this->fields['uri_str'])) {
            $uri = $this->fields['uri_str']; 
			error_log('Failed DataCite ' . $uri);
            $result = '<null/>';
        }
        return $result;
    }

    /** see http://www.openarchives.org/ore/1.0/terms */
    private function getOAIORE() {
        $result = '';
        $rdf = $this->getRDFXML();
        if (!empty($rdf)) {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            if (isset($this->config->OAI['rdf2ore'])) {
                $xslt = $this->config->OAI['rdf2ore'];
                $result = $this->transform($dom, $xslt);
            }
        } 
        return $result;
    }

    /* see http://www.openarchives.org/ore/1.0/rdfxml */
    private function getOAIRDF() {
        return $this->getRDFXML();
    }

    private function getOAIEpicur() {
        $urn = $this->getUrn();
        if (!empty($urn) && isset($this->fields['uri_str']))
        {
            $url = $this->fields['uri_str'];
            $xml = new \SimpleXMLElement(
            '<epicur '
                    . 'xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" '
                    . 'xmlns:epicur="urn:nbn:de:1111-2004033116" '
                    . 'xmlns="urn:nbn:de:1111-2004033116" '
                    . 'xsi:schemaLocation="urn:nbn:de:1111-2004033116 '
                    . 'http://www.persistent-identifier.de'
                    . '/xepicur/version1.0/xepicur.xsd" />'
                    );
            $adm = $xml->addChild('administrative_data');
            $delivery = $adm->addChild('delivery');
            $status = $delivery->addChild('update_status');
            $status->addAttribute('type', 'urn_new');
            $record = $xml->addChild('record');
            $ident = $record->addChild('identifier', $urn);
            $ident->addAttribute('scheme','urn:nbn:de');
            $resource = $record->addChild('resource');
            $ident = $resource->addChild('identifier', $url);
            $ident->addAttribute('scheme','url');
            $ident->addAttribute('type','frontpage');
            $ident->addAttribute('role','primary');
            return $xml->asXml();
        }
        return '';
    }

    public function getOAI_DC($format, $baseUrl = null, $link = null) {
        $result = '';
        $rdf = $this->getRDFXML();
        if (empty($rdf)) {
            // 
        } else {
            $dom = new DOMDocument();
            $dom->loadXML($rdf);
            if (isset($this->config->OAI['rdfDCore'])) {
                $xslt = $this->config->OAI['rdfDCore'];
                $result = $this->transform($dom, $xslt);
            }
        } 
        if (empty( $result)) {
            $result = $this->getOAI_DC_Simple($format, $baseUrl, $link);
        }
        return $result;
    }

    // If xslt transform fails for any reason, produce simple XML:
    private function getOAI_DC_Simple($format, $baseUrl = null, $link = null) {
        if ($format == 'oai_dc') {
            $dc = 'http://purl.org/dc/elements/1.1/';
			$xsi = 'http://www.w3.org/2001/XMLSchema-instance'; 
            $xml = new \SimpleXMLElement(
                '<oai_dc:dc '
                . 'xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/" '
                . 'xmlns:dc="' . $dc . '" '
                . 'xmlns:xsi="' . $xsi .'" '
            . 'xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ '
            . 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd" />'
            );
            $xml->addChild('title', htmlspecialchars($this->getTitle()), $dc);
            $ccount = 0;
            foreach($this->getPrimaryAuthors() as $primary) {
                if (!empty($this->fields['author_variant'][$ccount])) {
                    $authorid = $this->fields['author_variant'][$ccount];
                    if (substr_count($authorid, '-')==3) {
                        $primary .= '; ' . $authorid;
                    }
                }
                $xml->addChild('creator', htmlspecialchars($primary), $dc);
                $ccount++;
            }
            foreach ($this->getSecondaryAuthors() as $current) {
                $xml->addChild('contributor', htmlspecialchars($current), $dc);
                $ccount++;
            }
            if (isset($xml->creator)) { // creator is mandatory
                // this is always set with simplexml, thats why we count
            } else if ($ccount==0 && empty($this->getPublishers())) {
                // bad
            } else if ($ccount==0) {
                $xml->addChild('creator', current($this->getPublishers()), $dc);
            }
            foreach ($this->getCorporateAuthors() as $corp) {
                $xml->addChild('contributor', htmlspecialchars($corp), $dc);
            }
            if (!isset($this->fields['language'])) {
                $xs0 = $xml->addChild('language', 'eng', $dc);
            } else { // DINI requires ISO 639-3 codes
                foreach ($this->fields['language'] as $lang) {
                    //$xs0 = $xml->addChild('language', $lang, $dc);
                    if ($lang == 'German') {
                        $xs0 = $xml->addChild('language', 'deu', $dc);
                    } else if ($lang == 'English') {
                        $xs0 = $xml->addChild('language', 'eng', $dc);
                    } else if ($lang == 'French') {
                        $xs0 = $xml->addChild('language', 'fra', $dc);
                    } else if ($lang == 'Latin') {
                        $xs0 = $xml->addChild('language', 'lat', $dc);
                    } else { // dini oai validator is crazy
                        $xs0 = $xml->addChild('language', 'deu', $dc);
                    }
                }
            }
            $pub = implode(', ',$this->getPublishers());
            if (!empty($pub)) {
                $xml->addChild('publisher', htmlspecialchars($pub), $dc);
            }
            foreach ($this->getPublicationDates() as $date) {
                $xml->addChild('date', htmlspecialchars($date), $dc);
            }
            $sub = null;
            foreach ($this->getAllSubjectHeadings() as $subj) {
                //$xml->addChild(
                //    'subject', htmlspecialchars(implode(' -- ', $subj)), $dc
                //);
                $sub .= implode(' -- ', $subj) . ' ; ';
            }
            if (!empty($sub)) {
                $xml->addChild('subject', htmlspecialchars(substr($sub,0,-2)), $dc);
            }
		    if (isset($this->fields['topic_facet'])) {
                $xml->addChild(
                    'subject', htmlspecialchars(implode(' -- ', $this->fields['topic_facet'])), $dc
                );
            }
            if (isset($this->fields['doi_str_mv'])) { // the best
                $xml->addChild('identifier', current($this->fields['doi_str_mv']), $dc);
            } else if (isset($this->fields['uri_str'])) {
                $xml->addChild('identifier', $this->fields['uri_str'], $dc);
            } else {
                $urn = $this->getUrn();
                if (!empty($urn)) {
                    $xml->addChild('identifier', $urn, $dc);
                }
            }

            $ccount = 0;
		    if (isset($this->fields['oai_set_str_mv'])) {
		        foreach ($this->fields['oai_set_str_mv'] as $type) {
		            if (substr($type, 0, 9) == 'doc-type:') {
                        $xml->addChild('type', $type, $dc);
                        $ccount++;
			        } else if (substr($type, 0, 4) == 'ddc:') {
                        $xml->addChild('subject', $type, $dc);
			        } else if (substr($type, 0, 5) == 'issn:') {
                        //$xml->addChild('source', substr($type,5), $dc);
                        $xml->addChild('source', $type, $dc);
                    }
		        }
		    }
            if ($ccount==0) { // dc:type is mandatory
                $xml->addChild('type', $this->docType(), $dc);
            }

		    if (isset($this->fields['license_str'])) {
                $xml->addChild('rights', $this->fields['license_str'], $dc);
            }

		    if (isset($this->fields['hierarchy_top_title'])) {
                $source = '';
		        foreach ($this->fields['hierarchy_top_title'] as $val) {
                    $source .= htmlspecialchars($val).' ';
                }
                if (!empty(trim($source))) {
                    $xml->addChild('source', $source, $dc);
                }
            }
		    if (isset($this->fields['description'])) {
                $val = $this->fields['description'];
                $xml->addChild('description', htmlspecialchars($val), $dc);
            }
		    //DINI recommends dc:relation dc:contributor dc:source dc:provenance
		    if (isset($this->fields['author_additional'])) {
                $val = htmlspecialchars(reset($this->fields['author_additional']));
                $xml->addChild('contributor', $val, $dc);
			}
            return $xml->asXml();
        }

        // Unsupported format:
        return false;
    }

    /** returns doc-type for oai_dc */
    private function docType() {
		if (isset($this->fields['format'])) {
            switch(current($this->fields['format'])) {
                case 'Article':
				    return 'doc-type:article';
                case 'Dissertation':
                    return 'doc-type:doctoralThesis';
                case 'Book Chapter':
				    return 'doc-type:bookPart';
                case 'Book':
				    return 'doc-type:book';
                case 'Image':
				    return 'doc-type:Image';
                case 'Issue':
				    return 'doc-type:Periodical';
                case 'Journal':
				    return 'doc-type:Periodical';
                case 'Journal Articles':
				    return 'doc-type:article';
                case 'Manuscript':
				    return 'doc-type:Manuscript';
                case 'Musical Score':
				    return 'doc-type:MusicalNotation';
                case 'Volume Holdings':
				    return 'doc-type:Periodical';
                case 'Volume':
				    return 'doc-type:Periodical';
                case 'Work':
				    return 'doc-type:workingPaper';
                case 'Series':
				    return 'doc-type:Periodical';
            }
        }
        return 'doc-type:report';
    }

    /** returns transformed string */
    private function transform($dom, $xslFile) {
        $xsl = new XSLTProcessor();

        // Load up the style sheet
        $style = new DOMDocument;
        if (!$style->load($xslFile)) {
            throw new \Exception("Problem loading XSL file: {$xslFile}.");
        }
        $xsl->importStyleSheet($style);

        // Process and return the XML through the style sheet
        $result = $xsl->transformToXML($dom);
        if (empty($result)) {
            error_log('SolrDpub: Problem transforming '.$this->getEdition());
            // throw new \Exception("Problem transforming XML. " . $this->getEdition());
        }
        return $result;
    }

    /*
     * Return the first valid DOI found in the record (false if none).
     *
     * @return mixed
     */
    public function getCleanDOI()
    {
        $field = 'doi_str_mv';
        $doi = !empty($this->fields[$field][0]) ? $this->fields[$field][0] : false;
        if (empty($doi)) {
            //
        } else if (strpos($doi, 'http')===0) {
            $doi = substr($doi, strpos($doi,'/',8)+1);
        }
        return $doi;
    }
 
    /**
     * Get Google Scholar Tags -- see toolbar.phtml
     *
     * @return array
     */
    public function getGoogleScholarTags()
    {
        $meta = array();
        $format = $this->getOpenURLFormat();
        $pubDate = $this->getPublicationDates();
        $pubDate = empty($pubDate) ? '' : $pubDate[0];

        $title = $this->fields['title_full'] ?? $this->getTitle(); 
        $meta[] = ['name' => 'citation_title', 'content' => $title];
        foreach($this->getPrimaryAuthors() as $primary) {
            $meta[] = [ "name" => "citation_author", "content" => $primary ];
        }

        $meta[] = [ 'name' => 'citation_publication_date',
                    'content' => $pubDate ];

        $urls = current($this->getURLs()) ?? [];
        $url = $urls['url'] ?? null;
        $meta[] = [ 'name' => 'citation_pdf_url', 'content' => $url ];

        switch ($format) {
        case 'Edited book':
        case 'Authored book':
        case 'Book':
            $meta[] = array(
                "name" => "citation_isbn",
                "content" => $this->getCleanISBN()
            );
            break;
        case 'Journal article':
        case 'Article':
            $meta[] = array(
                "name" => "citation_issn",
                "content" => $this->getCleanISSN()
            );
            $meta[] = array(
                "name" => "citation_volume",
                "content" => $this->getContainerVolume()
            );
            $meta[] = array(
                "name" => "citation_issue",
                "content" => $this->getContainerIssue()
            );
            $meta[] = array(
                "name" => "citation_firstpage",
                "content" => $this->getContainerStartPage()
            );
            $meta[] = array(
                "name" => "citation_journal_title",
                "content" => $this->getContainerTitle()
            );
            break;
        case 'Journal':
            $meta[] = array(
                "name" => "citation_issn",
                "content" => $this->getCleanISSN()
            );
        default:
            break;
        }

        if ( isset($this->fields['license_str']) ) { 
            $meta[] = [
                "name" => "DC.rights",
                "content" => $this->fields['license_str']
            ];
        }
        return $meta;
    }

    private function getUrn() {
        $urn = null;
        if (isset($this->fields['urn_str'])) {
            return $this->fields['urn_str'];
        } else if (isset($this->fields['uuid_str_mv'])) {
            foreach($this->fields['uuid_str_mv'] as $uid) {
                if (substr($uid,0,4) === 'urn:') {
                    $urn = $uid;
                }
            }
        }
        return $urn;
    }

    /**
    *  return array of links ['One' => 'http://a.b', 'Two' => 'http://c.d']
    */
    private function linkify($value) {
        $result = [];
        $data = explode('[', $value);
        foreach ($data as $row) {
            if (empty($row)) {
                // 
            } else {
                $x = strpos($row,'](');
                $y = strrpos($row,')');
                if (0<$x && $x<$y) {
                    $desc = substr($row, 0, $x);
                    $url = substr($row, $x+2, $y-($x+2));
                    $desc = empty($desc) ? $url : $desc;
                    $desc = $desc=='Nachweis' ? $url : $desc;
                    $result += [ $desc , $url ];
                }
            }
        }

        if (empty($value)) {
            //
        } else if (empty($result) && strpos($value, 'http')===0) {
            // linkify
            $result += [ $value , $value ];
        }

        return $result;
    }

}
