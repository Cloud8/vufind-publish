<?php
/**
 * Dbib Publish
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
 * @title Description of Storage
 * @abstract Three state publishing: new / published / persistent
 *           File handling: temp files are bound to a domain, which
 *           is a pair of path and url from database domain table.
 *
 * @created 2016-11-16
 * @date 2022-04-22
 */
namespace Dbib\Publish;

class DataStorage {

    var $dev = true;
    var $db;
    var $domain;
    var $path;
    var $docbase;
    var $columns;
    var $temp;
    var $auto;
    var $urn_prefix;
    var $doi_prefix;

    public function __construct($db, $params = []) {
        $this->db = $db;
        $this->domain = $params['domain'];
        $this->urn_prefix = $params['urn_prefix'] ?? null;
        $this->doi_prefix = $params['doi_prefix'] ?? null;
        $this->columns = 'title, creator_corporate, subject_swd,'
              . 'description, publisher_university, contributors_name,'
              . 'contributors_corporate, date_year, date_creation,'
              . 'date_modified, type, source_title,' // 12
              . 'source_swb, language, verification,'
              . 'subject_uncontrolled_german, subject_uncontrolled_english,'
              . 'title_alt, description2, subject_type, date_valid,'
              . 'description_lang, description2_lang, sachgruppe_ddc,' 
              . 'bereich_id, lic, isbn, bem_intern, bem_extern, status'; // 30
        $sql = 'select concat(path, base) path, base from opus_domain where id='
             . $params['domain'];
        $row = $this->db->query($sql)->execute()->current();
        $this->path = empty($row['path']) ? sys_get_temp_dir() : $row['path'];
        if (empty($row['base'])) {
            error_log("No Storage for ".$this->path);
        } else {
            $this->docbase = $row['base'];
        }

        $this->urn_prefix = $params['urn_prefix'] ?? null;
        $this->doi_prefix = $params['doi_prefix'] ?? null;
        $this->temp = '/volltexte/incoming/';
        $this->auto = '/volltexte/auto/';
    }

    /** read data from opus or temp table */
    public function read($identifier) {
        $urn = substr($identifier,0,4) == 'urn:' ? $identifier : false;
        $oid = substr($identifier,0,5) == 'opus:' ? substr($identifier,5):false;
        $did = substr($identifier,0,5) == 'dbib:' ? substr($identifier,5):false;
        $tid = substr($identifier,0,5) == 'temp:' ? substr($identifier,5):false;
        $oid = empty($oid) ? $did : $oid;

	    $sql = 'SELECT distinct ' . $this->columns
              . ',from_unixtime(date_creation, "%Y-%m-%d") date_creation_esc'
              . ',o.source_opus' 
              . ',i.inst_nr, f.faculty, d.title_de' // 31
              . ',from_unixtime(d.date_accepted, "%Y-%m-%d") date_accepted'
              . ',d.advisor'; // 33
        if ($urn) {
            // $this->log('read ' . $urn);
            $sql .= ',sr_id, sequence_nr, p.urn, p.uid'
            . ' from opus_publications p, opus o'
            . ' left join opus_inst i on o.source_opus=i.source_opus'
            . ' left join opus_diss d on o.source_opus=d.source_opus'
            . ' left join institute f on i.inst_nr=f.nr'
            . ' left join opus_schriftenreihe s on o.source_opus=s.source_opus'
            . ' where o.source_opus=p.oid and'
            . ' p.urn like "'.$urn.'_"';
        } else if ($oid) {
            $sql .= ',sr_id, sequence_nr, p.uid, p.urn from opus o'
            . ' left join opus_inst i on o.source_opus=i.source_opus'
            . ' left join opus_diss d on o.source_opus=d.source_opus'
            . ' left join institute f on i.inst_nr=f.nr'
            . ' left join opus_schriftenreihe s on o.source_opus=s.source_opus'
            . ' left join opus_publications p on o.source_opus=p.oid'
            . ' where o.source_opus='.$oid;
        } else if ($tid) { 
            $sql .= ',sr_id, sequence_nr from temp o'
            . ' left join temp_inst i on o.source_opus=i.source_opus'
            . ' left join temp_diss d on o.source_opus=d.source_opus'
            . ' left join institute f on i.inst_nr=f.nr'
            . ' left join temp_schriftenreihe s on o.source_opus=s.source_opus'
            . ' where o.source_opus='.$tid;
        }
        $data = $urn || $oid || $tid ? $this->readData($sql) : [];

        if ($urn || $oid || $tid) {
            if (empty($data) && $tid) {
                $this->log('Zero data -- orphaned record adoption allowed');
                $data['opus:source_opus'] = $tid;
            }
        } else {
            return $data;
        }

        // Continue to read from database 
	    $sql = 'SELECT creator_name, reihenfolge, orcid, gnd from ';
        if ($urn && !empty($data['opus:source_opus'])) {
            $sql .= 'opus_autor where source_opus='.$data['opus:source_opus'];
        } else if ($oid) {
            $sql .= 'opus_autor where source_opus='.$oid;
        } else if ($tid) { 
            $sql .= 'temp_autor where source_opus='.$tid;
        } else {
            return $data;
        }

        $stmt = $this->db->createStatement($sql);
        $stmt->prepare();
        try {
            $result = $stmt->execute();
            $num = 0;
            foreach($result as $row) {
                $data['dcterms:creator'.$num] = $row['creator_name']; 
                if (!empty($row['orcid'])) {
                    $data['opus:authorid'.$num] = $row['orcid']; 
                } else if (!empty($row['gnd'])) {
                    $data['opus:authorid'.$num] = $row['gnd']; 
                }
                $num++;
            }
            if ($num==0 && !empty($data['opus:creator_corporate'])) {
                $num = 1;
            }
            $data['opus:number'] = $num; 
        } catch (\Exception $ex) {
            $this->log("DataStorage [" . $sql . "] " . $ex->getMessage());
            return $data;
        }

        $data = $this->readLinks($data, $urn, $oid, $tid);

        // collections
        $data['collections'] = $this->readCollections($urn, $oid, $tid);
        $key = array_key_last($data['collections']);
        $data['opus:collection'] = substr($key, strpos($key, '_')+1);

        if ($tid) {
            $data['opus:files'] = $this->readFiles($tid);
        } else if ($oid) { // not on temp
            $data['opus:files'] = $this->readFiles($oid);
        } else if ($urn) {
            $data['opus:files'] = $this->readFilesDB($data['opus:source_opus']);
        }
        return $data;
    }

