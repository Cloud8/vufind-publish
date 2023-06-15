<?php
/**
 * Data Storage
 * 
 * PHP version 8
 *
 * Copyright (C) Abstract Power 2023.
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
 * @category Publishing
 * @package  Upload
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
 * @date 2022-06-15
 */
namespace Dbib\Upload;

class DataStorage {

    var $about; // sql
    var $db;
    var $domain;
    var $path;
    var $docbase;
    var $temp;
    var $auto;
    var $urn_prefix;
    var $doi_prefix;

    public function __construct($db, $params = []) {
        $this->db = $db;
        $this->about = $params['about'] ?? null; // SQL query
        $this->domain = $params['domain'];
        $this->urn_prefix = $params['urn_prefix'] ?? null;
        $this->doi_prefix = $params['doi_prefix'] ?? null;
        $sql = 'SELECT concat(path, base) path, base from opus_domain where id='
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

    public function read($id, $colls = []) {
        $data = [];
        $urn = substr($id, 0, 4) == 'urn:' ? $id : false;
        $oid = substr($id, 0, 5) == 'opus:' ? substr($id, 5) : false;
        $tid = substr($id, 0, 5) == 'temp:' ? substr($id, 5) : false;

        if (empty($oid) && empty($tid)) {
            $sql = 'SELECT oid from opus_publications where urn like "';
            $row = $this->db->query($sql.$id.'_"')->execute()->current();
            $oid = $row['oid'] ?? false;
        } else if (empty($oid)) {
            $oid = $tid;
        }

        if (empty($oid)) {
            $oid = 0;
        } else {
            $queries = '';
            if (file_exists($this->about)) {
                $queries = file_get_contents($this->about);
            } else if (file_exists('../'.$this->about)) {
                $queries = file_get_contents('../'.$this->about);
            }
            $queries = str_replace('<oid>', $oid, $queries);
            $query = explode(';', $queries);
            foreach($query as $sql) {
                $x = strpos($sql, 'from ') + 5;
                $table = substr($sql, $x, strpos($sql, ' ', $x)-$x);
                if ($tid) {
                    $sql = str_replace('from opus', 'from temp', $sql);
                    $sql = str_replace('opus_diss', 'temp_diss', $sql);
                    $sql = str_replace('opus_inst', 'temp_inst', $sql);
                    $sql = str_replace('opus_autor', 'temp_autor', $sql);
                }

                $type = in_array($table, $colls) ? 'collection' : 'item';
                $rows = $this->db->query($sql)->execute();
                if (count($rows)==0) {
                    //
                } else if ($type=='item') {
                    foreach($rows as $row) {
                        foreach($row as $key => $val) {
                            $data[$table.':'.$key] = $val;
                        }
                    }
                } else foreach($rows as $row) {
                    foreach($row as $key => $val) {
                        $data[$table.':'.$key][] = $val;
                    }
                }
            }
        }

        if (empty($urn)) {
            $data['meta:files'] = $this->readFiles($oid);
        } else {
            $data['meta:files'] = $this->readFilesDB($oid);
            // $data['meta:files'][] = $data['opus_files:name'];
        }

        if (empty($data['meta:files'])) {
            error_log('zero files ['.$id.']');
        } else foreach ($data['meta:files'] as $file) {
            // error_log('read file ['.($file['name'] ?? $file['url']).']');    
        }

        return $data;
    }

    /** return 0 on error, 1 for new created temp records, 
        2 for temp updates, 3 for opus updates */
    public function write($terms, $data) {
        // $json = json_encode($data);
        // file_put_contents($this->path.'/data.json', $json);
        $upd = 0;
        $oid = $data['opus:source_opus'];
        $data = $this->cleanData($terms, $data);

        if (empty($this->db)) {
            $this->log("Storage form save : no database");
        } else if (empty($oid) && empty($data['dcterms:title'])) {
            $this->log("Storage form save : empty submission");
        } else if (empty($oid)) { // metadata only, no oid created until now
            $data['opus:source_opus'] = $this->getIdentifier();
            $this->writeLog($data); // never happens
            $upd = $this->save($terms, $data, 1);
            $this->log("Storage created late: " . $data['opus:source_opus']);
        } else if (is_numeric($oid)) { 
            $upd = $this->test($oid);
            if ($upd==1) {
                $this->writeIndex($oid);
                $this->writeLog($data);
            }
            $upd = $this->save($terms, $data, $upd);
        }
        // error_log('DataStorage save ' . $oid . ' ['.$upd.']');
        return $upd;
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

        $sql = 'SELECT source_opus from temp where status="deleted"'
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
             . 'SELECT * from temp where source_opus=' . $oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_autor'
             . ' SELECT * from temp_autor where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_diss'
             . ' SELECT * from temp_diss where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_inst'
             . ' SELECT * from temp_inst where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_schriftenreihe'
             . ' SELECT * from temp_schriftenreihe where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO opus_coll'
             . ' SELECT * from temp_coll where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();

        // remove entry from temp tables
        $this->remove($oid);

        $files = $this->copyFiles($data);
        $this->cover($files);
        return $files; // return $files to directly index this record 
    }
  
    /** Rename a file and return updated file array */
    public function rename($old, $new, $files) {
		$sql = "SELECT extension from format where diss_format=1";
        $res = $this->db->query($sql)->execute()->getResource()->fetchAll();
        foreach($res as $row) {
		    $format[] = $row['extension'];
		}
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
            $sql = 'SELECT source_opus from temp where status="deleted"'
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

		$sql = "SELECT extension from format where diss_format=1";
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
            if (empty($file['tmp_name'])) {
                error_log('Bad file ?? ' . $file['name']);
            } else {
			    copy($file['tmp_name'], $fpath);
                error_log('New file ' . $file['name']);
                $data[] = [ 'path' => $fpath, 'name' => $fname, 
                        'url' => $url, 'size' => $file['size'] ];
            }
		}
        return $data;
    }

