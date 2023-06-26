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
 * @date 2023-06-23
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
    var $files; 
    var $title;
    var $admin;
    var $mailfrom;

    var $solr;
    var $storage;
    var $indexer;
	var $inputFilter;
	var $publish;
    var $colls;

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
        $this->colls = ['opus_autor', 'opus_coll', 'opus_links', 'opus_files'];
    }

    public function create($post = []) {
        $this->terms = (new DCTerms())->create($post, $this->admin);
        $this->page = empty($post['spec:page']) ? 1 : (int)$post['spec:page'];
        $this->end = $this->addElements($this->page) + 1;
        $this->setData($post);

        if (empty($post['submit']) && empty($post['back'])) {
            $this->log('zero page ' . $this->page);
        } else if (empty($post['submit'])) {
            $this->page = max(1, $this->page-1);
        } else if ($this->isValid()) {
            $this->page++;
        }
        $this->replay($this->page);
        $this->setData($post);
        $this->get('spec:page')->setValue($this->page);

        $time = $post['opus:date_modified'] ?? date('Y-m-d');
        $this->files = $this->getFiles($post['opus_files:name']?:[], $time);
        return $this;
    }

    /** process form data and return true if form is finished */
    public function process($post) 
    {
        if (!isset($post['spec:page'])) {
            $this->log("empty page request");
            return false; // Do not process strange things
        } else if ($this->page > $this->end) {
            return true;
        } else if ($this->page==0) {
            return false;
        } else if ($this->page==1 && $this->admin) {
            // $this->log('Upload by ' . $post['opus:verification']);
        } else {
            // $this->log('process page '.$this->page.'/'.$this->end);
        }

        $oid = $post['opus:source_opus'] ?: 0;
		if (isset($post['publish'])) {
            if (empty($this->admin)) {
                // 
            } else {
                $upd = $this->getStorage()->copy($post);
                $this->log('publish ' . $upd);
                if ($upd==3) {
                    $url = $post['opus_domain:url'];
                    $this->getIndexer()->index($oid, $url);
                }
            }
            return true;
		} else if (isset($post['delete'])) {
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
            if ($res==1 && empty($oid)) {
                $this->sendmail($post);
                $this->log("Submitted ".$oid.' # '.$res);
            } else if ($res==2) {
                //
            } else if ($res==3) {
                $this->log(" Save ".$oid.' : '.$res);
            } else {
                $this->log(" Bad save ".$oid.' # '.$res);
            }
            return false;
		} else if (!empty($post['opus:fileadmin'])) {
            $this->files = $this->getStorage()->rename($post['opus:filename'],
                           $post['opus:fileadmin'], $this->files);
	    } else if (!empty($post['spec:file'][0]['name'])) {
            // file upload -- save file and set oid as a side effect
            // var_dump($post['spec:file']);
            // $oid = $post['opus:source_opus'];
            $uid = $post['opus_publications:uid'];
            $files = $this->getStorage()->saveFile($post['spec:file'], $uid);
            $this->files = array_merge($this->files, $files);
            foreach($files as $file) {
                $name = 'opus_files:name'.(count($this->files)+1);
                $elem = new Element\Hidden($name);
                $elem->setValue($file['name']);
                $this->get('opus_files:name')->add($elem);
                $this->log('create ' . $name);
            }
            $this->log('Submit ' . $uid . ' ' . count($this->files));
            // $this->get('opus:source_opus')->setValue($oid);
            // var_dump($this->files);
            return false;
		} else {
            // $this->log('page '.$this->page.'/'.$this->end);
            return false;
		}
    }

    /** By Controller: use data from record as provided by driver */
    public function read($oid) {
		$data = $this->getStorage()->read($oid, $this->colls);

        // remap some values
        $data['opus:type'] = $data['opus:typeid'] ?? 0;
        unset($data['opus:typeid']);
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

        return $data;
    }

    private function save($data) {
        // remap some values 
        $data['opus:date_creation'] = strtotime($data['opus:date_creation']);
        $data['opus_diss:date_accepted'] = strtotime($data['opus_diss:date_accepted']);
        $data['opus:date_modified'] = strtotime($data['opus:date_modified']);
	    $res = $this->getStorage()->write($this->terms, $data);
        return $res;
    }

    private function sendmail($data) {
        $message = "Ein neues Dokument mit\n\n"
		         // . "Archiv-ID: " . $data['opus:source_opus'] .PHP_EOL
		         . "Titel: " . $data['opus:title'] .PHP_EOL
                 //. "Autor: " . $data['dcterms:creator0'] .PHP_EOL
                 . "Email: " . $data['opus:verification'] .PHP_EOL
		         // . "IP: " . $_SERVER['REMOTE_ADDR'] .PHP_EOL
		         . "\nwurde in das Publikationssystem eingestellt.\n";
        $mail = new Mail\Message();
        $mail->setEncoding('utf-8');
        $mail->setFrom($this->mailfrom, "Admin Archiv");
        foreach(explode(',', $this->publish['mailto']) as $to) {
            $this->log('Sending mail to '.$to);
            $mail->addTo(trim($to));
        }
        $mail->setSubject('neue Publikation: '.$data['opus:title']);
        $mail->setBody($message);
        try {
		    //$transport = new Mail\Transport\Sendmail();
		    $transport = new Mail\Transport\Smtp();
            $transport->send($mail);
        } catch (\Exception $ex){
            $this->log("failed to send mail: " . $ex->getMessage());
        }
    }

    private function replay($page = 0) {
        foreach($this->terms as $term) {
            $tab = substr($term[0], 0, strpos($term[0], ':'));
            if (in_array($tab, $this->colls)) {
                $count = $this->get($term[0])->getCount();
                $this->remove($term[0]);
                $elem = $this->getCollection($page, $term);
                $elem->setCount($count);
                $this->add($elem);
            }
        }
    }

    private function getCollection($page = 0, $term) {
        $elem = new Element\Collection($term[0]);
        if ($page == $term[3]) {
            if ($term[2] == 'collection') {
                $elem->setLabel($this->translate($term[1]));
                $elem->setTargetElement(new Element\Text());
            } else if (substr($term[2],0,6) == 'SELECT') {
                $options = $this->retrieve($term[2]);
                $sub = new Element\Select();
				$sub->setValueOptions($options);
                $sub->setEmptyOption('with_selected');
                $sub->setAttribute('size', '1');
                // $sub->setLabel('');
                // $elem->setLabel($term[1]);
                // $elem->setTargetElement($sub);
                $elem->setTargetElement($sub);
            } else if ($term[2] == 'url') {
                // $elem->setTargetElement(new Element\Url());
                $elem->setTargetElement(new Element\Text());
                $elem->setLabel($term[1]);
                // $elem->setAttribute('size', '299');
                // $elem->setAttribute('width', '100%');
            }
        } else {
            $elem->setTargetElement(new Element\Hidden());
        }
        $elem->setOptions([ 'count' => 1, 'allow_add' => true,
            'allow_remove' => true, 'should_create_template' => true,
        ]);
        // $elem->prepareFieldset();
        return $elem;
    }

    private function addElements($page = 0) {
        $max = 0;
        foreach($this->terms as $term) {
            $max = $term[3] > $max ? $term[3] : $max;
            $tab = substr($term[0], 0, strpos($term[0], ':'));
            $elem = null;
            if ($term[2] == 'file') {
                $elem = new Element\File($term[0]);
                $elem->setLabel($term[1])
                    ->setAttribute('id', $term[0])
                    ->setAttribute('multiple', true);
            } else if (in_array($tab, $this->colls)) {
                $elem = $this->getCollection($page, $term);
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
            } else if ($term[2] == 'url') {
                $elem = new Element\Url($term[0]);
                $elem->setLabel($term[1]);
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
            }
            if (!empty($elem)) {
                $this->add($elem);
            }
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

        foreach($this->terms as $term) {
            if ($term[2]=='file') {
                // $test = new InputFilter\FileInput($term[0]);
                // $test->setRequired(true);
                // $this->inputFilter->add($test);
            } else if ($term[3]==$this->page) {
                $req = empty($term[4]) ? false : $term[4]===' ';
                $req = $term[2]=='box' ? true : $req;
                // $req = $req ? !$this->admin : $req;
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

    private function getFiles($names, $time) {
        $files = [];
        foreach($names as $file) {
            $files[] = ['name' => $file, 'time' => $time];
        }
        return $files;
    }

    private function log(String $msg) {
        error_log('MetaForm: ' . $msg);
    }

}