    // GH2022-04-22
    private function readLinks($data, $urn, $oid, $tid) {
	    $sql = 'SELECT oid, tag, name, link from opus_links';
        if ($urn && !empty($data['opus:source_opus'])) {
            $sql .= ' where oid='.$data['opus:source_opus'];
        } else if ($oid) {
            $sql .= ' where oid='.$oid;
        } else if ($tid) { 
            $sql .= ' where oid='.$tid;
        } else {
            return $data;
        }
        // $sql .= ' order by tag limit 1'; // only one at a time

        $stmt = $this->db->createStatement($sql);
        $stmt->prepare();
        try {
            $result = $stmt->execute();
            $i=0;
            foreach($result as $row) {
                if (empty($row['link']) || empty($row['tag'])) {
                    //
                } else if ($row['tag'] == 'desc' || $row['tag'] == 'item') {
                    if ($row['name'] == 'Bibliographic Details') {
                        $data['opus:details'] = $row['link'];
                    } else if ($row['name'] == 'Research data') {
                        $data['opus:research'] = $row['link'];
                    }
                }
            }
        } catch (\Exception $ex) {
            $this->log("DataStorage [" . $sql . "] " . $ex->getMessage());
        }

        return $data;
    }

    /** return array of file urls */
    private function readFilesDB($oid) {
        $files = [];
        $sql = 'select concat("/",uid,"/",name) url'
             . ' from opus_files f, opus_publications p'
             . ' where f.oid=p.oid and f.oid='.$oid;
        $rows = $this->db->query($sql)->execute();
        foreach($rows as $row) {
            $files[] = ['url' => $row['url']];
        }
        return $files;
    }

    /** return array of file information */
    private function readFiles($oid) {
        $year = date('Y');
        $path = $this->path . '/' . $year . '/' . $oid;
        $url = $this->docbase . '/' . $year . '/' . $oid;
        $sub = substr($this->docbase, strrpos($this->docbase,'/')+1);
        $uid = $sub . '/' . $year . '/' . $oid;
        for ($i=4; $i>0 && !file_exists($path); $i--) {
			if (file_exists($this->path.$this->temp.'/'.$year.'/'.$oid)) {
                $path = $this->path . $this->temp . $year . '/' . $oid;
                $url = $this->docbase . $this->temp . $year . '/' . $oid;
                break;
			} else {
			    $year--;
                $path = $this->path . '/' . $year . '/' . $oid;
                $url = $this->docbase . '/' . $year . '/' . $oid;
                $uid = $sub . '/' . $year . '/' . $oid;
			}
		}

        // Check auto folder
        if (!file_exists($path) && file_exists($this->path.$this->auto)) {
            $year = date('Y');
            for ($i=1; $i>0 && !file_exists($path); $i--) {
                $path = $this->path . $this->auto . $year . '/' . $oid;
                $url = $this->docbase . $this->auto . $year . '/' . $oid;
                $year--;
            }
        }

        if (file_exists($path)) {
            // OK 
        } else {
            return [];
        }

		$sql = 'select extension from format where diss_format=1';
        $result = $this->db->query($sql)->execute();
        for ($i=0; $i<$result->count(); $result->next(), $i++) {
            $format[] = $result->current();
        }
		$result = [];
        $format[] = ['extension' => 'data','data'];

        foreach ($format as $extension) {
            // $ext = $extension[0];
            $ext = $extension['extension'];
            $dir = $path.'/'.$ext;
            if (!file_exists($dir)) {
                continue;
            }
            $files = scandir($dir);
            foreach($files as $file) {
                if (substr($file,0,1)=='.') {
                    continue;
                } else if (substr($file,-4)=='.old') {
                    continue;
                } else if (filesize($dir .'/'. $file)==0) {
                    continue;
                } else {
                    $fpath = $dir.'/'.$file;
                    $ftime = date ('Y-m-d', filectime($fpath));
                    $result[] = [ 'name' => $ext . '/' . $file,
                                  'time' => $ftime,
                                  'path' => $fpath,
                                  'uid' => $uid,
                                  'url' => $url . '/'. $ext .'/'. $file ];
                }
            }
        }
        return $result;
    }

    /** Rename a file and return updated file array */
    public function rename($old, $new, $files) {
		$sql = "select extension from format where diss_format=1";
        //$res = $this->db->query($sql)->execute()->getResource()->fetchAll();
        //foreach($res as $row) {
		//    $format[] = $row['extension'];
		//}
        $result = $this->db->query($sql)->execute();
        for ($i=0; $i<$result->count(); $result->next(), $i++) {
            $format[] = $result->current()['extension'];
        }
        $format[] = 'old';
        $ext = pathinfo($new, PATHINFO_EXTENSION);
        if (!in_array($ext, $format) || $old==$new) {
            $this->log("invalid format");
            return;
        }
        $count=0;
        foreach ($files as $file) {
            $base = dirname($file['url']);
            $name = basename($file['url']);
            if ($name==$old) {
                $path = dirname($file['path']).'/'.$new;
                if (file_exists($file['path']) && !file_exists($path)) {
                    $this->log("renamed ".$file['path'] . ' to ' .$new);
                    rename($file['path'], $path);
                    $files[$count]['path'] = $path;
                    $files[$count]['name'] = $ext.'/'.$new;
                    $files[$count]['url'] = $base.'/'.$new;
                }
            }
            $count++;
        }
        return $files;
    }

