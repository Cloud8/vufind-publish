<?php
/**
 * Model for Repository records in Solr.
 *
 * PHP version 7
 *
 * Copyright (C) Abstract Technologies 2014 - 2019.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php
 * @link     http://vufind.org/wiki/other_than_marc Wiki
 */

namespace Dpub\RecordDriver;
use VuFind\RecordDriver\SolrDefault as SolrDefault;
use VuFind\Connection\Manager as ConnectionManager;
use VuFindHttp\HttpService as Service;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use DOMDocument, XSLTProcessor;
use SimpleXMLElement,VuFind\SimpleXML;


/**
 * @category VuFind
 * @package RecordDrivers
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public 
 * @title Driver for Repository records in Solr with private extensions.
 */
class SolrView extends SolrDpub 
{

    private $db; 
    private $edit;

    /*
    public function __construct($mainConfig = null, $recordConfig = null,
        $searchSettings = null, $adapterFactory = null) {
        parent::__construct($mainConfig, $recordConfig, $searchSettings, $adapterFactory);
        $this->db = $adapterFactory->getAdapterFromConnectionString($mainConfig['Minipub']['database']);
        $this->edit = false;
        $this->count = 0;
    }
    */

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
        if ($size == 'large') {
            $url = $_SERVER['CONTEXT_PREFIX']
                  .'/Record/'.$this->getUniqueId().'/View';
            return $url;
        } else if (empty($this->fields['thumbnail'])) {
            //
        } else { 
            // find content streaming path
            $img = trim($this->fields['thumbnail']);
            if (ctype_upper(substr($img,0,1)) || substr($img,0,7)=='file://') {
                $url = $_SERVER['CONTEXT_PREFIX'] . '/Dpub/View?q='.$img;
            } else { // dev should have a copy : shortcut link
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

    // GH201812 : private extension
    private function mapUrl($urls) {
        $result = [];
        foreach ($urls as $key => $map) {
            // error_log('GH2020-12 key '. $key);
            // var_dump($map);
            $url = null;
            if (isset($map['url'])) {
                $url = $map['url'];
            } else if (isset($map['routeParams']['q'])) {
                $url = $map['routeParams']['q'];
            } 
            
            if (substr($url,0,7) == 'file://') {
                if (substr($url, -4) == '.mp4') { 
                    $result[] = [ 'route' => 'record-view',
                        'routeParams' => [ 
                        'id' => $this->getUniqueId(), 'url' => $url],
                        'desc' => 'Video'];
                } else if (substr($url, -5) == '.epub') { 
                    $result[] = [ 'route' => 'record-view',
                        'routeParams' => [ 
                        'id' => $this->getUniqueId(), 'url' => $url],
                        'desc' => 'Epub'];
                } else if (substr($url, -4) == '.txt') { // markdown 
                    $result[] = [ 'route' => 'view-text',
                        'routeParams' => [ 'id' => $this->getUniqueId(),
                        'q' => $url ],
                        'queryString' => '?id='.$this->getUniqueId(),
                        'desc' => basename($url)];
                } else { // file view
                    $result[] = [ 'route' => 'dpub-view', 
                        'routeParams' => [ 'id' => $this->getUniqueId(),
                        'url' => $url ],
                        'queryString' => '?q='.$url,
                        'desc' => basename($url)];
                }
            } else if ($_SERVER["SERVER_NAME"]==='localhost'
                    || $_SERVER["SERVER_NAME"]==='titanic') {
                // dev should have a copy 
                if (substr($url,-4)=='.xml') {
                    $result[] = $map;
                } else if (strpos($url, 'download')>0) {
                    $result[] = $map;
                } else if (strpos($url, 'view')>0) {
                    $result[] = $map;
                } else if (strpos($url, 'ub')>0) {
                    // error_log('dev url shortener ' . $url);
                    $x = strlen($url)>7 ? strpos($url,'/',8) : 0;
                    $url = substr($url, $x);
                    // error_log($_SERVER['DOCUMENT_ROOT'].substr($url,$x));
                    if (isset($map['routeParams']['q'])) {
                        $result[] = $map;
                        // var_dump($result[0]['routeParams']['q']);
                        $result[0]['routeParams']['q'] = $url;
                    } else {
                        $result[] = [ 'url'  => $url, 'desc' => $map['desc'] ];
                    }
                } else {
                    $result[] = $map;
                }
            } else {
                $result[] = $map;
            }
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
    public function getURLs() {
        $result = parent::getURLs();
        $result = $this->mapUrl($result);
        return $result;
    }

    /** GH2015-11-18 */ //$service->setHeaders('Accept: application/rdf+xml');
    /*
    private function getSPARQL($uri, $level=1) {
        $query = "construct { <subject> ?b ?c . ?c ?d ?e } where {"
                 . " <subject> ?b ?c optional { ?c ?d ?e } }";
        if ($level==2) {
            $query = "construct { <subject> ?b ?c . ?c ?d ?e . ?e ?f ?g } "
                   . " where { <subject> ?b ?c optional { ?c ?d ?e . "
                   . " optional { ?e ?f ?g } } }";
        }
        $query = str_replace("subject", $uri, $query);
        $sparql = 'http://localhost:8890/sparql?default-graph-uri=';
        $sparql .= '&format='.urlencode('application/rdf+xml');
        //$sparql .= '&format='.urlencode('application/RDF+XML-ABBREV');
        $sparql .= '&query='.urlencode($query);
        $service = new Service();
        $result = $service->get($sparql);
        return $result->getBody();
    }
    */

    /** unused : dynamic link to citations from Google Scholar */
    /*
    private function getScholarCitations()
    {
        $base = "http://scholar.google.com/scholar";
        $query = http_build_query(array('as_q' => '', 
		                                'as_epq' => $this->getTitle(),
										'as_oq' => '',
										'as_eq' => '',
										'as_occt' => 'title',
										'as_sauthors' => '',
										'as_publication' => '',
										'as_ylo' => '',
										'as_yhi' => '',
										'btnG' => '',
										'hl' => 'de',
										'as_sdt' => ''));

        $html = file_get_contents($base.'?'.$query);
		$x = strpos($html, 'scholar?cites');
		if ($x===FALSE) {
			return array('note' => 'No citations were found for this record.');
		}
		$y = strpos($html, '&', $x + 12);
		$cites = substr($html, $x+14 , $y - $x - 14);

        $base = "http://scholar.google.com/scholar";
        $query = http_build_query(array('cites' => $cites, 
                                        'as_sdt' => '2005',
                                        'sciodt' => '0,5',
                                        'hl' => 'de'));
		$cites = $base.'?'.$query;

        $x = strpos($html, 'Zitiert von:');
		$y = strpos($html, '<', $x + 12);
        $count = substr($html,$x + 12, $y - $x -12);
        return array($count => $cites);
    }
    */

    /***
    private function xml2js($xmlnode) {
        $root = (func_num_args() > 1 ? false : true);
        $jsnode = array();

        if (!$root) {
            if (count($xmlnode->attributes()) > 0){
                $jsnode["$"] = array();
                foreach($xmlnode->attributes() as $key => $value)
                    $jsnode["$"][$key] = (string)$value;
            }

            $textcontent = trim((string)$xmlnode);
            if (count($textcontent) > 0)
                $jsnode["_"] = $textcontent;

            foreach ($xmlnode->children() as $childxmlnode) {
                $childname = $childxmlnode->getName();
                if (!array_key_exists($childname, $jsnode))
                    $jsnode[$childname] = array();
                array_push($jsnode[$childname], xml2js($childxmlnode, true));
            }
            return $jsnode;
        } else {
            $nodename = $xmlnode->getName();
            $jsnode[$nodename] = array();
            array_push($jsnode[$nodename], xml2js($xmlnode, true));
            return json_encode($jsnode);
        }
    }   
    ****/

}
