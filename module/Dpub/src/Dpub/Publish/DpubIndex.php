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
 * @category Minipub
 * @package  Publish
 * @license  http://opensource.org/licenses/gpl-2.0.php
 *
 */

namespace Dpub\Publish;
use DOMDocument;
use XSLTProcessor;

use Laminas\Http\Client;

class DpubIndex {

    var $db;
    var $domain;
    var $about;    // sql data
    var $rdfxslt;  // rdf transformer
    var $solrxslt; // rdf to solr transformer
    var $solrcore; // solr core

    public function __construct($db, $params) {
        $this->db = $db;
        $this->domain = $params['domain'];
        $this->solrcore = $params['solrcore'];
        $this->base = $params['base'];
        $this->about = 'module/Dpub/sql/opus-about.sql';
        $this->rdfxslt = 'module/Dpub/xslt/opus2rdf.xslt';
        $this->solrxslt = 'module/Dpub/xslt/seaview.xslt';
    }

    /** read xml, lift to rdf and transform to solr */
    public function index($oid) {
        // error_log('DpubIndex::index '.$oid . ' ' .$this->solrcore);
        $doc = $this->getDpubDocument($oid);
        // $doc->formatOutput = true;
        // $doc->save('data/opus-'.$oid.'.xml');
        $rdf = $this->transformToDoc($doc, $this->rdfxslt);
        $data = $this->transformToXml($rdf, $this->solrxslt);
        $this->rest($data);
        $this->rest('<commit/>');
    }

    public function delete($oid) {
        $oid = str_replace(':','\:',$oid);
        $query = '<delete><query>id:'.$oid.'</query></delete>';
        error_log('solr delete [' . $query . ']');
        $this->rest($query);
        $this->rest('<commit/>');
    }

    /** get xml document formatted like mysql --xml
      * rewrite to support local streaming if uid is like that
      * return DOMDocument
      */
    private function getDpubDocument($oid) {
        $urn = false; // set true if record has urn
        $queries = file_get_contents($this->about);
        $queries = str_replace('<oid>', $oid, $queries);
        $query = explode(';', $queries);

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
                    if (empty($val)) continue;
                    // if ($key=='urn') {
                    //     $urn = true;
                    // } else if ($key=='url' && $urn==false) {
                    //     $val='file://'.$this->base;
                    // }
                    // str_ends_with($this->base,substr($val,0,strpos($val,'/')
                    if ($key=='uid') {
                        $sub = substr($val,0,strpos($val,'/'));
                        // $sub = substr($this->base,strrpos($this->base,'/'));
                        // error_log('sub ['.$sub.']');
                        if (str_ends_with($this->base, $sub)) {
                            $url = 'file://'.$this->base;
                            $url = substr($url,0,strpos($url,$sub)-1);
                            error_log('url ['.$url.'] [val ['.$val.']');
                        } else {
                            error_log('No match ['.$url.'] [val ['.$val.']');
                        }
                    } else if ($key=='url' && !empty($url)) {
                        $val=$url;
                    }
                    $field = $row->appendChild($doc->createElement('field')); 
                    $field->setAttribute('name', $key);
                    $field = $field->appendChild($doc->createTextNode($val)); 
                }
            }
        }
        return $doc;
    }

    private function move($data) {
        // $target = $this->path . '/' . $uid;
        // if (is_writable(dirname($target))) {
        //     $this->log('publication target ['. $target .']');
        // } else if (mkdir($target, '0755')) {
        //     $this->log('Created ['. $target .']');
        // } else {
        //     $this->log('Not writable ['. $target .']');
        // }
        return false;
    }

    private function rest($data) {
        $client = new Client($this->solrcore.'/update');
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
            error_log('Failed rest ' . $time . ' ' . $this->solrcore);
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

}