    /** Update status from temp table: return false if there was no deletion */
    public function delete($oid) {
        // test returns 0: error, 1: upload records, 2: temp, 3: opus
        $b = false;
        $res = $this->test($oid);
        if ($res == 2) {
            $sql = 'select source_opus from temp where status="deleted"'
                 . ' and source_opus='.$oid;
            //$row = $this->db->query($sql)->execute()->getResource()->fetch();
            $row = $this->db->query($sql)->execute()->current();
            if (empty($row)) { // mark entry as removed
                $sql = 'UPDATE temp set status="deleted"'
                    . ', date_modified=UNIX_TIMESTAMP()'
                    . ' where source_opus='.$oid;
                $pdo = $this->db->query($sql)->execute();
                $this->log("DataStorage delete temp " . $oid . ' # ' .$res);
            } else { // finally remove entry from temp tables
                $res = $this->remove($oid);
                $this->log("DataStorage remove temp " . $oid . ' # ' .$res);
                $this->log("*** DataStorage does not remove files! ***");
            }
            $sql = 'DELETE from opus_publications where oid='.$oid;
            $pdo = $this->db->query($sql)->execute();
            $b = true;
        } else if ($res == 3) {
            $this->unpublish($oid); // back to temp
            $sql = 'DELETE p, f from opus_publications p left join opus_files f'
                 .' on p.oid=f.oid where p.oid='.$oid;
            $res = $this->db->query($sql)->execute();
            $b = true; 
        } else { // invalid
            $this->log("DataStorage refused to delete " . $oid . ' # ' .$res);
        }
        return $b;
    }

    private function readData($sql) {
        $stmt = $this->db->createStatement($sql);
        $stmt->prepare();
        // $result = $stmt->execute();
        // $row = $result->next();
        $row = $stmt->execute()->current();
        $data = [];
        if (empty($row)) {
            //
        } else {
            $data = $this->readDataRow($row);
        }
        return $data;
    }

    private function readDataRow($row) {
        $data['dcterms:title'] = $row['title'];
        $data['opus:creator_corporate'] = $row['creator_corporate'];
        $data['dcterms:subject']   = $row['subject_swd'];
        $data['dcterms:abstract']  = $row['description'];
        $data['dcterms:publisher'] = $row['publisher_university'];

        $data['dcterms:contributor'] = $row['contributors_name'];
        $data['opus:contributors_corporate'] = $row['contributors_corporate'];
        $data['dcterms:created'] = $row['date_year'];
        // GH20191006 : special date
        if (empty($data['dcterms:created'])) {
            // skip
        } else if (substr($data['dcterms:created'],2,2)=='XX') {
            $data['dcterms:created'] = '11' . substr($data['dcterms:created'],0,2);
        }
        $data['dcterms:issued'] = $row['date_creation_esc'];
        $data['dcterms:type'] = $row['type'];

        $data['dcterms:source']   = $row['source_title'];
        $data['opus:source_swb']  = $row['source_swb'];
        $data['dcterms:language'] = $row['language'];
        $data['opus:email']       = $row['verification'];
        $data['opus:subject']     = $row['subject_uncontrolled_german'];
        $data['opus:subject2']    = $row['subject_uncontrolled_english'];

        // $data['dcterms:alternative'] = $row['language']=='eng'?
        //                                $row['title_de']:$row['title_alt'];
        $data['dcterms:alternative'] = $row['title_alt'];
        $data['opus:abstract2'] = $row['description2'];
        $data['opus:subject_type'] = $row['subject_type'];      
        $data['opus:date_valid'] = $row['date_valid'];
        // $data['opus:description_lang'] = $row['description_lang'];  

        $data['opus:language']   = $row['description2_lang'];
        $data['opus:ddc']        = $row['sachgruppe_ddc']; 
        $data['opus:domain']     = $row['bereich_id'];
        $data['dcterms:license'] = empty($row['lic']) ? 'urhg' : $row['lic'];
        $data['opus:isbn']       = $row['isbn'];
        $data['opus:note'] = trim($row['bem_intern']);
        $data['opus:comment'] = $row['bem_extern'];
        $data['opus:source_opus'] = $row['source_opus'];
        $data['dcterms:identifier'] = $row['urn'] ?? '';
        $data['aiiso:faculty'] = $row['faculty'];
        $data['aiiso:institute'] = $row['inst_nr'];

        $data['dcterms:dateAccepted'] = $row['date_accepted'];
        $data['opus:advisor'] = $row['advisor'];
        $data['opus:status'] = $row['status'];
        $data['opus:uid'] = $row['uid'] ?? '';
        $data['opus:serial'] = $row['sr_id'];
        $data['opus:volume'] = $row['sequence_nr'];
        // $data['opus:collection'] = $row['coll_id'];

        return $data;
    }

    /** returns file docbase on success, otherwise null */
    public function saveFile($files, &$oid = null) {
        $data = [];
        if (empty($files)) {
            $this->log('No Storage -- no file.');
            return $data;
        } else if (count($files)==0) {
            $this->log('No Storage -- zero files.');
            return $data;
        } else if (empty($files[0]['name'])) {
            return $data;
        }
		$sql = "select extension from format where diss_format=1";
        //$res = $this->db->query($sql)->execute()->getResource()->fetchAll();
        //foreach($res as $row) { $format[] = $row['extension']; }
        $result = $this->db->query($sql)->execute();
        for ($i=0; $i<$result->count(); $result->next(), $i++) {
            $format[] = $result->current()['extension'];
        }
        $path = null;
        foreach($files as $file) {
            if (empty($file['name'])) {
			    continue;
            }
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (!in_array($ext, $format)) {
				$this->log("No Storage -- bad file ".$oid." # ".$ext);
			    continue;
			}
			$year = date('Y');
            if (empty($oid)) {
				$oid = $this->getIdentifier();
			} else { // check file path, we may already have one
                for ($y=$year-3; $y<=$year; $y++) {
			        if (file_exists($this->path.$this->temp.$y.'/'.$oid)) {
					    $year = $y;
                        break;
                    } 
                }
            }
		    $path = $this->path.$this->temp.$year.'/'.$oid;
            $fname = $file['name'];
           
            // pdf to pdf folder and everything else to data folder
            $target = $ext=='pdf' ? $ext : 'data';
            if (!is_dir($path.'/'.$target)) {
                mkdir($path.'/'.$target, 0777, true);
            } // copy submitted file
            $fpath = $path.'/'.$target.'/'.$fname;
            $url = $this->docbase.$this->temp.$year.'/'.$oid
                 .'/'.$target.'/'.$fname;
			copy($file['tmp_name'], $fpath);
            $data = [ 'path' => $fpath, 'name' => $fname, 'url' => $url ];
		}
        return $data;
    }