    /** return array of file urls */
    private function readFilesDB($oid) {
        $files = [];
        $sql = 'SELECT concat("/",uid,"/",name) url, name'
             . ' from opus_files f, opus_publications p'
             . ' where f.oid=p.oid and f.oid='.$oid;
        $rows = $this->db->query($sql)->execute();
        foreach($rows as $row) {
            $files[] = ['name' => $row['name'], 'url' => $row['url']];
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

		$sql = 'SELECT extension from format where diss_format=1';
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
        $sql = "SELECT max(id) id from seq_temp";
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
             . ' SELECT * from opus where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'UPDATE temp set status="neu" where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_autor'
             . ' SELECT * from opus_autor where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_diss'
             . ' SELECT * from opus_diss where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_inst'
             . ' SELECT * from opus_inst where source_opus='.$oid; 
        $res = $this->db->query($sql)->execute();
        $sql = 'INSERT IGNORE INTO temp_schriftenreihe'
             . ' SELECT * from opus_schriftenreihe where source_opus='.$oid; 
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
        $sql = 'SELECT 2 "x" from temp where source_opus=' . $oid;
        $row = $this->db->query($sql)->execute()->current();
        if (empty($row)) {
            $sql = 'SELECT 3 "x" from opus where source_opus=' . $oid;
            $row = $this->db->query($sql)->execute()->current();
            if (empty($row)) {
		        return 1; // insert into temp
            } else {
                return $row['x']; // return 3 : modify opus
            }
		}
        return $row['x']; // modify temp or copy from temp to opus
	}

    private function cleanData($terms, $data) {
        foreach($terms as $term) {
            if (empty($data[$term[0]])) {
                //
            } else if (substr($term[2],0,7) == 'varchar') {
                $len = strpos($term[2],')') - strpos($term[2],'('); 
                $data[$term[0]] = substr($data[$term[0]], $len);
                $data[$term[0]] = $this->cleanString($data[$term[0]]);
            } else if ($term[2] == 'text') {
                $data[$term[0]] = $this->cleanString($data[$term[0]]);
            }
        }
        return $data;
    }

    // replace the hell out of a string
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
    private function saveOne($terms, $data, $upd) {
        // $type = in_array($table, $coll) ? 'collection' : 'item';
    }

    /** return 0 on error, 1 for new created records, 2 for updates */
    private function save($terms, $data, $upd) {
        $values = [];
        $params = [];
        $author = [];
        $series = [];
        $inst = [];
        $diss = [];

        foreach($terms as $term) {
            if (empty($data[$term[0]]) || empty($term[2])) {
                //
            } else if (substr($term[0],0,5) == 'opus:') {
                $values[] = substr($term[0],5); 
                $params[] = $data[$term[0]];
            } else if (substr($term[0],0,11) == 'opus_autor:') {
                $author[$term[0]] = $data[$term[0]];
            } else if (substr($term[0],0,10) == 'opus_diss:') {
                $diss[$term[0]] = $data[$term[0]];
            } else if (substr($term[0],0,10) == 'opus_inst:') {
                $inst[$term[0]] = $data[$term[0]];
            } else if (substr($term[0],0,20) == 'opus_schriftenreihe:') {
                $series[$term[0]] = $data[$term[0]];
            }
        }
        
        $last = end($values);
        $oid = $data['opus:source_opus'];
        $sql = 'UPDATE opus set ';
        if ($upd==1 || $upd==2) {
            $sql = 'UPDATE temp set ';
        }
        foreach($values as $v) {
            $sql .= $v.'=?'.($v==$last ? '' : ',');
        }
        $sql .= ' where source_opus='.$oid;
        // error_log('GH2023-06 type ['.$data['opus:type'].']');
        // error_log('GH2023-06 year ['.$data['opus:date_creation'].']');
        try {
            $stmt = $this->db->createStatement($sql);
            $stmt->prepare($sql);
            $stmt->execute($params);
        } catch (\Exception $ex) {
            $this->log("DataStorage [" . $sql . "] " . $ex->getMessage());
        }

        $upd = $this->saveAuthor($author, $oid, $upd); 
        $upd = $this->saveInst($inst, $oid, $upd); 
        $upd = $this->saveDiss($diss, $oid, $upd); 
        $upd = $this->saveSeries($series, $oid, $upd);
        $upd = $this->saveCollection($data['opus_coll:coll_id'], $oid, $upd);
        // $upd = $this->saveLinks($oid, $data, $upd);
        return $upd;
    }

    /** return 0 on error, 1 for new records, 2 for temp, 3 for opus */
    private function saveAuthor($data, $oid, $upd) {
        $authors = $data['opus_autor:creator_name'] ?? [];
        $authids = $data['opus_autor:authorid'] ?? [];
        $count = 0;

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
        foreach($authors as $autor) {
            $aid = $authids[$count++] ?? null;
            // $this->log('aut [' . $autor . '] ' . $aid);
            $orc = null;
            $gnd = null;
            if (empty($aid)) {
                // Nothing
            } else if (strpos($aid, '-') !== false) {
                $orc = mb_substr($aid, strlen($aid)-19);
            } else {
                $gnd = mb_substr($aid, strlen($aid)-19);
            }
            $stmt->execute([$autor, $count, $oid, $orc, $gnd]);
        }
        $sql = 'DELETE from opus_autor where source_opus='.$oid
            . ' and reihenfolge>'.($count);
        if ($upd==2) {
            $sql = str_replace('opus_', 'temp_', $sql);
        } 
        $res = $this->db->query($sql)->execute();
        return $upd;
    }

    private function saveInst($item, $oid, $upd) {
        $sql = 'REPLACE INTO opus_inst (source_opus,inst_nr) VALUES (?,?)';
        if ($upd==1 || $upd==2) {
            $sql = str_replace('opus_','temp_',$sql);
        }
        $stmt = $this->db->createStatement($sql);
        $stmt->prepare($sql);
        // key opus_inst:inst_nr [16]
        foreach($item as $key => $val) {
            // $this->log('key '.$key.' ['.$val.']');
            $stmt->execute([$oid, $val]);
        }
        return $upd;
    }

    private function saveDiss($data, $oid, $upd) {
        $sql = 'REPLACE INTO opus_diss (source_opus,date_accepted,advisor)';
        if ($upd==1 || $upd==2) {
            $sql = str_replace('opus_','temp_',$sql);
        }
        $sql .= ' VALUES (?,?,?)';
        $advisor = $data['opus_diss:advisor'] ?? null;
        $accepted = $data['opus_diss:date_accepted'] ?? null;
        if (!empty($advisor) && !empty($accepted)) try {
            $stmt = $this->db->createStatement($sql);
            $stmt->prepare($sql);
            $stmt->execute([$oid, $accepted, $advisor]);
        } catch (\Exception $ex) {
            $this->log(' [' . $sql . '] ' . $ex->getMessage());
        }
        return $upd;
    }

    private function saveSeries($data, $oid, $upd) {
        $sid = $data['opus_schriftenreihe:sr_id'] ?? null;
        $vol = $data['opus_diss:sequence_nr'] ?? null;
        $sql = 'INSERT INTO opus_schriftenreihe ';
        $upd = 'DELETE FROM opus_schriftenreihe where source_opus='.$oid;

        if ($upd==1 || $upd==2) {
            $sql = str_replace('opus_','temp_',$sql);
        }

        if ($upd==1 && empty($sid)) {
            // Nothing
        } else if ($upd==2 && empty($sid)) {
            $res = $this->db->query($upd)->execute()->count();
        } else if ($upd==3 && empty($sid)) {
            $res = $this->db->query($upd)->execute();
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

    private function saveCollection($data, $oid, $upd) {
        $sql = 'REPLACE INTO opus_coll (source_opus,coll_id) VALUES (?,?)';
        if ($upd==1 || $upd==2) {
            $sql = str_replace('opus_','temp_',$sql);
        }

        foreach($data as $key => $val) {
            if (empty($val)) {
                $upd = 'DELETE from opus_coll where source_opus='.$oid;
                $res = $this->db->query($upd)->execute()->count();
            }
        }

        $stmt = $this->db->createStatement($sql);
        $stmt->prepare($sql);
        foreach($data as $key => $val) {
            $res = empty($val) ? 0 : $stmt->execute([$oid, $val])->count();
            $this->log('collection key '.$key.' ['.$val.'] '.$res);
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
        error_log('DataStorage: ' . $msg);
    }

}

