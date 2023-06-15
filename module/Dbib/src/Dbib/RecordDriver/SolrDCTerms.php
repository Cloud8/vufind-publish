<?php
/**
 * Model for DCTerms records in Solr.
 *
 * PHP version 8
 *
 * Copyright (C) Abstract Technologies 2013 - 2023.
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

namespace Dbib\RecordDriver;
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
class SolrDCTerms extends SolrDefault 
{

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
     * Return a URL to a thumbnail preview of the record, if available; false
     * otherwise.
     *
     * @param array $size Size of thumbnail (small, medium or large -- small is
     * default).
     *
     * @return string|bool
     */
    public function getThumbnail($size = 'small')
    {
        $url = null;
        $self = $_SERVER['CONTEXT_PREFIX'].'/Record/'.$this->getUniqueId();
        if ($size == 'large' && isset($this->fields['rights_str'])) {
            $url = $self . '/Restricted';
        } else if ($size == 'large') {
            $url = $self . '/View';
        } else if (empty($this->fields['thumbnail'])) {
            //
        } else {
            // find content streaming path
            $img = trim($this->fields['thumbnail']);
            if (ctype_upper(substr($img,0,1)) || substr($img,0,7)=='file://') {
                $url = $self . '/SMS?q=' . $img;
                // $url = $self . '/Stream?q=' . $img;
                error_log('cover '.$size.' '.$url);
            } else { // dev may have a copy : shortcut 
                $x = strlen($img)>8 ? strpos($img,'/',8) : 0;
                if (file_exists($_SERVER['DOCUMENT_ROOT'].substr($img,$x))) {
                    $url = substr($img, $x);
                } else {
                    //error_log('*'.$_SERVER['DOCUMENT_ROOT'].substr($url,$x));
                }
            }
        }

        if (empty($url)) {
            return parent::getThumbnail($size);
        } else {
            return $url;
        }
    }

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
            $desc = $this->translate('Online Access');
            if (substr($url, -4) == '.pdf') {
                $desc = $this->translate('PDF Full Text');
            } else if (substr($url, 0, 7) == '/ep/000') {
                $desc = $this->translate('Get full text');
            } else if (substr($url, -4) == '.zip') {
                $desc = $this->translate('View Record');
            } else if (substr($url, -4) == '.xml') {
                $url = 'https://dfg-viewer.de/show/?tx_dlf[id]=' . urlencode($url);
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
            } else if (substr($url,0,1) == '[') { // Markdown style
                $x = strpos($url,'](');
                $desc = $this->translate(substr($url, 1, $x-1));
                $url = substr($url, $x+2, strlen($url)-$x-3);
            } else if (substr($url, -9) == '/retrieve') { // DSpace
                $desc = $this->translate('Get full text');
            } else if (strpos($url, '/view/')>0) {
                $desc = $this->translate('Online Access');
            } else if (strpos($url, 'download')>0) {
                $desc = $this->translate('PDF Full Text');
            } else if (strpos($url, '/ep/')>0) {
                $desc = $this->translate('Online Access');
            } else if (strpos($url, 'https://doi.org')===0) {
                $desc = substr($url, 8);
            } else if ($this->isToplevel()) {
                $desc = $url;
            }

            if (isset($this->fields['rights_str'])) {
                return ['route'  => 'record-restricted', 'desc' => $desc,
                    'routeParams' => ['id' => $this->getUniqueId(),'q' => $url],                ];
            } else if (substr($url,0,7) == 'file://') {
                return ['route'  => 'record-view', 'desc' => $desc,
                    'routeParams' => ['id' => $this->getUniqueId(),'q' => $url],
                ];
            } else {
                $x = strlen($url)>8 ? strpos($url,'/',8) : 0;
                if (file_exists($_SERVER['DOCUMENT_ROOT'].substr($url,$x))) {
                    $url = substr($url, $x); // dev may have a copy
                    // error_log('url ' . substr($url,$x));
                }
                return [ 'url'  => $url, 'desc' => $desc];
            }
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

    public function getRealTimeHoldings()
    {
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
            $notes['Dbib::Date published'] = [$issued];
        }

        if (isset($this->fields['external_str_mv'])) {
            foreach($this->fields['external_str_mv'] as $field) {
                $link = $this->linkify($field);
                if (count($link)==2) {
                    $notes[$this->translate($link[0])] = [$link[1]];
                }
            }
        }

        foreach($this->getDownloads() as $key => $val) {
            $notes[$key] = [$val];
        }

        $txt = $this->getPermission();
        if (empty($txt)) {
            // Skip
        } else {
            $notes[$this->translate('License')] = [$txt];
        }

        if (isset($this->fields['uri_str'])) { // about
            $notes['Access URL'][] = $this->fields['uri_str'];
        }

        if (isset($this->fields['doi_str_mv'])) { 
            $doi = current($this->fields['doi_str_mv']);
            $notes['Access URL'][] = $doi;
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

    /** MBL: JSON export */
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
        if (empty($rdf) || substr($rdf,1,3)!=='rdf') {
            error_log('Zero RDF data');
            // make sure nothing bad happens
            $rdf = '<rdf:RDF '
                 . 'xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#">'
                 . '</rdf:RDF>';
        }
        $rdf = str_replace('&', '&amp;', $rdf);
        return $rdf;
    }

    private function getSolrRDF() {
        $rdf = null;
        if (isset($this->fields['fullrecord'])) {
            $rdf = $this->fields['fullrecord'];
        }
        return $rdf;
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

    /* called from record landing page - should be fast. */
    public function hasCitations()
    {
        if (isset($this->fields['cites_str'])) {
            return ['a', 'b'];
        } 
        return [];
    }

    /** citation extension : parse and render as link */
    public function getCitations()
    {
        $result = [];
        $rdf = $this->getRDFXML();
        if (empty($rdf)) {
             // 
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
                return $this->translate('Copyright') . ' ' . $lic;
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
                $rdf = $this->getRDFXML();
                if (empty($rdf)) {
                    error_log('Zero RDF, total disaster');
                } else {
                    $dom = new DOMDocument();
                    $dom->loadXML($rdf);
                    $result = $this->transform($dom, $xslt);
                }
            }
        }
        return $result;
    }

    /** http://datacite.org/schema/kernel-4 export */
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

    /* http://www.openarchives.org/ore/1.0/rdfxml */
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
        return $result;
    }

    /** return transformed string */
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
            error_log('SolrDCTerms: Problem transforming '.$this->getEdition());
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
        if (isset($this->fields['uuid_str_mv'])) {
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