    // Legacy support : file lookup 
    private function writeIndex($oid) {
        $index = "<html><body><h2>Dateien</h2><ul>";
        $files = $this->readFiles($oid);
        $path = count($files)<1?null:$files[0]['path']; 
        if (empty($path)) {
            return;
        }
        $path = dirname(dirname($path));
        foreach($files as $file) {
            $fname = $file['name'];
            $index .= '<li><a href="'.$fname.'">'.$fname.'</li>';
        }
        $index .= "</ul></body></html>";
        file_put_contents($path.'/index.html', $index);
    }

    // write data to logfile in case of emergency
    private function writeLog($data) {
        if (empty($data['opus:source_opus'])) { 
            $this->log('No data, no log');
        } else {
            $oid = $data['opus:source_opus'];
            $path = $this->path . $this->temp . date('Y') . '/' . $oid;
            if (file_exists($path)) {
                $path = $path . '/meta-' . $oid . '.log';
                file_put_contents($path, print_r($data, true));
            } else {
                $this->log('No logfile ' . $oid);
            }
        }
    }

    private function getIdentifier() {
        // $res = $this->db->query("LOCK TABLES seq_temp WRITE")->execute();
        $upd = "update seq_temp set id=LAST_INSERT_ID(id+1)";
        $res = $this->db->query($upd)->execute()->count();
        $sql = "select max(id) id from seq_temp";
        $result = $this->db->query($sql)->execute();
        $row = null;
        $oid = 0;

        if (empty($result)) {
            //
        } else {
            $row = $result->current();
            $oid = empty($row) ? 0 : $row['id'];
        }

        if (empty($oid)) {
            $oid = 1; // start over
            $upd = "insert into seq_temp values(1)";
            $res = $this->db->query($upd)->execute()->count();
        }
        // $res = $this->db->query("UNLOCK TABLES")->execute();
        return $oid;
    }

    private function readCollections($urn, $oid, $tid) {
        $data = [];
	    $sql = 'SELECT o.coll_id, c.coll_name from';
        if ($urn) {
            $sql .= ' opus_publications p, opus_coll o, collections c'
                . ' where p.oid=o.source_opus'
                . ' and o.coll_id=c.coll_id'
                . ' and p.urn like "'.$urn.'_"';
        } else if ($oid) {
            $sql .= ' opus_coll o, collections c where source_opus='.$oid
                . ' and o.coll_id=c.coll_id';
        } else if ($tid) { 
            $sql .= ' temp_coll o, collections c where source_opus='.$tid
                . ' and o.coll_id=c.coll_id';
        }
        $sql .= ' ORDER BY o.coll_id';

        $stmt = $this->db->createStatement($sql);
        $stmt->prepare();
        $result = $stmt->execute();
        $num = 0;
        foreach($result as $row) {
		    $data['opus:coll_'.$row['coll_id']] = $row['coll_name'];
            $num++;
        }
        return $data;
    }

    /** return 0 on error, 1 for new created temp records, 
        2 for temp updates, 3 for opus updates */
    public function save(&$data) {
        // $json = json_encode($data);
        // file_put_contents($this->path.'/data.json', $json);
        $res = 0;
        $oid = $data['opus:source_opus'];
        if (empty($this->db)) {
            $this->log("Storage form save : no database");
        } else if (empty($oid) && empty($data['dcterms:title'])) {
            $this->log("Storage form save : empty submission");
        } else if (empty($oid)) { // metadata only, no oid created until now
            $data['opus:source_opus'] = $this->getIdentifier();
            $this->writeLog($data); // this never happens
            $res = $this->saveData($data, 1);
            $res = $this->saveAuthor($data, $res);
            $res = $this->saveDiss($data, $res);
            $res = $this->saveInst($data, $res);
            $res = $this->saveSeries($data, $res);
            $res = $this->saveCollection($data, $res);
            $this->log("Storage created late: " . $data['opus:source_opus']);
        } else if (is_numeric($oid)) { 
            $res = $this->test($oid);
            if ($res==1) {
                $this->writeIndex($oid);
                $this->writeLog($data);
            }
            $data = $this->cleanData($data);
            $res = $this->saveData($data, $res);
            $res = $this->saveAuthor($data, $res); 
            $res = $this->saveDiss($data, $res); 
            $res = $this->saveInst($data, $res);
            $res = $this->saveSeries($data, $res);
            $res = $this->saveCollection($data, $res);
            $res = $this->saveLinks($oid, $data, $res);
        }
        return $res;
    }

