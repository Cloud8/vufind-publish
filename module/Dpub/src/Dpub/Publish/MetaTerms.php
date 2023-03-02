<?php
/**
 * Dpub Publishing
 * 
 * PHP version 7
 *
 * Copyright (C) Abstract Power 2018 - 2021.
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
 * @category Dpub VF
 * @package  Publish
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 *
 */

namespace Dpub\Publish;

class MetaTerms {

    var $terms;
    var $domain;

    public function create($data = [], $domain, $admin = false, $umail = null) {
        $this->domain = $domain;
        if (empty($this->terms)) {
            $this->createTerms();
        }
        $this->process($data, $admin, $umail);
        return $this->terms;
    }

    // 0:name 1:label 2:type 3:page 4:required 5:text
    // text2 is smaller than text
    // text col 5 : ref renders link to content page as text
    //            : ref2 renders form element as second column
    //            : term[5][3] is used as id attribute
    private function createTerms() {
        $this->terms = [
            ['dcterms:identifier', 'Identifier', 'text', 0, false, null],
            ['dcterms:type', 'Document Type', ['with_selected', 
               'SELECT typeid, type FROM resource_type'
               . ' where typeid<10 or typeid>14'], 
             1, true, // null], // Skip Journal types -- used by OJS
             ['Dpub::Login', 'login', 'ref', 'upload']],
            ['aiiso:faculty', 'Faculty', ['with_selected', 
             'SELECT nr, name from faculty order by name'], 1, true, null],
            ['opus:number', 'Number of Authors', 'number', 1, true, null],
            ['opus:email', 'Email Address', 'email', 1, true, null],
            ['dcterms:language', 'Language', ['with_selected', 
             'SELECT code, sprache from language'], 1, true, 
             'Dpub::Language of work'],
            ['opus:language', 'Language', 
              ['eng' => 'English', 'ger' => 'German'], 0, false, null],
            ['dcterms:license', 'License', ['with_selected', 
             'SELECT shortname, longname from license order by sort'], 1, true, null],
            ['opus:file', 'Dpub::Files', 'file', 1, true, null],
            ['opus:filecount', 'Dpub::filecount', 'number', 1, false, null],
            // ['Dpub::filesubmit', 'files', 'ref']], // see metadata.phtml

            ['dcterms:title', 'Title', 'area', 2, true,
             'Dpub::Original title of work', null],
            ['dcterms:alternative', 'Transliterated Title', 'area', 2, false,
             'Dpub::Transliterated title of work', null],
            ['separator', '<hr><br>', 'separator', 2, false],
            // array of authors / authorids
            ['dcterms:creator', 'CreatorRoles::aut', 'text', 2, true,
              ['Dpub::Last Name, First Name' , 'opus:authorid', 'ref3']],
            ['opus:authorid', 'Dpub::AuthorId', 'text2', 2, false, 
             ['Dpub::AuthorId', 'authorid', 'ref']],
            ['opus:advisor', 'CreatorRoles::dgs', 'text', 2, false,
              'Dpub::Last Name, First Name (Title)'],
            ['dcterms:contributor', 'CreatorRoles::Beiträger', 'text', 2, false,
              'Dpub::Last Name, First Name (Title)'],
            ['opus:contributors_corporate', 'Contributors', 'text', 2, false, 
             'Dpub::Contributor'],
            ['opus:creator_corporate', 'CreatorRoles::org', 'text', 2, false, 
             'CreatorRoles::isb'],
            ['separator', '<hr><br>', 'separator', 2, false],
            ['aiiso:institute', 'Institute', 
             ['with_selected', 'SELECT nr, name from institute'], 2, true, null],
            ['dcterms:subject', 'Subject Terms', 'subject',  2, false,
             'Kontrollierte Schlagwörter (Deutsch)'],
            ['opus:subject', 'Topics', 'area',  2, false,
             'Freie Schlagwörter (Deutsch)'],
            ['opus:subject2', 'Topics', 'area',  2, false,
             'Freie Schlagwörter (Englisch)'],
            ['opus:ddc', 'browse_dewey',
              ['with_selected', 'SELECT nr, sachgruppe_en from sachgruppe_ddc'],
              2, true, null],
            ['dcterms:publisher', 'Institution', ['with_selected', 
             'SELECT universitaet, universitaet from university'], 2, true],
            ['dcterms:created', 'Created',  'year', 2, true,
             'Dpub::Date created'],
            ['dcterms:issued', 'Publication Date', 'date', 2, false,
             'Dpub::Date published'],
            ['dcterms:dateAccepted', 'Accepted', 'date', 2, false,
             'Dpub::Date accepted'],
            ['opus:isbn', 'ISBN / ISSN', 'text', 2, false, 'Dpub::ISBN', null],
            ['dcterms:source', 'Source', 'text', 2, false,'Dpub::Other Source'],

            ['dcterms:abstract', 'Summary', 'area', 3, false, null],
            ['opus:abstract2', 'Summary', 'area', 3, false, null],
            ['separator', '<hr><br>', 'separator', 3, false, null],
            ['opus:serial', 'Serial', ['with_selected', 
             'SELECT sr_id, left(name,111) from schriftenreihen order by name'],
             3, false, ['Serial', 'opus:volume', 'ref2']],
            ['opus:volume', 'Volume', 'text2', 3, false],
            ['opus:collection', 'Collection', ['with_selected', 'SELECT coll_id, '
              .'left(coll_name,111) from collections order by coll_name'],
             3, false, null],
            ['opus:domain', 'Range', ['with_selected', 'SELECT id, name '
              . 'from opus_domain where domain='.$this->domain.' order by id'],
              3, true, ['Range', 'opus:status', 'ref2']],
            ['opus:status', 'Status', 'text2', 3, false],

            ['opus:physical', 'Physical Description', 'text', 0, false],
            ['separator', '<hr><br>', 'separator', 3, false, null],

            ['separator', 'More options', 'separator', 3, false, null],
            ['opus:details', null, 'text', 3, false, 'Bibliographic Details'],
            ['opus:research', null, 'text', 3, false, 'Research data'],
            ['opus:comment', null, 'text', 3, false, 'ill_request_comments'],

            ['separator', '<hr><br>', 'separator', 3, false],
            ['opus:note', 'Notes', 'area', 3, false, 'Staff View'],

            ['opus:box', 'Dpub::Permissions', 'checkbox', 3, true,
             ['Dpub::accept', 'license', 'ref']],
            ['opus:dsgvo', 'Dpub::DSGVO', 'checkbox', 3, true,
             ['Dpub::DSGVO', 'dsgvo', 'ref']],
            ['opus:msg', 
             'Das Dokument und die Metadaten wurden erfolgreich übermittelt.'
             ."\n".
             'Das Dokument wird zwischengespeichert und nach Abschluss des'
             ."\n".'Prüfungsverfahrens veröffentlicht.', 'message', 5],
            ['opus:source_opus', null, 'text', 0, false, 'identifier'],
            ['opus:source_swb', null, 'text', 0, false, 'PPN'],
            ['opus:uid', 'URL', 'text', 0, false, 'Access URL'],
        ];
    }

