<?php
/**
 * Dbib Administration
 * 
 * PHP version 7
 *
 * Copyright (C) Abstract Power 2022.
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
 * @category Dbib VF
 * @package  Publish
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 *
 */

/**
 * @title Dbib Admin and Metadata processing 
 * @date 2017-03-16 2020-05-22 2022-02-27
 */
namespace Dbib\Publish;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Http\Client;

class DbibAdmin extends Form 
    implements \VuFind\I18n\Translator\TranslatorAwareInterface
{

    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    var $db;
    var $data;
    var $head;
    var $table;
    var $page;
    var $title;
    var $autobib; // rest url
    var $domain;
    var $message;

    public function __construct($name = null, $params = [])
    {
        parent::__construct($name, $params);
        $this->db = $params['db'];
        $this->domain = $params['publish']['domain'];
        $this->autobib = $params['publish']['autobib'] ?? null;
    }

    // see themes/seaview/templates/dbib/admin.phtml
    public function create($post = []) {
        $this->page = (empty($post['page'])) ? 1 : $post['page'];
        $action = (empty($post['opus:action'])) ? 0 : $post['opus:action'];
        // error_log("create page " . $this->page . ' ' .$action);

        if (isset($post['opus:ppn'])) {
            error_log("create ppn " . $post['opus:ppn']);
            $this->page = 1;
            DbibAdmin::rest($this->autobib, 'ppn', $post['opus:ppn']);
        }

        if ($this->page=='1') { // submissions : temp table records
            // $this->title = 'Dbib::Submissions';
            $this->table = 'temp:';
            $query = 'SELECT '
                   . 'GROUP_CONCAT(a.creator_name SEPARATOR ", ") creator_name,'
                   . 't.status, t.source_opus, t.title, '
                   . 'r.dokumentart, t.bem_intern '
                   . 'from temp t left join resource_type r on r.typeid=t.type'
			       . ' left join temp_autor a on t.source_opus = a.source_opus'
			       . ' where t.status!="deleted"'
			       // . ' where t.status="neu"'
                   //. ' and (a.reihenfolge=1 or a.reihenfolge is null)'
                   . ' and (t.bem_intern is null or '
                   . ' t.bem_intern not like "%Sperrfrist%"'
                   // . ' or str_to_date(right(rtrim(t.bem_intern),10),'
                   // . '"%d.%m.%Y") < now()'
                   . ')'
                   . ' GROUP BY t.source_opus, r.dokumentart'
                   . ' order by t.source_opus';
        } else if ($this->page=='2') { // locked records
            // $this->title = 'Dbib::Locked';
            $this->table = 'temp:';
            $query = 'SELECT status, t.source_opus, title, creator_name, '
                   . 'dokumentart, bem_intern '
                   . 'from resource_type r, temp t'
			       . ' left join temp_autor a on t.source_opus = a.source_opus'
			       . ' where t.type = r.typeid '
                   . ' and (a.reihenfolge=1 or a.reihenfolge is null)'
                   // . ' and (t.status="neu" or t.status="auto")'
			       . ' and t.status!="deleted"'
                   . ' and (t.bem_intern like "%Sperrfrist%")'
                   //. ' order by t.source_opus';
                   //. ' order by right(rtrim(t.bem_intern),4)'
                   //. ', right(rtrim(t.bem_intern),7)'
                   //. ', right(rtrim(t.bem_intern),10)';
                   . ' order by str_to_date(right(rtrim(t.bem_intern),10)'
                   // . ' order by str_to_date(trim(substr(bem_intern,'
                   // . ' locate("Sperrfrist", bem_intern)+12, 10))'
                   . ',"%d.%m.%Y")';
        } else if ($this->page=='3' || $this->page=='3b') { 
            $this->title = 'Dbib::No PID';
            $this->table = 'opus:';
            $query = 'SELECT concat(from_unixtime(o.date_creation, "%Y/%m")'
                   . '," ",COALESCE(status, "")) as status, '
                   . 'o.source_opus, o.title, a.creator_name, '
                   . 'r.dokumentart, o.bem_intern, o.source_swb '
                   . 'from resource_type r, opus o'
                   . ' left join opus_publications p on (o.source_opus=p.oid)'
			       . ' left join opus_autor a on (o.source_opus=a.source_opus)'
			       . ' where o.type = r.typeid '
                   . ' and (a.reihenfolge=1 or a.reihenfolge is null)'
                   . ' and (p.urn is null)'
                   . ($this->page=='3' ? ' and (p.uid is null)' : '')
                   // . ($this->page=='3' ? ' and (p.urn is null)' : '')
                   // . ($this->page=='3' ? ' and (p.doi is null)' : '')
                   . '';
            $this->page = '3b';
        } else if ($this->page=='4') { // deleted records 
            $this->title = 'Dbib::Deleted';
            $this->table = 'temp:';
            $query = 'SELECT status, t.source_opus, title, creator_name, '
                   . 'from_unixtime(date_modified) dokumentart, bem_intern '
                   . 'from resource_type r, temp t'
			       . ' left join temp_autor a on t.source_opus = a.source_opus'
			       . ' where t.type = r.typeid '
                   . ' and (a.reihenfolge=1 or a.reihenfolge is null)'
			       . ' and t.status="deleted" and'
                   . ' from_unixtime(date_modified) > now()-INTERVAL 3 MONTH'
                   . ' order by t.date_modified desc';
        } else if ($this->page=='5') { // Brief View
            $this->data = [
                ['id' => '1', 'url' => '?page=3', 
                 'label' => 'Dbib::Records', 'text' => 'Dbib::No PID'], 
                ['id' => '2', 'label' => 'PPN', 'text' => 'Dbib::Create'],
                ['id' => '3', 'url' => '?page=4', 
                 'label' => 'Dbib::Records', 'text' => 'Dbib::Deleted'], 
                ['id' => '4', 'url' => '?page=6', 'label' => 'Series', 
                 'text' => 'Edit'], 
                ['id' => '5', 'url' => '?page=7', 'label' => 'Series', 
                 'text' => 'Dbib::Create'], 
                ['id' => '6', 'url' => '?page=8', 'label' => 'Collection', 
                  'text' => 'Edit'],
                ['id' => '7', 'url' => '?page=9', 'label' => 'Collection', 
                  'text' => 'Dbib::Create'],
            ];
            $this->addCatalogElements();
        } else if ($this->page=='6') { // Serials 
            $this->title = 'Series';
            $query = 'SELECT sr_id id, url, name, right(rtrim(urn),21) urn'
                   . ' from schriftenreihen' //  where urn is not null'
                   . ' order by sr_id desc';
            $this->addSeriesElements();
        } else if ($this->page=='7') { // Serial create
            // error_log("DbibAdmin series create ".$this->page." # ".$sid);
            $this->title = 'Serial';
            $sql = 'SELECT COALESCE(max(sr_id)+1,1) id from schriftenreihen';
            $res = $this->db->query($sql)->execute()->current();
            $sid = empty($res) ? 0 : $res['id'];
            $sql = 'SELECT url from opus_domain where id='.$this->domain;
            $res = $this->db->query($sql)->execute()->current();
            $url = empty($res) ? 'Access URL' : $res['url'];
            $this->addSeriesElements();
            $this->get('opus:sid')->setValue($sid);
            $this->get('opus:series')->setValue($post['opus:series'] ?? '');
            $this->get('opus:url')->setValue($url);
            $this->get('opus:contributor')->setValue($post['opus:contributor'] ?? null);
            $this->get('opus:type')->setValue($post['opus:type'] ?? null);
        } else if ($this->page=='8') { 
            $this->title = 'Collection';
            $query = 'SELECT coll_id id, url, coll_name name,'
                   . ' right(rtrim(urn),21) urn'
                   . ' from collections where urn is not null'
                   . ' order by coll_id desc';
            $this->addCollectionElements();
        } else if ($this->page=='9') { 
            // error_log("DbibAdmin collection create ".$this->page);
            $this->title = 'Collection';
            $sql = 'SELECT COALESCE(max(coll_id)+1,1) id from collections';
            $res = $this->db->query($sql)->execute()->current();
            $cid = empty($res) ? 0 : $res['id'];
            $sql = 'SELECT url from opus_domain where id='.$this->domain;
            $res = $this->db->query($sql)->execute()->current();
            $url = empty($res) ? 'Access URL' : $res['url'];
            $this->addCollectionElements();
            $this->get('opus:cid')->setValue($cid);
            $this->get('opus:coll')->setValue($post['opus:coll'] ?? '');
            $this->get('opus:url')->setValue($url);
        }

        if (empty($query)) {
            // error_log("Zero page " . $this->page);
        } else if (empty($this->data)) {
            // Pdo only:
            // $stmt = $this->db->query($query);
            // $this->data = $stmt->execute()->getResource()->fetchAll();
            $this->data = [];
            $result = $this->db->query($query)->execute();
            for ($i=0; $i<$result->count(); $result->next(), $i++) {
                $this->data[] = $result->current();
                if (!empty($this->data[$i]['bem_intern'])) {
                    $text = $this->data[$i]['bem_intern'];
                    // if ($i==1) error_log($text);
                    if (strpos($text, 'Sperrfrist:')!==FALSE) {
                        $today = date('Y-m-d');
                        // $date = substr($text, -10);
                        $date = substr($text,-4) . '-' . substr($text,-7, 2)
                            . '-' . substr($text,-10, 2);
                        if ($today > $date) {
                            // if ($i==1) error_log($date);
                            $this->data[$i]['bem_intern'] = '<font color="red">'
                                . $text . '</font>';
                        }
                    }
                }
            }
            // error_log('GH2020-03 '.$result->count().' '.count($this->data));
        }

        return $this;
    }

    public function process($post = []) {
        // $this->setData($post);
        $this->page = (empty($post['page'])) ? 1 : $post['page'];
        $sid = (empty($post['opus:sid'])) ? 0 : $post['opus:sid'];
        $cid = (empty($post['opus:cid'])) ? 0 : $post['opus:cid'];
        $url = (empty($post['opus:url'])) ? 0 : $post['opus:url'];
        $contrib = (empty($post['opus:contributor'])) ? null : $post['opus:contributor'];
        $type = (empty($post['opus:type'])) ? 40 : $post['opus:type'];
        $series = (empty($post['opus:series'])) ? null : $post['opus:series'];
        $collection = (empty($post['opus:coll'])) ? null 
            : $post['opus:coll'];
        $action = (empty($post['opus:action'])) ? 'zero' : $post['opus:action'];

        if ($this->page==6 && empty($sid) && empty($series)) {
            // zero action
            error_log('Zero proc '.$action. ' '.$this->page.' # ser '.$sid);
        } else if ($this->page==6 && empty($series)) {
            // edit action from main page
            error_log('proc one '.$action. ' '.$this->page.' # ser '.$sid);
            //error_log("process page ".$this->page.' # '.$sid.'['.$series.']');
            $sql = 'SELECT name,url,urn,contributor,type from schriftenreihen'
                 . ' where sr_id='.$sid;
            $res = $this->db->query($sql)->execute()->current();
            $name = empty($res['name']) ? null : $res['name'];
            $url = empty($res['url']) ? null : $res['url'];
            $urn = empty($res['urn']) ? null : $res['urn'];
            $contrib = empty($res['contributor']) ? null : $res['contributor'];
            $type = empty($res['type']) ? null : $res['type'];
            $this->addSeriesElements();
            $this->get('opus:series')->setValue($name);
            $this->get('opus:url')->setValue($url);
            $this->get('opus:contributor')->setValue($contrib);
            $this->get('opus:type')->setValue($type);
            $this->data = [['id' => $sid, 'name' => $name, 'url' => $url, 
                'urn' => $urn, 'contributor' => $contrib, 'type' => $type]];
        } else if ($this->page==6) {
            // edit action from edit page
            error_log('proc two '.$action.' '.$this->page.' '.$sid.' '.$series);
            if ($action=='delete') {
                //$upd='UPDATE schriftenreihen set urn=null where sr_id='.$sid;
                $upd = 'DELETE from schriftenreihen where sr_id='.$sid;
                $res = $this->db->createStatement($upd)->execute();
            } else if ($action=='index') {
                DbibAdmin::rest($this->autobib, $action, 's'.$sid);
            } else {
                $upd = 'UPDATE schriftenreihen SET name="'.$series.'"'
                    . ' , contributor="'.$contrib.'", type='.$type
                    . ' where sr_id='.$sid;
                // error_log('XXX ['.$upd.'] XXX');
                $res = $this->db->createStatement($upd)->execute();
            }
        } else if ($this->page==7 && empty($series)) {
            error_log('Zero serial page '.$this->page);
            $this->message = 'empty_search_disallowed';
        } else if ($this->page==9 && empty($collection)) {
            error_log('Zero collection page '.$this->page);
            $this->message = 'empty_search_disallowed';
        } else if ($this->page==7 && empty($sid)) {
            error_log('Unexpected serial action '.$this->page.' # '.$sid);
        } else if ($this->page==9 && empty($cid)) {
            error_log('Unexpected collection action'.$this->page.' # '.$cid);
        } else if ($this->page==7 || $this->page==9) {
            error_log('proc create serial / collection '.$this->page);
            if (empty($url)) {
                //
            } else if (strpos($url, '/', strpos($url,'//')+2) === false) {
                error_log('bad url ['.$url.'] ' . strlen($url).' '.$series);
                $this->message = 'Dbib::invalid_url';
            } else if ($action=='create') {
                // $urn = empty($url) ? null : 'urn neu'; 
                $path = substr($url, strpos($url, '/', 8));
                $sql = 'SELECT path from opus_domain where id='.$this->domain;
                $res = $this->db->query($sql)->execute()->current();
                $path = empty($res['path']) ? path : $res['path'].$path;
                $raw = substr($url, strpos($url,'//')+2);
                $urn = DbibAdmin::rest($this->autobib, 'urn', $raw);

                if (empty($urn)) {
                    //
                } else if (substr($urn, 0, 4) === 'urn:') {
                    //
                } else {
                    $urn = null; 
                }

                $upd = 'SELECT 1';
                if ($this->page==7) {
                    $urn = empty($urn) ? 'null' : trim($urn);
                    $upd = 'INSERT into schriftenreihen'
                        . ' (sr_id, name, url, urn, type) values'
                        . ' ('.$sid.',"'.$series.'","'.$url.'","'.$urn.'",40)';
                    error_log('serial created ['.$upd.']');
                } else if ($this->page==9) {
                    $url = substr($url, strpos($url, '//')); // url general
                    $upd = 'INSERT into collections (coll_id, coll_name, url,'
                        . 'urn) values ('.$cid .', "'.$collection.'", "'.$url
                        . '", ' . (empty($urn) ? 'null' : '"'.$urn.'"') . ')';
                    error_log('collection created ['.$upd.']');
                }

                if (empty($urn)) {
                    $this->message = 'An error has occurred';
                } else if (is_dir($path)) {
                    $this->message = 'Dbib::Directory exists';
                } else {
                    $res = $this->db->createStatement($upd)->execute();
                    $post['page'] = 5; // page 6 or 8 have inclompete data
                    // create serial or collection folder
                    $oid = $this->page==7 ? 'cs'.$sid : 'cc'.$cid;
                    DbibAdmin::rest($this->autobib, $action, $oid);
                }
            }
        } else if ($this->page==8 && empty($collection)) {
            // edit action from main page
            error_log('coll one '.$action. ' '.$this->page.' # cid '.$cid);
            $sql = 'SELECT coll_name name, url, urn'
                 . ' from collections where coll_id='.$cid;
            $res = $this->db->query($sql)->execute()->current();
            $name = empty($res['name']) ? null : $res['name'];
            $url = empty($res['url']) ? null : $res['url'];
            $urn = empty($res['urn']) ? null : $res['urn'];
            $this->addCollectionElements();
            $this->get('opus:coll')->setValue($name);
            $this->get('opus:url')->setValue($url);
            $this->data = [['id' => $cid, 'name' => $name, 
                            'url' => $url, 'urn' => $urn]];
        } else if ($this->page==8) {
            // edit action from edit page
            error_log('coll two '.$action. ' '.$this->page.' # cid '.$cid);
            if ($action=='delete') {
                $upd = 'update collections set urn=null where coll_id='.$cid;
                $res = $this->db->createStatement($upd)->execute();
            } else if ($action=='index') {
                DbibAdmin::rest($this->autobib, $action, 'c'.$cid);
            } else {
                $upd = 'update collections set coll_name="'.$collection
                     . '" where coll_id='.$cid;
                $res = $this->db->createStatement($upd)->execute();
            }
        } else if ($this->page==9) {
            // handled above
        }
        return $post;
    }

    private function addCatalogElements() {
        $elem = new Element\Text('opus:ppn');
        $elem->setLabel("ppn");
        $elem->setAttribute('size', '25');
        $this->add($elem);
    }

    private function addSeriesElements() {
        $elem = new Element\Number('opus:sid');
        $this->add($elem);
        $elem = new Element\Text('opus:url');
        $elem->setAttribute('size', '44');
        $this->add($elem);
        $elem = new Element\Textarea('opus:series');
        $elem->setLabel("Dbib::Change");
        $elem->setAttribute('cols', '44');
        $elem->setAttribute('rows', '2');
        $this->add($elem);
        $elem = new Element\Text('opus:contributor');
        $elem->setAttribute('size', '44');
        $this->add($elem);
        $elem = new Element\Select('opus:type');
        $elem->setValueOptions( ['40' => 'Serial', '31' => 'Periodical',
            '18' => 'CollectedWorks', '4' => 'Book']);
        $this->add($elem);
    }

    private function addCollectionElements() {
        $elem = new Element\Number('opus:cid');
        $this->add($elem);
        $elem = new Element\Text('opus:url');
        $elem->setAttribute('size', '44');
        $this->add($elem);
        $elem = new Element\Textarea('opus:coll');
        $elem->setLabel("Dbib::Change");
        $this->add($elem);
        $elem->setAttribute('cols', '44');
        $elem->setAttribute('rows', '2');
        $this->add($elem);
    }

    public static function rest($rest, $cmd, $oid) {
        if (empty($oid) || empty($rest) || empty($cmd)) {
            error_log(" cannot rest " . $oid);
            return false;
        }

        if ($cmd==='ppn') {
            $rest = $rest . 'rest/opac/temp/' . $oid;
        } else if ($cmd==='index') {
            $rest = $rest . 'rest/opus/solr1/' . $oid;
        } else if ($cmd==='urn') {
            $rest = $rest . 'rest/urn/' . $oid;
        } else if ($cmd==='create') {
            $rest = $rest . 'rest/opus/opus/' . $oid;
        } else {
            return;
        }

        $client = new Client($rest);
        // $client->setOptions(['timeout' => 0.6]);

        $response = null;
        $time = -microtime(true);
        try {
            $response = $client->send();
        } catch (\Exception $ex) {
            // timeout exception is good for asynchronous rest
            error_log(' Rest ex ' . $ex->getMessage());
        } finally {
            $time += microtime(true);
            error_log('rest ' . $rest . ' ' . $time);
        }

        if ($response->isSuccess()) {
            return $response->getContent();
        } else {
            return false;
        }
    }

}