    /** copy from temp to opus */
    public function copy($data) {
        $oid = $data['opus:source_opus'];
        $res = $this->test($oid);
        // test returns 0: error, 1: new record, 2: temp, 3: opus
        $this->log('DataStorage copy ' . $oid . '[' . $res . ']');
        if ($res==2) { 
            // OK
        } else if ($res==3) { 
            // published object :: update publications
            $urn = $data['dcterms:identifier'];
            $this->publish($oid, $data['opus:uid'], $urn);
            return $this->readFiles($oid);
        } else {
            return false;
        }

        $sql = 'select source_opus from temp where status="deleted"'
             . ' and source_opus='.$oid;
        $row = $this->db->query($sql)->execute()->current();
        if (empty($row)) {
            // OK
        } else {
            $sql = 'update temp set status="neu" where status="deleted"'
                 . ' and source_opus='.$oid;
            $row = $this->db->query($sql)->execute();
            return false;
        }

        if (empty($data['dcterms:issued'])) {
            $sql = 'update temp set date_creation=UNIX_TIMESTAMP(NOW())'
                 . ' where source_opus='.$oid;
            $res = $this->db->query($sql)->execute();
        }
        $sql = 'INSERT IGNORE INTO opus '
             . 'select * from temp where source_opus=' . $oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_autor'
             . ' select * from temp_autor where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_diss'
             . ' select * from temp_diss where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_inst'
             . ' select * from temp_inst where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_schriftenreihe'
             . ' select * from temp_schriftenreihe where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_coll'
             . ' select * from temp_coll where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();

        // remove entry from temp tables
        $this->remove($oid);

        $files = $this->copyFiles($data);
        $this->cover($files);
        return $files; // return $files to directly index this record 
    }
  
    /** Remove entry from temp table */
    private function remove($oid) {
        $sql = 'DELETE from temp where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'DELETE from temp_autor where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'DELETE from temp_diss where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'DELETE from temp_inst where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'DELETE from temp_schriftenreihe where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'DELETE from temp_coll where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
    }

    /** Copy back from opus to temp */
    private function unpublish($oid) {
        $year = date('Y');
        $files = $this->readFiles($oid);
        if (!empty($files[0]['path'])) { 
            // PHP 7: $year = basename(dirname($files[0]['path'],3));
            $year = basename(dirname(dirname(dirname($files[0]['path']))));
        }
        $source = $this->path . '/' . $year . '/' . $oid;
        $target = $this->path . $this->temp . $year . '/' . $oid;
		if (file_exists($source) && file_exists($target)) {
            // both exists -- skip hard delete
        } else if (file_exists($source)) {
            rename($source, $target); // move from opus to temp area
        }

        $sql = 'INSERT IGNORE INTO temp '
             . ' select * from opus where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'UPDATE temp set status="neu" where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_autor'
             . ' select * from opus_autor where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_diss'
             . ' select * from opus_diss where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_inst'
             . ' select * from opus_inst where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_schriftenreihe'
             . ' select * from opus_schriftenreihe where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();

        $sql = 'delete from opus_autor where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'delete from opus_diss where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'delete from opus_inst where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        $sql = 'delete from opus where source_opus='.$oid;
        $res = $this->db->query($sql)->execute();
        if (empty($res)) {
            $this->log('FATAL: unpublish failed ['.$oid.']');
        } else {
            $this->log('unpublish ['.$oid.'] '.$source.' '.$target);
        }
    }

    /** update publications table with new uid */
    private function publish($oid, $uid, $urn = null) {
        $sql = 'INSERT into opus_publications (oid, uid, date)'
            . ' values ('. $oid .',"'. $uid .'", now())'
            . ' ON DUPLICATE KEY UPDATE date=now()';
        if (empty($urn) && !empty($this->urn_prefix)) {
            $urn = $this->createURN($uid);
            $sql = 'INSERT into opus_publications (oid, uid, urn, date)'
                . ' values ('. $oid .',"'. $uid .'", "'. $urn .'",'. 'now())'
                . ' ON DUPLICATE KEY UPDATE urn="'.$urn.'", date=now()';
        } else {
            $this->log('Zero '.$urn.' prefix ['.$this->urn_prefix.']');
        }

        if (empty($uid)) {
            $this->log('publications: zero uid ' . $oid);
        } else if (substr_count($uid,'/')==2) {
            $this->log('publications: ' . $oid . ' ' . $uid);
            // $this->log('[' . $sql . ']');
            $res = $this->db->query($sql)->execute();
        } else {
            $this->log('publications: bad uid ' . $oid . ' ' . $uid);
        }

    }

    // return 26 metadata parameters mapped from terms to database columns
    private function map(&$data) {
        $oid = $data['opus:source_opus'];
        $params = [];
        $params[0] = $data['dcterms:title'];
        $params[1] = $data['opus:creator_corporate'];
        $params[2] = $data['dcterms:subject'];
        $params[3] = $data['dcterms:abstract'];
        $params[4] = $data['dcterms:publisher'];
        $params[5] = $data['dcterms:contributor']; // contributors_name
        $params[6] = $data['opus:contributors_corporate']; // Institution
        $params[7] = $data['dcterms:created'];
        $params[8] = $data['dcterms:issued'];
        $params[9] = $data['dcterms:type'];
        $params[10] = $data['dcterms:source'];

        $params[11] = $data['dcterms:language'];
        $params[12] = $data['opus:email'];
        $params[13] = $data['opus:subject'];
        $params[14] = $data['opus:subject2'];
        $params[15] = $data['dcterms:alternative']; // title_alt
        $params[16] = $data['opus:abstract2'];
        $params[17] = null; // $data['opus:subject_type'];      
        $params[18] = 0; // $data['opus:date_valid'];
        $params[19] = $data['dcterms:language'];  
        $params[20] = $data['opus:language'];

        $params[21] = $data['opus:ddc']; 
        $params[22] = $data['opus:domain']; // bereich_id
        if (empty($params[22])) {
            $params[22] = $this->domain;
        }
        $params[23] = $data['dcterms:license'];
        $params[24] = $data['opus:isbn'];
        $params[25] = $data['opus:note'];
        $params[26] = $data['opus:comment'];
        $params[27] = $data['opus:status'];

        for($i=0; $i<count($params); $i++) {
            if (empty(trim($params[$i]))) {
                $params[$i] = null;
            }
        }
        return $params;
    }

