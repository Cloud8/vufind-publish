<?php
/**
 * Solr Index
 *
 * PHP Version 8
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
 * @category Dbib
 * @package  Publish
 * @license  http://opensource.org/licenses/gpl-2.0.php
 *
 */

namespace Dbib\Publish;
use DOMDocument;
use XSLTProcessor;

use Laminas\Http\Client;

class DbibIndex {

    var $dev = true;

    var $db;
    var $solr;     // solr core
    var $about;    // sql data
    var $rdfxslt;  // rdf transformer
    var $solrxslt; // rdf to solr transformer

    public function __construct($db, $params = []) {
        $this->db = $db;
        $this->solr = $params['solr'];
        $this->about = $params['publish']['about'];      // SQL query
        $this->rdfxslt = $params['publish']['dcterms'];  // XMl to DCTerms RDF
        $this->solrxslt = $params['publish']['rdfsolr']; // RDF to solr XML
    }

    /** read xml, lift to rdf and transform to solr */
    public function index($oid, $files = []) {
        $doc = $this->getDbibDocument($oid, $files);
        if ($this->dev) {
            $doc->formatOutput = true;
            $out = LOCAL_CACHE_DIR.'/dbib-'.$oid.'.xml';
            $doc->save($out);
            $this->log('Cached to ' . $out);
        }
        $rdf = $this->transformToDoc($doc, $this->rdfxslt);
        $data = $this->transformToXml($rdf, $this->solrxslt);
        $this->rest($data);
        $this->rest('<commit/>');
    }

    public function delete($oid) {
        $oid = substr(str_replace(':', '?', $oid),0,-1);
        $query = '<delete><query>id:'.$oid.'</query></delete>';
        $this->log('solr delete [' . $query . ']');
        $this->rest($query);
        $this->rest('<commit/>');
    }

    /** get xml document formatted like mysql --xml
      * rewrite to support local streaming if uid is like that
      * return DOMDocument
      */
    private function getDbibDocument($oid, $files = []) {
        $url = null; 
        $queries = file_get_contents($this->about);
        $queries = str_replace('<oid>', $oid, $queries);
        $query = explode(';', $queries);

        if (empty($files)) {
            $this->log('Zero files');
        } else {
            // file_put_contents('data/dbib-'.$oid.'.json',json_encode($files));
            $path = $files[0]['path'];
            $len = strlen($path) - strlen($files[0]['name']) -1;
            $url = 'file://'.substr($path, 0, $len);
            $this->log('File URL ['.$url.']');
        }

        $doc = new DOMDocument('1.0', 'utf-8');
        $root = $doc->createElement('document');
        $doc->appendChild($root);
        foreach($query as $sql) {
            $x = strpos($sql, 'from ') + 5;
            $y = strpos($sql, ' ', $x);
            $name = trim(substr($sql, $x, $y-$x+1)); 
            $result = $this->db->query($sql)->execute();
            if ($result->count()==0) continue;
            $table = $root->appendChild($doc->createElement('resultset'));
            $table->setAttribute('table', $name);
            $durl = null; // domain URL
            for ($i=0; $i<$result->count(); $result->next(), $i++) {
                $row = $table->appendChild($doc->createElement('row'));
                $data =  $result->current();
                foreach($data as $key => $val) {
                    if ($key=='url' && !empty($url)) {
                        $val=$url;
                    } else if (empty($val)) {
                        continue;
                    }
                    $field = $row->appendChild($doc->createElement('field')); 
                    $field->setAttribute('name', $key);
                    $field = $field->appendChild($doc->createTextNode($val)); 
                }
            }
        }
        return $doc;
    }

    private function rest($data) {
        $client = new Client($this->solr.'/update');
        $client->setMethod('POST');
        $client->setRawBody($data);
        $client->setEncType('text/xml');
        // $client->setOptions(['timeout' => 0.8]); // need to wait ??

        $time = -microtime(true);
        try {
            $response = $client->send();
        } catch (\Exception $ex) {
            // error_log(' Rest ex ' . $ex->getMessage());
        } finally {
            $time += microtime(true);
        }

        if (empty($response)) {
            return false;
        } else  if ($response->isSuccess()) {
            // error_log('OK rest ' . $time);
            return $response->getContent();
        } else {
            error_log('Failed rest ' . $time . ' ' . $this->solr);
            return false;
        }
    }

    /** return DOMDocument|false */
    private function transformToDoc($dom, $xslFile) {
        $xsl = new XSLTProcessor();

        // Load up the style sheet
        $style = new DOMDocument;
        if (!$style->load($xslFile)) {
            throw new \Exception("Problem loading XSL file: {$xslFile}.");
        }
        $xsl->importStyleSheet($style);
        $doc = $xsl->transformToDoc($dom);
        return $doc;
    }

    /** returns transform string|DOMDocument|false */
    private function transformToXml($dom, $xslFile) {
        $xsl = new XSLTProcessor();

        // Load up the style sheet
        $style = new DOMDocument;
        if (!$style->load($xslFile)) {
            throw new \Exception("Problem loading XSL file: {$xslFile}.");
        }
        $xsl->importStyleSheet($style);
        $xml = $xsl->transformToXML($dom);

        return $xml;
    }

    private function log($msg) {
        if ($this->dev) {
            error_log('DbibIndex: '.$msg);
        }
    }
}

