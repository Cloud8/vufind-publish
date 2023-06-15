<?php
/**
 * VuFind File Upload
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
 * @category VuFind
 * @package  Upload
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 *
 */

/**
 * @title Form Field and Metadata processing 
 * @date 2023-06-14
 */
namespace Dbib\Upload;

use Laminas\Mail;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\Form\Fieldset;
use Laminas\InputFilter;
use Laminas\Validator;
use Laminas\InputFilter\InputFilterInterface;

/**
  * Note : published by getStorage()->copy(.)
  */
class MetaForm extends Form 
    implements \VuFind\I18n\Translator\TranslatorAwareInterface,
               \Laminas\InputFilter\InputFilterAwareInterface
{

    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    var $terms;

    var $db;
    var $page;
    var $end;
    var $files; // array of files
    var $title;
    var $admin;
    var $mailfrom;

    var $solr;
    var $storage;
    var $indexer;
	var $inputFilter;
	var $publish;
    var $coll;

    public function __construct($name = null, $params = [])
    {
        parent::__construct($name, $params);
        $this->title = 'Publication Information';
        $this->publish = $params['publish'];
        $this->mailfrom = $params['mailfrom'];
        $this->admin = $params['admin'];
        $this->db = $params['db']; // laminas-db
        $this->solr = $params['solr'];
		$this->setAttribute('enctype', 'multipart/form-data');
    }

    public function create($post = []) {
        $this->terms = (new DCTerms())->create($post, $this->admin);
        $this->colls = ['opus_autor', 'opus_coll', 'opus_links', 'opus_files'];

        $this->page = empty($post['spec:page']) ? 0 : (int)$post['spec:page'];
        $this->page = empty($post['back']) ? $this->page+1 : $this->page-1;
        $this->end = $this->addElements() + 1;
        $this->files = $post['meta:files'] ?? [];
        $this->setData($post);

        if (empty($post)) {
            // 
        } else if ($this->isValid()) {
            $this->get('spec:page')->setValue($this->page);
            // $this->log('valid page '.$this->page);
        } else {
            $this->get('spec:page')->setValue(--$this->page);
            $this->log('invalid page '.$this->page);
        }

        return $this;
    }

    /** process form data and return true if form is finished */
    public function process($post) 
    {
        if (!isset($post['spec:page'])) {
            $this->log("empty page request");
            return false; // Do not process strange forms
        } else if ($this->page > $this->end) {
            return true;
        } else if ($this->page==0) {
            return true;
        } else if ($this->page==1 && $this->admin) {
            // $this->log('Upload by ' . $post['opus:verification']);
        } else {
            // $this->get('spec:page')->setValue($this->page--);
            // $this->log('process page '.$this->page.'/'.$this->end);
        }

		if (isset($post['publish'])) {
            $oid = $post['opus:source_opus'];
            $uid = $post['opus:uid'];
            if (empty($this->admin)) {
                // Nothing
            } else if (empty($uid)) {
                $this->log(' publish one [' . $uid .'] '.$oid);
                // $this->files = $this->getStorage()->copy($post);
            } else {
                $this->log(' publish two [' . $uid .'] '. $oid);
                // $this->files = $this->getStorage()->copy($post);
                if (substr_count($uid,'/')==2) {
                    $this->getIndexer()->index($oid, $this->files);
                    $this->log('Index '.$uid.' '.$post['dcterms:identifier']);
                } else {
                    $this->log('Invalid uid, no index update');
                }
            }
            return true;
		} else if (isset($post['callnum'])) {
            $oid = $post['opus:source_opus'];
            $uid = $post['opus:uid']; 
            $uid = $this->createUID($oid, $uid);
            $this->get('opus:uid')->setValue($uid);
		    // $this->page->setValue(--$page);
            return false;
		} else if (isset($post['delete'])) {
            $oid = $post['dcterms:identifier'];
		    $res = $this->getStorage()->delete($post['opus:source_opus']);
            if (empty($oid)) {
                $oid = 'dbib:'.$post['opus:source_opus'];
                $this->getIndexer()->delete($oid); 
                $this->log('delete ['.$oid.']');
            } else {
                $this->getIndexer()->delete($oid); 
            } 
            return true;
		} else if ($this->page == $this->end && isset($post['submit'])) {
		    $res = $this->save($post);
            $oid = $post['opus:source_opus'];
            if ($res==1) {
                $this->sendmail($post);
                $this->log("Submitted ".$oid.' # '.$res);
            } else if ($res==0 && empty($oid)) {
                // skip
            } else if ($res==0 && !is_numeric($oid)) {
                $this->log(" Bad id ".$oid.' # '.$res);
            } else if ($res==3) {
                $status = $post['opus:status'];
                $uid = $post['opus:uid'] ?? $post['opus:status'];
                // $this->log(" Save ".$oid.' ['.$uid.']['.$status.']');
                // $this->get('opus:uid')->setValue($uid);
            } else {
                // $this->log(" Save ".$oid.' # '.$res);
            }
            return false;
		} else if (!empty($post['opus:fileadmin'])) {
            $this->files = $this->getStorage()->rename($post['opus:filename'],
                           $post['opus:fileadmin'], $this->files);
			// $this->page->setValue('1');
	    } else if (!empty($post['spec:file'][0]['name'])) {
            // file upload -- save file and set oid as a side effect
            // var_dump($post['spec:file']);
            $oid = $post['opus:source_opus'];
            $files = $this->getStorage()->saveFile($post['spec:file'], $oid);
            $this->files = array_merge($this->files, $files);
            $this->log('Submit ' . $oid . ' ' . count($this->files));
            $this->get('opus:source_opus')->setValue($oid);
            // var_dump($this->files);
            return false;
		} else {
            // $this->log('GH2023-06 page '.$this->page.'/'.$this->end);
            // var_dump($this->files);
            return false;
		}
    }

    /** By Controller: use data from record as provided by driver */
    public function read($oid) {
		$data = $this->getStorage()->read($oid, $this->colls);

        // remap some values
        $data['faculty:nr'] = $data['opus:faculty'] ?? '';
        unset ($data['opus:faculty']);
        $data['opus_inst:inst_nr'] = $data['opus:inst_nr'] ?? '';
        unset ($data['opus:inst_nr']);
        $data['opus_diss:advisor'] = $data['opus:advisor'] ?? '';
        unset ($data['opus:advisor']);
        $data['opus_diss:date_accepted'] = $data['opus:date_accepted'] ?? '';
        unset ($data['opus:date_accepted']);

        $count=0;
        foreach($data['opus_autor:creator_name'] ?? [] as $creator) {
            $authid = $data['opus_autor:gnd'][$count] 
                 ?? $data['opus_autor:orcid'][$count];
            $data['opus_autor:authorid'][$count] = $authid;
            $count++;
        }

        foreach($this->terms as $term) {
            if (empty($data[$term[0]])) {
                // $this->log('no '. $term[0]);
            }
        }

        foreach($data as $key => $val) {
            $found = 0;
            foreach($this->terms as $term) {
                if ($term[0] == $key) {
                    $found = 1;
                }
            }
            // if ($key=='opus_autor.gnd' || $key=='opus_autor.orcid') {
            //     foreach($data[
            // }
            if (empty($found)) {
                // $this->log('zero '. $key);
                // unset($data[$key]);
            }
        }
        return $data;
    }

    private function save($data) {
        // remap some values 
        $data['opus:type'] = $data['opus:typeid'];
        unset($data['opus:typeid']);
        $data['opus:date_creation'] = strtotime($data['opus:date_creation']);
        $data['opus_diss:date_accepted'] = strtotime($data['opus_diss:date_accepted']);
	    $res = $this->getStorage()->write($this->terms, $data);
        return $res;
    }

    private function sendmail($data) {
        $message = "Ein neues Dokument mit\n\n"
		         . "Archiv-ID: " . $data['source_opus'] .PHP_EOL
		         . "Titel: " . $data['title'] .PHP_EOL
                 //. "Autor: " . $data['dcterms:creator0'] .PHP_EOL
                 . "Email: " . $data['verification'] .PHP_EOL
		         // . "IP: " . $_SERVER['REMOTE_ADDR'] .PHP_EOL
		         . "\nwurde in das Publikationssystem eingestellt.\n";
        $mail = new Mail\Message();
        $mail->setEncoding('utf-8');
        $mail->setFrom($this->mailfrom, "Admin Archiv");
        foreach(explode(',', $this->publish['mailto']) as $to) {
            $this->log('Sending mail to '.$to);
            $mail->addTo(trim($to));
        }
        // $mail->setSubject('neue Publikation: '.$data['dcterms:creator0']);
        $mail->setSubject('neue Publikation: '.$data['title']);
        $mail->setBody($message);
        try {
		    //$transport = new Mail\Transport\Sendmail();
		    $transport = new Mail\Transport\Smtp();
            $transport->send($mail);
        } catch (\Exception $ex){
            $this->log("failed to send mail: " . $ex->getMessage());
        }
    }

    private function addElements() {
        $max = 0;
        foreach($this->terms as $term) {
            $max = $term[3] > $max ? $term[3] : $max;
            $tab = substr($term[0], 0, strpos($term[0], ':'));
            $elem = null;
            if (in_array($tab, $this->colls)) {
                $elem = new Element\Collection($term[0]);
                if ($this->page == $term[3]) {
                    if ($term[2] == 'collection') {
                        $elem->setLabel($term[1]);
                        $elem->setTargetElement(new Element\Text());
                    } else if (substr($term[2],0,6) == 'SELECT') {
                        $options = $this->retrieve($term[2]);
                        $sub = new Element\Select();
				        $sub->setValueOptions($options);
                        $sub->setEmptyOption('with_selected');
                        $sub->setAttribute('size', '1');
                        // $sub->setLabel('');
                        // $elem->setLabel($term[1]);
                        $elem->setTargetElement($sub);
                    }
                } else {
                    $elem->setTargetElement(new Element\Hidden());
                }
                $elem->setOptions([
                    'count' => 1, 'allow_add' => true,
                    'allow_remove' => true, 'should_create_template' => true,
                ]);
            } else if ($term[2] == 'text') {
                $elem = new Element\Textarea($term[0]);
                $elem->setLabel($term[1]);
                $elem->setAttribute('size', '45');
                $elem->setAttribute('cols', '45');
                $elem->setAttribute('rows', '2');
            } else if ($term[2] == 'int') {
                $elem = new Element\Number($term[0]);
                $elem->setLabel($term[1]);
				$elem->setAttributes(['min'  => '0', 'step' => '1']);
                $elem->setAttribute('style', 'width: 5em;');
            } else if ($term[2] == 'box') {
                $elem = new Element\Checkbox($term[0]);
                $elem->setLabel($term[1]);
                $elem->setCheckedValue(true);
                $elem->setUncheckedValue(false);
            } else if (substr($term[2],0,6) == 'SELECT') {
                $options = $this->retrieve($term[2]);
                $elem = new Element\Select($term[0]);
                $elem->setLabel($term[1]);
				$elem->setValueOptions($options);
                $elem->setEmptyOption('with_selected');
                $elem->setAttribute('size', '1');
            } else if (substr($term[2],0,7) == 'varchar') {
                $elem = new Element\Text($term[0]);
                $elem->setLabel($term[1]);
                $len = strpos($term[2],')') - strpos($term[2],'(');
                $size = substr($term[2], strpos($term[2],'(')+1, $len-1);
                $size = $size > 38 ? 38 : $size;
                $elem->setAttribute('size', $size);
            } else if ($term[2] == 'year') {
                $elem = new Element\Number($term[0]);
                $elem->setLabel($term[1]);
                $elem->setValue(date('Y'));
                $elem->setAttributes(['size' => 5, 'min'  => '1100',
                    'max' => date('Y')+10, 'step' => '1']);
                $elem->setAttribute('style', 'width: 5em;');
            } else if ($term[2] == 'date') {
                $elem = new Element\Date($term[0]);
                $elem->setLabel($term[1]);
            } else if ($term[2] == 'email') {
                $elem = new Element\Email($term[0]);
                $elem->setLabel($term[1]);
            } else if ($term[2] == 'file') {
                $elem = new Element\File($term[0]);
                $elem->setLabel($term[1])
                    ->setAttribute('id', $term[0])
                    ->setAttribute('multiple', true);
            }
            // if (!empty($elem)) {
                $this->add($elem);
            // }
        }
        return $max;
    }

    // public function setInputFilter(InputFilter\InputFilterInterface $if)
    // {
    //     throw new \Exception("Not amused");
    // }

    public function getInputFilter() : InputFilterInterface
    {
		if ($this->inputFilter) {
			return $this->inputFilter;
		} else {
            $this->inputFilter = new InputFilter\InputFilter();
        }

        $page = $this->get('spec:page')->getValue(); // isValid
        foreach($this->terms as $term) {
            if ($term[2]=='file') {
                // $test = new InputFilter\FileInput($term[0]);
                // $test->setRequired(true);
                // $this->inputFilter->add($test);
            } else if ($term[3]==$page) {
                $req = empty($term[4]) ? false : $term[4]===' ';
                $req = $term[2]=='box' ? true : $req;
                $req = $req ? !$this->admin : $req;
                // if ($req) error_log('validate '.$term[0]
                //     .' page '.$this->page.': ' .$page);
                $this->inputFilter->add(['name' => $term[0], 'required' => $req]);
            } else {
                $this->inputFilter->add(['name' => $term[0], 'required' => false]);
            }
        }
        return $this->inputFilter;
    }

    private function getStorage() {
        if (empty($this->storage)) {
		    $this->storage = new DataStorage($this->db, $this->publish);
        }
        return $this->storage;
    }

    private function getIndexer() {
        if (empty($this->indexer)) {
            $params['publish'] = $this->publish;
            $params['solr'] = $this->solr;
		    $this->indexer = new DbibIndex($this->db, $params);
        }
        return $this->indexer;
    }

    private function retrieve($query) {
        $result = $this->db->query($query)->execute();
        $data = [];
        for ($i=0; $i<$result->count(); $result->next(), $i++) {
            $row = array_values($result->current());
            if (count($row)==2) {
                $data[$row[0]] = $row[1];
            } else if (count($row)==1) {
                $data[$row[0]] = $row[0];
            } else {
                $this->log('Unexpected: ' . $query);
            }
        }
        return $data;
    }

    // Find uid - url part behind server url
    private function createUID($oid, $uid) {
        if (empty($oid)) {
           $this->log('Unexpected createUID [' . $oid . ']');
        } else if (empty($uid) || $uid=='neu') {
           $uid = $this->files[0]['uid'] ?? 'dbib/'.date('Y').'/'.$oid;
           $this->log('GH2022-10 createUID one [' . $uid . '] '.$oid);
        } else if (substr_count($uid,'/')==2) {
           $this->log('GH2022-10 createUID two [' . $uid . '] '.$oid);
           $this->get('opus:uid')->setAttribute('readonly', 'true');
        } else {
           $this->log('GH2022-10 createUID three [' . $uid . '] '.$oid);
           $uid = null;
        }
        return $uid;
    }

    private function log(String $msg) {
        error_log('MetaForm: ' . $msg);
    }

}