    // published records can have additional links 
    private function saveLinks($oid, $data, $upd) {
        if ($upd==3) {
            if (!empty($data['opus:details'])) {
                $link = $data['opus:details'];
                $name = 'Bibliographic Details';
                $tag = 'desc';
                $this->saveOpusLink($oid, $link, $name, $tag);
            }
            if (!empty($data['opus:research'])) {
                $link = $data['opus:research'];
                $name = 'Research data';
                $tag = 'item';
                $this->saveOpusLink($oid, $link, $name, $tag);
            }
        }
        return $upd;
    }

    private function saveOpusLink($oid, $link, $name, $tag) {
        $sql = null;

        if (empty($link)) {
            //
        } else if (strpos($link, '/')!==false) {
            $sql = 'REPLACE into opus_links (oid, name, link, tag)'
                . ' values(' . $oid .',"'.$name.'","'.$link.'","'.$tag.'")';
        } else {
            $sql = 'DELETE from opus_links where oid='.$oid
                . ' and name="'.$name.'"';
        }

        if (empty($sql)) {
            //
        } else {
            $this->db->query($sql)->execute()->count();
        }
    }

    /** return 0 on error, 1 new records, 2 temp, 3 opus, 4 NaN */
    private function test($oid) {
        if (empty($oid)) {
		    return 0;
        }
        $sql = 'select 2 "x" from temp where source_opus=' . $oid;
        $row = $this->db->query($sql)->execute()->current();
        if (empty($row)) {
            $sql = 'select 3 "x" from opus where source_opus=' . $oid;
            $row = $this->db->query($sql)->execute()->current();
            if (empty($row)) {
		        return 1; // insert into temp
            } else {
                return $row['x']; // return 3 : modify opus
            }
		}
        return $row['x']; // modify temp or copy from temp to opus
	}

    private function cleanData(&$data) {
        // mysql 5.0.1 fails with data too long
        $data['dcterms:subject'] = $this->cleanString($data['dcterms:subject'], 150);
        $data['dcterms:publisher'] = $this->cleanString($data['dcterms:publisher'], 60);
        // column is varchar(150) and can store multibytes
        $data['opus:subject'] = $this->cleanString($data['opus:subject'], 150);
        $data['opus:subject2'] = $this->cleanString($data['opus:subject2'], 150);
        $data['dcterms:contributor'] = $this->cleanString($data['dcterms:contributor'], 335); 
        $data['opus:contributors_corporate'] = $this->cleanString($data['opus:contributors_corporate'], 150); 
        $data['dcterms:abstract'] = $this->cleanString($data['dcterms:abstract']);
        $data['opus:abstract2'] = $this->cleanString($data['opus:abstract2']);
        if (empty($data['dcterms:created'])) {
            // skip
        } else if (substr($data['dcterms:created'],0,2)=='11') {
            // GH20191006 : special date
            $data['dcterms:created'] = substr($data['dcterms:created'],2,2).'XX';
            $this->log('GH20191006 special date ' . $data['dcterms:created']);
        }
        return $data;
    }

    // replace the hell out of the string
    private function cleanString($text, $length=null) {
        // detect encoding and recode to UTF-8
        $enc = mb_detect_encoding($text);
        $text = mb_convert_encoding($text, 'UTF-8', $enc);
        // reject overly long 2 byte sequences, as well as characters 
        // above U+10000 and replace with ?
        $text = preg_replace('/[\x00-\x08\x10\x0B\x0C\x0E-\x19\x7F]'.
            '|[\x00-\x7F][\x80-\xBF]+'.
            '|([\xC0\xC1]|[\xF0-\xFF])[\x80-\xBF]*'.
            '|[\xC2-\xDF]((?![\x80-\xBF])|[\x80-\xBF]{2,})'.
            '|[\xE0-\xEF](([\x80-\xBF](?![\x80-\xBF]))|(?![\x80-\xBF]{2})|[\x80-\xBF]{3,})/S', '?', $text );
 
        // reject overly long 3 byte sequences and UTF-16 surrogates and 
        // replace with ?
        $text = preg_replace('/\xE0[\x80-\x9F][\x80-\xBF]'.
            '|\xED[\xA0-\xBF][\x80-\xBF]/S','?', $text );
        if (empty($length)) {
            return $text;
        } else {
            return mb_substr($text, 0, $length);
        }
    }

    /** return 0 on error, 1 for new created records, 2 for updates */
    private function saveData(&$data, $upd) {
        $oid = $data['opus:source_opus'];
        $values = 'title=?, creator_corporate=?, subject_swd=?, description=?, '
              . 'publisher_university=?, contributors_name=?,'
              . 'contributors_corporate=?, date_year=?, ' // 0..7
              . 'date_creation=UNIX_TIMESTAMP(STR_TO_DATE(?,"%Y-%m-%d")),' // 8
              . 'date_modified=unix_timestamp(now()),'
              . 'type=?, source_title=?, ' // 9 10 
              . 'language=?, verification=?, subject_uncontrolled_german=?, '
              . 'subject_uncontrolled_english=?, title_alt=?, description2=?, '
              . 'subject_type=?, date_valid=?, description_lang=?, '
              . 'description2_lang=?, sachgruppe_ddc=?,' // 20 21
              . 'bereich_id=?, lic=?, isbn=?, bem_intern=?,' // 22..25
              . 'bem_extern=?, status=?'; // 27

        if ($upd==1) {
            $sql = 'INSERT INTO temp set ' . $values . ', source_opus=? '; // 28
            $params = $this->map($data);
            $params[22] = $this->domain; 
            $params[27] = 'neu'; 
            $params[28] = $oid;
        } else if ($upd==2) {
            $params = $this->map($data);
            $sql = 'UPDATE temp set '.$values.' where source_opus=' . $oid;
        } else if ($upd==3) {
	        $sql = 'UPDATE opus set ' . $values . ' where source_opus='.$oid;
            $params = $this->map($data);
            if (empty($params[8])) { // dcterms:issued
                $params[8] = date('Y-m-d');
            } else {
                // $this->log("issued " . $params[8]);
            }
            if (empty($params[21]) || $params[21]=='no') {
                $ddc = 'SELECT f.ddc from faculty f, opus_diss d'
                . ' where d.publisher_faculty=f.nr and d.source_opus='.$oid;
                $res = $this->db->query($ddc)->execute()->current();
                $params[21] = $res['ddc'] ?? '020';
                $this->log('DDC guess ['.$params[21].']');
            }
            // Zero status for new records
            if (!empty($params[27]) && $params[27]=='neu') {
                $params[27] = null; 
            }
            // External notes if bem_intern says so : see opus:comment
            // if (!empty($params[25]) && substr($params[25],0,4)=='ext:') {
            //     $params[26] = trim(substr($params[25],4));
            //     // $params[25] = null;
            // }
        } else {
            return 0;
        }
		//var_dump($data); var_dump("####\n"); var_dump($params);
        $stmt = $this->db->createStatement($sql);
        $stmt->prepare($sql);
        $stmt->execute($params);
        return $upd;
    }

