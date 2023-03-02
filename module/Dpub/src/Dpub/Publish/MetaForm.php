<?php
/**
 * VuFind Publishing
 * 
 * PHP version 7
 *
 * Copyright (C) Abstract Power 2018.
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
 * @package  Publish
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 *
 */

/**
 * @title Form Field and Metadata processing 
 * @date 2023-02-23
 * @created 2016-12-22
 */
namespace Dpub\Publish;

use Laminas\Mail;
use Laminas\Form\Element;
use Laminas\Form\Form;
use Laminas\InputFilter;
use Laminas\Form\Fieldset;
use Laminas\Http\Client;
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
    var $files; // array of files
    var $end;
    var $autobib; // rest url
    var $solrcore; // rest url
    var $title;
    var $mailto;
    var $mailfrom;
    var $umail;
    var $admin;
    var $storage;
    var $indexer;
	var $domain;
	var $inputFilter;
	var $urn_prefix;
	var $doi_prefix;
    var $dev; // development support

    public function __construct($name = null, $params = [])
    {
        parent::__construct($name, $params);

        $this->title = 'Publication Information';
        $this->db = $params['db'];
        $this->domain = $params['domain'];

        $this->mailfrom = $params['mailfrom'];
        $this->mailto = $params['mailto'];
        $this->umail = isset($params['usermail']) ? $params['usermail']
                     : false;

        $this->page = new Element\Hidden('page');
        $this->add($this->page);
        $this->admin = $params['admin'];
        $this->page->setValue(1);
        $this->autobib = $params['autobib'];
        $this->solrcore = $params['solrcore'];
		$this->setAttribute('enctype', 'multipart/form-data');
        // $this->fview = $params['fview'];
        $this->urn_prefix = $params['urn_prefix'];
        $this->doi_prefix = $params['doi_prefix'];
        $this->dev = true;
        // $this->add(array( 'name' => 'phone', 'type' => 'Phone',))
        // $this->inputFilter = InputFilter();
    }

    public function create($data = []) {
        $dpub = new MetaTerms();
        $this->terms = $dpub->create($data, $this->domain, $this->admin, $this->umail);
        $this->end = $this->addElements();
        $lng = empty($data['dcterms:language'])?'eng':$data['dcterms:language'];
        $this->addLanguage($lng);
        $this->files = isset($data['opus:files']) ? $data['opus:files'] : [];
        if (count($this->files)>$this->get('opus:filecount')->getValue()) {
            $this->get('opus:filecount')->setValue(count($this->files));
        }

        return $this;
    }

    /** process form data and return true if form is finished */
    public function process($post) 
    {
        if (!isset($post['page'])) {
            // Do not process foreign forms
            $this->log("empty page request");
            return false;
        } else if ($post['page']==0 && isset($post['dcterms:type'])) {
            $this->log(" Zero page request type ".$post['dcterms:type']);
        } else if ($post['page']==0) {
            $this->log(" Zero page request - no type ");
        } else if ($post['page']==1 && !empty($this->umail) 
            && empty($this->admin)) {
            $this->log('Upload by ' . $this->umail);
        } else {
            // error_log('page '.$post['page'].'/'.$this->end);
        }

        $this->setData($post);
        $this->setBasicTerms();
        $page = $this->page->getValue();

        if ($this->isValid() && empty($post['back'])) {
			$this->page->setValue(++$page);
        } else if (empty($post['back'])) {
            // nothing
        } else if ($post['back']=='back') {
		    $this->page->setValue(--$page);
        } else if ($page==1 && !empty($post['back'])) {
            return true;
        } else {
		    $this->log("MetaForm: invalid");
		}

        if ($page==0) {
            return true;
		} else if ($page-1 == $this->end && isset($post['publish'])) {
            $oid = $post['opus:source_opus'];
            $uid = $post['opus:uid'];
            if (empty($this->admin)) {
                // Nothing
            } else if (empty($uid)) {
                $this->log(' publish one [' . $uid .'] '. $oid);
                $this->files = $this->getStorage()->copy($post);
            } else {
                $this->log(' publish two [' . $uid .'] '. $oid);
                $this->files = $this->getStorage()->copy($post);
                if (substr_count($uid,'/')==2) {
                    $this->getIndexer()->index($oid);
                } else {
                    $this->log('Invalid uid, no index update');
                }
            }
            return true;
		} else if ($page-1 == $this->end && isset($post['callnum'])) {
            $oid = $post['opus:source_opus'];
            $uid = $this->createUID($oid, $post['opus:uid']);
            $this->get('opus:uid')->setValue($uid);
		    $this->page->setValue(--$page);
            return false;
		} else if ($page > $this->end) {
            return true;
		} else if ($page == $this->end && isset($post['delete'])) {
            $oid = $post['dcterms:identifier'];
		    $res = $this->getStorage()->delete($post['opus:source_opus']);
            if (empty($oid)) {
                $oid = 'dpub:'.$post['opus:source_opus'];
                $this->getIndexer()->delete($oid); 
                $this->log('delete ['.$oid.']');
            } else {
                $this->getIndexer()->delete($oid); 
            } 
            return true;
		} else if ($page == $this->end && isset($post['submit'])) {
		    $res = $this->getStorage()->save($post);
            $oid = $post['opus:source_opus'];
            if ($res==1) {
                $this->sendmail($post);
                $this->log("Submitted ".$oid.' # '.$res);
            } else if ($res==0 && empty($oid)) {
                // skip
            } else if ($res==0 && !is_numeric($oid)) {
                $this->log(" Bad id ".$oid.' # '.$res);
            } else {
                $this->log(" Save ".$oid.' # '.$res);
            }
            return false;
		} else if (!empty($post['opus:fileadmin'])) {
            $this->files = $this->getStorage()->rename($post['opus:filename'],
                           $post['opus:fileadmin'], $this->files);
			$this->page->setValue('1');
		} else if (empty($post['opus:file']) || empty($post['opus:file'][0])) {
            // Nothing
		} else if (!empty($post['opus:file'][0]['size'])) {
            // file upload -- save file and set oid as a side effect
            $oid = $post['opus:source_opus'];
            $file = $this->getStorage()->saveFile($post['opus:file'], $oid);
            $this->get('opus:source_opus')->setValue($oid);
            if (empty($file)) {
                //
            } else if (empty($file['path'])) {
                $this->log('Bad File submit '.$oid.' -- '.count($this->files));
            } else {
                $this->files[] = $file;
                $this->log('Submit ' . $oid . ' ' . $file['path']);
            }
		    if (count($this->files) < $post['opus:filecount']) {
			    $this->page->setValue('1'); 
		    } 
            if (count($this->files) > $post['opus:filecount']) {
                $this->get('opus:filecount')->setValue(count($this->files));
            }
            return false;
		} else {
            $this->log("GH2022-05 page ".$post['page'].'/'.$this->end);
            return false;
		}
    }

    /** By Controller: use data from record as provided by driver */
    public function read($oid) {
        // error_log('MetaForm read ' . $oid);
        $data = [];
        if (empty($oid)) {
            // Nothing
        } else {
		    $data = $this->getStorage()->read($oid);
            // Spec: enable UID creation with opus dpub, not temp or urn
            if (substr($oid,1,2) == 'pu' && empty($data['opus:uid'])) {
                $data['opus:uid'] = $data['opus:status'] ?? 'neu'; 
                $data['opus:source_opus'] = substr($oid,5);
                // $this->log('enable UID ['.$data['opus:uid'].']');
            }
        }
        if (empty($data)) {
            // Controller may read from elsewehere
        } else if (empty($data['opus:email'])) {
            $data['opus:email'] = $this->umail;
        }
        return $data;
    }

    private function sendmail($data) {
        $message = "Ein neues Dokument mit\n\n"
		         . "Archiv-ID: " . $data['opus:source_opus'] .PHP_EOL
		         . "Titel: " . $data['dcterms:title'] .PHP_EOL
                 . "Autor: " . $data['dcterms:creator0'] .PHP_EOL
                 . "Email: " . $data['opus:email'] .PHP_EOL
		         // . "IP: " . $_SERVER['REMOTE_ADDR'] .PHP_EOL
		         . "\nwurde in OPUS eingebracht.\n";
        $mail = new Mail\Message();
        $mail->setEncoding('utf-8');
        $mail->setFrom($this->mailfrom, "Admin Archiv");
        foreach(explode(',', $this->mailto) as $to) {
            $this->log('Sending mail to '.$to);
            $mail->addTo(trim($to));
        }
        $mail->setSubject('neue Publikation: '.$data['dcterms:creator0']);
        $mail->setBody($message);
        try {
		    //$transport = new Mail\Transport\Sendmail();
		    $transport = new Mail\Transport\Smtp();
            $transport->send($mail);
        } catch (\Exception $ex){
            error_log("failed to send mail: " . $ex->getMessage());
        }
    }

    private function addElements() {
        $max = 0;
        foreach($this->terms as $term) {
            if ($term[3] > $max) {
                $max = $term[3];
            }
            if (is_array($term[2]) && isset($term[2][0])) {
                $options = $this->retrieve($term[2][1]);
                $elem = new Element\Select($term[0]);
                $elem->setLabel($term[1]);
				$elem->setValueOptions($options);
                if (count($options)>1) {
                    $elem->setEmptyOption($term[2][0]);
                } else if (count($options)==1 && empty($term[4])) {
                    $elem->setEmptyOption('with_selected'); // not mandatory
                } else if (empty($options)) {
                    $elem->setEmptyOption('with_selected');
                }
				if ($term[0]=='dcterms:type') {
                    // show login information if type changes
                    $elem->setAttribute('onchange', '$(this).next().show()');
                    // $elem->setAttribute('id', 'dcterms:type');
				    $elem->setValue(8);
                } else if ($term[0]=='aiiso:faculty') {
				    // $elem->setValue(20);
                } else if ($term[0]=='dcterms:license') {
				    $elem->setValue('cc_by-nc-sa');
                } else if ($term[0]=='dcterms:language') {
				    $elem->setValue('ger');
                }
                $this->add($elem);
            } else if (is_array($term[2])) {
                $elem = new Element\Select($term[0]);
                $elem->setLabel($term[1]);
				$elem->setValueOptions($term[2]);
                $this->add($elem);
            } else if ($term[2] == 'file') { // Single File Input
                $elem = new Element\File($term[0]);
                $elem->setLabel($this->translate($term[1]))
                     ->setAttribute('id', $term[0])
                     ->setAttribute('multiple', true);
                $this->add($elem);
            } else if ($term[2] == 'Xfile') { // Multiple File Input
                $file = new Element\File('attachment');
                $file->setLabel($term[1])
                     ->setAttribute('id', $term[0])
                     ->setAttribute('multiple', true);
                $elem = new Element\Collection($term[0]);
				$elem->setOptions([ 'count' => 3, 
				    'allow_add' => true, 'allow_remove' => true,
					'target_element' => $file]);
                $elem->setLabel($term[1]);
                $this->add($elem);
            } else if ($term[2] == 'checkbox') { // Checkbox
                $elem = new Element\Checkbox($term[0]);
                $elem->setLabel($term[1]);
                $elem->setCheckedValue(true);
                $elem->setUncheckedValue(false);
                $this->add($elem);
            } else if ($term[2] == 'text') {
                $elem = new Element\Text($term[0]);
                $elem->setLabel($term[1]);
                $elem->setAttribute('size', '45');
                $this->add($elem);
            } else if ($term[2] == 'text2') {
                $elem = new Element\Text($term[0]);
                $elem->setLabel($term[1]);
                $elem->setAttribute('size', '16');
                $this->add($elem);
            } else if ($term[2] == 'subject') {
                $elem = new Element\Textarea($term[0]);
                $elem->setLabel($term[1]);
                $elem->setAttribute('size', '37');
                $elem->setAttribute('cols', $this->admin?'70':'63');
                $elem->setAttribute('rows', '2');
                $this->add($elem);
			} else if ($term[2] == 'date' && $term[0] == 'dcterms:created') {
                $elem = new Element\Number($term[0]);
                $elem->setLabel($term[1]);
                $elem->setValue(date('Y')); // TODO : test this
                $this->add($elem);
            } else if ($term[2] == 'date') {
                $elem = new Element\Date($term[0]);
                $elem->setLabel($term[1]);
                $this->add($elem);
            } else if ($term[2] == 'area') {
                $elem = new Element\Textarea($term[0]);
                $elem->setLabel($term[1]);
				if (strpos($term[0], 'abstract')) {
                    $elem->setAttribute('cols', '90');
                    $elem->setAttribute('rows', '10');
				} else {
                    $elem->setAttribute('cols', '70');
                    $elem->setAttribute('rows', '2');
				}
                $this->add($elem);
            } else if ($term[2] == 'email') {
                $elem = new Element\Email($term[0]);
                $elem->setLabel($term[1]);
                if (!empty($this->umail)) {
                    $elem->setValue($this->umail);
                }
                $elem->setAttribute('size', '45');
                $this->add($elem);
            } else if ($term[2] == 'year') {
                $elem = new Element\Number($term[0]);
                $elem->setLabel($term[1]);
                $elem->setValue(date('Y'));
                $elem->setAttributes(['min'  => '1100', 
                                      'max' => date('Y')+10, 'step' => '1']);
                //$elem->setAttribute('size', '4');
                $this->add($elem);
            } else if ($term[2] == 'number' && $term[0]=='opus:filecount') {
                $elem = new Element\Number($term[0]);
                $elem->setLabel($term[1]);
                $elem->setValue($this->admin ? 0 : 1);
				$elem->setAttributes(['min' => '0', 'step' => '1']);
                $elem->setAttribute('size', '1');
                $elem->setAttribute('style', 'width: 4em;');
                $this->add($elem);
            } else if ($term[2] == 'number') {
                $elem = new Element\Number($term[0]);
                $elem->setLabel($term[1]);
                $elem->setValue(1);
				$elem->setAttributes(array('min'  => '1', 'step' => '1'));
                $elem->setAttribute('size', '5');
                $this->add($elem);
            } else if ($term[2] == 'collection') {
                // $elem = new Element\Collection($term[0]);
                $elem = new Element\Checkbox($term[0]);
                $elem->setOptions(['checked_value' => $term[1],
                     'unchecked_value' => $term[0], 
                     'use_hidden_element' => true]);
                $elem->setLabel($term[1]);
                $elem->setValue($term[1]);
                $this->add($elem);
            }
        }
        return $max;
    }

    private function addLanguage($lang) {
        $row = $this->retrieve('SELECT code, sprache'
			   . ' from language where code="'.$lang.'"');
        $lang1 = empty($row[$lang])?$lang:$row[$lang];
        $lang2 = $lang=='eng'?'German':'English';
        $this->get('dcterms:title')->setAttribute('lang', $lang1);
        $this->get('dcterms:alternative')->setAttribute('lang', $lang2);
        $this->get('dcterms:abstract')->setAttribute('lang', $lang1);
        $this->get('opus:abstract2')->setAttribute('lang', $lang2);
    }

    private function setBasicTerms() {
        $lang1 = $this->get('dcterms:language')->getValue();
        $lang2 = $lang1=='eng'?'ger':'eng';
        //$this->log('basic terms lang1 ' . $lang1 . ' lang2 ' . $lang2);
        //if (empty($this->get('opus:language')->getValue())) {
		    $this->get('opus:language')->setValue($lang2);
        //}
        $ddc = $this->get('opus:ddc')->getValue();
        $fac = $this->get('aiiso:faculty')->getValue();
        // $this->log("setBasicTerms " . $ddc . " " . $fac);
        if (empty($ddc) && !empty($fac)) {
            $sql = 'SELECT nr, ddc from faculty where nr="'.$fac. '"';
            $res = $this->retrieve($sql);
            if (count($res)==1) {
                // $this->log("setting ddc " . array_values($res)[0]);
                $this->get('opus:ddc')->setValue(array_values($res)[0]);
            }
        }
    }

    public function setInputFilter(InputFilter\InputFilterInterface $if)
    {
        throw new \Exception("Not used");
    }

    public function getInputFilter() : InputFilterInterface
    {
		if ($this->inputFilter) {
            // error_log("MetaForm filter exists " . $this->page->getValue());
			return $this->inputFilter;
		}
        $inputFilter = new InputFilter\InputFilter();

        foreach($this->terms as $term) {
            if (empty($term[4])) {
                //
            } else if ($term[2]=='file' && $this->page->getValue()==$term[3]) {
                // $filecount = $this->get('opus:filecount')->getValue();
                // $page = $this->page->getValue();
                // $count = count($this->files);
                // error_log("filter ".$filecount." page ".$page." ".$count);
			    $file = new InputFilter\FileInput($term[0]);
                if ($this->get('opus:filecount')->getValue() > count($this->files)) {
			        $file->setRequired($term[4]);
                    //error_log("file " . $this->get('opus:filecount')->getValue());
                } else {
			        $file->setRequired(false);
                }
			    // $file->getFilterChain()->attachByName( 'filerenameupload',
				//     [ 'target'          => './data/tmpuploads/',
				// 	     'overwrite'       => false,
				// 	     'use_upload_name' => false,
				//     ]
			    // );
                //$format = ['text/plain' => 'txt'];
                $format = $this->retrieve("SELECT mime_type, extension from format where diss_format=1");
                $mimes = array_keys($format);
                // ['text/plain', 'image/png', 'enableHeaderCheck' => true,];
                $extensions = array_values($format);
                //$mimes['enableHeaderCheck'] = true;
                // this fails with php 5.6
			    // $file->getValidatorChain()->attach(new Validator\File\MimeType($mimes));
			    $file->getValidatorChain()->attach(new Validator\File\Extension($extensions));
			    $inputFilter->add($file); 
            } else if ($term[3]==$this->page->getValue() && $term[2]=='date') {
                $date = new InputFilter\Input($term[0]);
				$date->setRequired($term[4]);
                $date->getValidatorChain()->attach(
                  new Validator\Date(['format' => 'Y-m-d', 'messages' => 
                  [Validator\Date::INVALID_DATE => 
                  'Invalid date format, use Y-m-d']]));
                $inputFilter->add($date);
            } else if ($term[3]==$this->page->getValue()) {
                if ($term[0]=='dcterms:creator0') {
                    if (empty($this->get('opus:creator_corporate')->getValue()))
                    {   // Require author if no corporate creator exists
                        $inputFilter->add(
                            ['name' => $term[0], 'required' => $term[4]]);
                    }
                } else if (str_starts_with($term[0], 'dcterms:creator')) {
                    // Do not require secondary authors
                } else {
                    $inputFilter->add(['name' => $term[0], 'required' => $term[4]]);
                }
            }
        }

		$this->inputFilter = $inputFilter;
        return $this->inputFilter;
    }

    private function getStorage() {
        if (empty($this->storage)) {
            $params = [ 'urn_prefix' => $this->urn_prefix,
                        'doi_prefix' => $this->doi_prefix ];
		    $this->storage = new DataStorage($this->db, $this->domain, $params);
        }
        return $this->storage;
    }

    private function getIndexer() {
        if (empty($this->indexer)) {
            $params['domain'] = $this->domain; 
            $params['solrcore'] = $this->solrcore;
            $sql = 'SELECT concat(path,base) base from opus_domain where id='
                . $this->domain;
            // $data = $this->retrieve($sql);
            // error_log(implode($data));
            $params['base'] = implode($this->retrieve($sql));
            // var_dump($params['base']);
            // error_log($params['base']);
		    $this->indexer = new DpubIndex($this->db, $params);
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
           $this->log('GH2022-10 createUID one [' . $uid . '] '.$oid);
           if (empty($this->files)) {
               $uid = 'opus/'.date('Y').'/'.$oid;
           } else {
               $uid = $this->files[0]['url'];
               $xxx = strlen($uid)-strlen($this->files[0]['name'])-1;
               $uid = substr($uid, 0, $xxx);
               $uid = substr($uid, strpos($uid,'opus'));
           }
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
        if ($this->dev) {
            error_log('MetaForm: ' . $msg);
        }
    }

}