    private function process($data, $admin, $umail) {
        // error_log($data['opus:status'].' # '.$data['opus:uid'].': '.$admin);
        // $coll_nr = 0;
        $type = empty($data['dcterms:type']) ? '8' : $data['dcterms:type'];
        for($k=0; $k<count($this->terms); ++$k) {
            if ($this->terms[$k][0]=='dcterms:type' && !empty($umail)) {
                 // error_log('Upload by ' . $umail);
                 $this->terms[$k][5] = null; // erase message
            } else if ($this->terms[$k][0]=='aiiso:institute' 
                && !empty($data['aiiso:faculty'])) {
                // constrain institutes based on faculty
                $this->terms[$k][2][1] = 'SELECT nr, name from institute'
                . ' where faculty=' . $data['aiiso:faculty']
                . ' ORDER BY NAME';
            } else if ($this->terms[$k][0]=='dcterms:creator' && !empty($data['opus:number'])) {
                for ($i = 0; $i < $data['opus:number']; ++$i) {
                    $term_[] = [$this->terms[$k][0] . $i, $this->terms[$k][1],
                        $this->terms[$k][2], $this->terms[$k][3],
                        $this->terms[$k][4], $this->terms[$k][5]];
                } // insert new array terms at first merge position
                if (!empty($term_)) {
                    unset($this->terms[$k]);
                    array_splice($this->terms, $k, 0, $term_);
                }
            } else if ($this->terms[$k][0]=='opus:authorid' 
                && !empty($data['opus:number'])) {
                for ($i = 0; $i < $data['opus:number']; ++$i) {
                    $authorid[] = [ $this->terms[$k][0] . $i, $this->terms[$k][1],
                        $this->terms[$k][2], $this->terms[$k][3],
                        $this->terms[$k][4], $this->terms[$k][5]];
                } // insert new array terms at first merge position
                if (!empty($authorid)) {
                    unset($this->terms[$k]);
                    array_splice($this->terms, $k, 0, $authorid);
                }
            } else if ($this->terms[$k][0]=='opus:advisor') {
                if ($type=='8' || $type=='7' || $type=='25') {
                    // nothing
                } else {
                    $this->terms[$k][3]=0; // suppress element
                }
            } else if ($this->terms[$k][0]=='dcterms:contributor') {
                if ($type=='8' || $type=='7' || $type=='25') {
                    $this->terms[$k][3]=0; // suppress element
                }
            } else if ($this->terms[$k][0]=='opus:contributors_corporate') {
                if ($type=='8' || $type=='7' || $type=='25') {
                    $this->terms[$k][3]=0; // suppress element
                }
            } else if ($this->terms[$k][0]=='opus:creator_corporate') {
                if ($type=='8' || $type=='7' || $type=='25') {
                    $this->terms[$k][3]=0; // suppress element
                }
            } else if ($this->terms[$k][0]=='dcterms:dateAccepted') {
                if ($type=='8' || $type=='7' || $type=='24' || $type=='25') {
                    // nothing
                } else {
                    $this->terms[$k][3] = 0; // suppress element
                }
            } else if ($this->terms[$k][0]=='opus:box' && $admin) {
                $this->terms[$k][3] = 0;    // suppress element
            } else if ($this->terms[$k][0]=='opus:file' && $admin) {
                unset($this->terms[$k][5]); // suppress info text
            } else if ($this->terms[$k][0]=='separator' 
                && $this->terms[$k][1]=='More options' && empty($admin)) {
                    $this->terms[$k][3] = 0; // hide element
            } else if ($this->terms[$k][0]=='opus:options' && empty($admin)) {
                if (empty($data['opus:details'])) {
                    $this->terms[$k][3] = 0; // hide element
                }
            } else if ($this->terms[$k][0]=='opus:details' && empty($admin)) {
                if (empty($data['opus:details'])) {
                    $this->terms[$k][3] = 0; // hide element
                }
            } else if ($this->terms[$k][0]=='opus:research' && empty($admin)) {
                if (empty($data['opus:research'])) {
                    $this->terms[$k][3] = 0; // hide element
                }
            } else if ($this->terms[$k][0]=='opus:comment' && empty($admin)) {
                if (empty($data['opus:comment'])) {
                    $this->terms[$k][3] = 0; // hide element
                }
            } else if ($this->terms[$k][0]=='opus:secondary' && empty($admin)) {
                if (empty($data['opus:secondary'])) {
                    $this->terms[$k][3] = 0; // hide element
                }
            } else if ($this->terms[$k][0]=='opus:msg' && $admin) {
                if (isset($data['delete'])) {
                    $this->terms[$k][1] = 'Dpub::Data deleted';
                } else {
                    $this->terms[$k][1] = 'Dpub::Data saved';
                }
            } else if ($this->terms[$k][0]=='dcterms:license' && $admin) {
                $this->terms[$k][5] = ''; // suppress user note
            // } else if ($this->terms[$k][0]=='opus:email' && $admin) {
            //     $this->terms[$k][5] = null; // suppress dsgvo checkbox
            } else if ($this->terms[$k][0]=='opus:dsgvo' && $admin) {
                $this->terms[$k][3] = 0; // suppress dsgvo check
                // $this->terms[$k][4] = false; // suppress dsgvo check
            } else if ($this->terms[$k][0]=='opus:domain' && !$admin) {
                $this->terms[$k][3] = 0;  // suppress element
            } else if ($this->terms[$k][0]=='opus:status' && !$admin) {
                $this->terms[$k][3] = 0;  // suppress element
            } else if ($this->terms[$k][0]=='dcterms:issued' && !$admin) {
                $this->terms[$k][3] = 0;  // suppress element
            } else if ($this->terms[$k][0]=='opus:uid' && $admin) { 
                if (empty($data['opus:uid'])) {
                    // Nothing
                } else {
                    $this->terms[$k][3] = 5;  // enable element
                }
            } else if ($this->terms[$k][0]=='opus:subject') { 
                if (empty($data['opus:subject'])) {
                    //
                } else if (mb_strlen($data['opus:subject']) > 150) {
                    // handled by Storage
                    error_log('opus:subject: ' . strlen($data['opus:subject'])
                    . ' [' . $data['opus:subject'] . '] data too long.'); 
                }
            } else if ($this->terms[$k][0]=='opus:subject2') { 
                if (empty($data['opus:subject2'])) {
                    //
                } else if (mb_strlen($data['opus:subject2']) > 150) {
                    // handled by Storage
                    error_log('opus:subject2: '.strlen($data['opus:subject2'])
                    . ' [' . $data['opus:subject'] . '] data too long.'); 
                }
            } else if ($this->terms[$k][0]=='opus:collection') { 
                // $coll_nr = $k;
            }
        }

        // GH2021-04-23 recreate collections
        // Either create elements from given data or loop through post
        // $colls = [];
        if (empty($data['collections'])) {
            foreach ($data as $key => $val) {
                if (strpos($key, 'opus:coll_')!==FALSE) {
                    // error_log('GH2021-04-26 MetaTerms ['.$key.':'.$val.']');
                    $this->terms[] = [$key, $val, 'collection', 3, false, null];
                }
            } 
        } else foreach($data['collections'] as $key => $val) {
            // error_log('MetaTerm coll ['.$key.' --> '.$val.']');
            // $this->terms[] = [$key, $val, 'collection', 3, false, null];
            $this->terms[] = [$key, $val, 'collection', 3, false];
        }
        // $result = array_splice($this->terms, 0, $coll_nr, true) + $colls
        // + array_splice($this->terms, 0, count($this->terms) - $coll_nr, true);
        // $this->terms = $result;
        $this->terms[] = ['separator', '<hr><br>', 'separator', 3, false, null];
    }
}