    /** return 0 on error, 1 for new records, 2 for temp, 3 for opus */
    private function saveAuthor(&$data, $upd) {
        $oid = $data['opus:source_opus'];
        if ($upd==1) {
            $sql = 'REPLACE INTO temp_autor';
        } else if ($upd==2) {
	        $sql = 'REPLACE INTO temp_autor';
        } else if ($upd==3) {
	        $sql = 'REPLACE INTO opus_autor'; 
        } else {
            return 0;
        }
        $sql .= ' (creator_name, reihenfolge, source_opus, orcid, gnd)';
        $sql .= ' VALUES (?,?,?,?,?)';

        $stmt = $this->db->createStatement($sql);
        $stmt->prepare($sql);
        for($i=0; $i<$data['opus:number']; $i++) {
            if (empty($data['dcterms:creator'.$i])) {
                $sql = 'delete from opus_autor where source_opus='.$oid
                     . ' and reihenfolge='.($i+1);
                if ($upd==2) {
                    $sql = 'delete from temp_autor where source_opus='.$oid
                         . ' and reihenfolge='.($i+1);
                } 
                $res = $this->db->query($sql)->execute();
                $this->log('deleted ['.$sql.'] '.$res->count());
            } else {
                $aid = empty($data['opus:authorid'.$i]) ? null 
                    : $data['opus:authorid'.$i]; 
                $orc = null;
                $gnd = null;
                if (empty($aid)) {
                    // Nothing
                } else if (strpos($aid, '-') !== false) {
                    $orc = mb_substr($aid, strlen($aid)-19);
                } else {
                    $gnd = mb_substr($aid, strlen($aid)-19);
                }
                $creator = $data['dcterms:creator'.$i];
                $stmt->execute([$creator, $i+1, $oid, $orc, $gnd]);
            }
        }
        return $upd;
    }

    private function saveDiss($data, $upd) {
        if (empty($data['dcterms:dateAccepted'])) { 
            return $upd; 
        }
        $oid = $data['opus:source_opus'];
        if ($upd==1) {
            $sql = 'INSERT INTO temp_diss (source_opus,date_accepted,advisor,title_de,publisher_faculty) VALUES (?,?,?,?,?)';
        } else if ($upd==2) {
            $sql = 'REPLACE INTO temp_diss (source_opus, date_accepted,'
                 . ' advisor, title_de, publisher_faculty) VALUES (?,?,?,?,?)';
        } else if ($upd==3) {
            $sql = 'REPLACE INTO opus_diss (source_opus, date_accepted,'
                 . ' advisor, title_de, publisher_faculty) VALUES (?,?,?,?,?)';
        } else {
            return 0;
        }
        $title_de = $data['dcterms:alternative'];
        if($data['dcterms:language']=='ger') {
            $title_de = null; // preserve information austerity
        }
        if(empty($data['dcterms:alternative'])) {
            $title_de = null; // null values
        }
        $stmt = $this->db->createStatement($sql);
        $stmt->prepare($sql);
        $stmt->execute([$oid,strtotime($data['dcterms:dateAccepted']),
                $data['opus:advisor'], $title_de, $data['aiiso:faculty']]);
        return $upd;
    }

    private function saveInst(&$data, $upd) {
        $oid = $data['opus:source_opus'];
        if ($upd==1) {
            $sql = 'INSERT INTO temp_inst (source_opus,inst_nr) VALUES (?,?)';
        } else if ($upd==2) {
            $sql = 'REPLACE INTO temp_inst (source_opus, inst_nr) VALUES (?,?)';
        } else if ($upd==3) {
            $sql = 'REPLACE INTO opus_inst (source_opus, inst_nr) VALUES (?,?)';
        } else {
            return 0;
        }
        if (empty($data['aiiso:institute'])) {
            // Nothing
        } else {
            $stmt = $this->db->createStatement($sql);
            $stmt->prepare($sql);
            $stmt->execute([$oid, $data['aiiso:institute']]);
        }
        return $upd;
    }

    private function saveSeries($data, $upd) {
        $oid = $data['opus:source_opus'];
        $sid = $data['opus:serial'];
        $vol = empty($data['opus:volume'])?'':$data['opus:volume'];
        if ($upd==1 && empty($sid)) {
            // Nothing
        } else if ($upd==2 && empty($sid)) {
            $sql = 'DELETE FROM temp_schriftenreihe where source_opus='.$oid;
            $res = $this->db->query($sql)->execute();
        } else if ($upd==3 && empty($sid)) {
            $sql = 'DELETE FROM opus_schriftenreihe where source_opus='.$oid;
            $res = $this->db->query($sql)->execute();
        } else if ($upd==1) {
            $sql = 'INSERT INTO temp_schriftenreihe ';
        } else if ($upd==2) {
            $sql = 'INSERT INTO temp_schriftenreihe ';
        } else if ($upd==3) {
            $sql = 'INSERT INTO opus_schriftenreihe ';
        } else {
            return 0;
        }

        if (empty($sid) || empty($oid) || empty($sql)) {
            // Nothing
        } else {
            $sql .= ' (source_opus,sr_id,sequence_nr) VALUES (?,?,?)';
            $sql .= ' ON DUPLICATE KEY UPDATE sr_id='.$sid;
            $sql .= ' ,sequence_nr="'.$vol.'"'; 
            $stmt = $this->db->createStatement($sql);
            $stmt->prepare($sql);
            $stmt->execute([$oid, $sid, $vol]);
        }
        return $upd;
    }

    private function saveCollection($data, $upd) {
        $oid = $data['opus:source_opus'];
        $cid = $data['opus:collection'];
        if ($upd==1 && empty($cid)) {
            // Nothing
        } else if ($upd==2 && empty($cid)) {
            $sql = 'DELETE FROM temp_coll where source_opus='.$oid;
            $res = $this->db->query($sql)->execute();
        } else if ($upd==3 && empty($cid)) {
            $sql = 'DELETE FROM opus_coll where source_opus='.$oid;
            $res = $this->db->query($sql)->execute();
        } else if ($upd==1) {
            $sql = 'INSERT INTO temp_coll ';
        } else if ($upd==2) {
            $sql = 'INSERT INTO temp_coll ';
        } else if ($upd==3) {
            $sql = 'INSERT INTO opus_coll ';
        } else {
            return 0;
        }

        if (empty($cid) || empty($oid) || empty($sql)) {
            // Nothing
        } else {
            $sql .= ' (source_opus,coll_id) VALUES (?,?)';
            $sql .= ' ON DUPLICATE KEY UPDATE coll_id='.$cid;
            // $sql .= ' ON DUPLICATE IGNORE'; -- not recommended
            $stmt = $this->db->createStatement($sql);
            $stmt->prepare($sql);
            $stmt->execute([$oid, $cid]);
        }

        // Check for invalidated collections
        foreach ($data as $key => $val) {
            if (strpos($key, 'opus:coll_')!==FALSE) {
                if ($key===$val) {
                     $upd = 'DELETE from opus_coll where source_opus='.$oid
                         . ' and coll_id='.substr($key, strpos($key, '_')+1);
                     $res = $this->db->query($upd)->execute()->count();
                     $this->log('GH2021-04-26 delete '.$oid.': '.$val.' '.$res);
                }
            }
        }

        return $upd;
    }

    private function copyFiles($data) {
        $oid = $data['opus:source_opus'];
        $files = $data['opus:files'];

        $year = date('Y');
        if (!empty($files[0]['path'])) { 
            // PHP 7: $year = basename(dirname($files[0]['path'],3));
            $year = basename(dirname(dirname(dirname($files[0]['path']))));
        }
        $source = $this->path . $this->temp . $year . '/' . $oid;
        $auto = $this->path . $this->auto . $year . '/' . $oid;
        $target = $this->path . '/' . $year . '/' . $oid;
        $docbase = $this->docbase . '/' . $year . '/' . $oid;

		if (!file_exists(dirname($target))) {
			mkdir($target, 0755, true);
        }
		if (file_exists($source)) {
            rename($source, $target); // move from temp to opus area
        } else if (file_exists($auto)) {
            // GH201711 -- recursive copy instead ?
            // rename($auto, $target); // move from temp to opus area
        }

        $count = 0;
        foreach($files as $file) {
            $sql = 'INSERT into opus_files (oid, name)' 
                 . ' values (' . $oid . ',"' . $file['name'] . '")'
                 . ' ON DUPLICATE KEY UPDATE name="'.$file['name'].'"';
            $res = $this->db->query($sql)->execute();
            $files[$count]['path'] = $target.'/'.$file['name'];
            $files[$count]['url'] = $docbase.'/'.$file['name'];
            $count++;
        }
        return $files;
    }

    private function cover($files) {
        if (empty($files) | empty($files[0]['path'])) {
            return;
        }
        if (!extension_loaded('imagick')) {
             $this->log('No imagick, no cover');
             return;
        }
        $source = null;
        $count = 0;
        foreach ($files as $file) {
            if (substr($file['name'],-4)=='.png' && $count==0) {
                $source = $file['path']; // first png
            } else if ($source==null && substr($file['name'],-4)=='.pdf') {
                $source = $file['path'];  // first pdf 
            }
            $count++;
        }
		if ($source!=null && file_exists($source)) {
            $target = dirname(dirname($source)).'/cover.png';
            if (file_exists($target)) {
                // Nothing
            } else {
                $im = new \Imagick($source.'[0]');
                $im->setIteratorIndex(0);
                $im->setResolution(200,200);
                // $im->scaleImage(800,0);
                $im->scaleImage(78,110);
                $im->setImageFormat('png');
                $im->flattenImages();
                $im->writeImage($target);
                $im->clear();
                $im->destroy();
                $this->log('created cover '. $target);
            }
        }
    }

    private function createURN($uid) {
        $urn = str_replace('diss','',trim($uid));
        $pos = strpos($urn, '/');
        $urn = substr($urn, 0, $pos) . substr($urn,$pos+1);
        $urn = str_replace('/', '-', $urn);
        $urn = $this->urn_prefix . $urn;
        $code = '3947450102030405060708094117############181419151621222324'
              . '2542262713282931123233113435363738########43';
        $chk = null;
        for ($i=$sum=0, $pos=1; $i<strlen($urn); $i++) {
            list($v1,$v2) = [
                substr($code,(ord(strtoupper(substr($urn,$i,1)))-45)*2,1),
                substr($code,(ord(strtoupper(substr($urn,$i,1)))-45)*2+1,1)
            ];
            $sum += ($v1==0) ? $v2*$pos++ : $pos++*$v1+$v2*$pos++;
            $chk = $sum/$v2 % 10;
        }
        return $urn.$chk;
    }

    private function log(String $msg) {
        if ($this->dev) {
            error_log('DataStorage: ' . $msg);
        }
    }

}

