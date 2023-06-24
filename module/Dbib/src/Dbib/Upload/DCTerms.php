<?php
/**
 * VuFind Publishing
 * 
 * PHP version 8
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

namespace Dbib\Upload;

class DCTerms {

    var $terms;

    public function create($data = [], $admin = false) {
        if (empty($this->terms)) {
            $lang = $data['opus:language'] ?? '';
            $lang2 = empty($lang) ? '' : ($lang=='eng' ? 'ger' : 'eng');
            $type = $data['opus:typeid'] ?? '0';
            $fid = $data['faculty:nr'] ?? '0';
            $this->createTerms($lang, $lang2, $type, $fid, $admin);
        }
        return $this->terms;
    }

    // table:column label type page help
    private function createTerms($lang, $lang2, $type, $fid, $admin) {
        $this->terms = [
            ['spec:page', 'End Page', 'int', 0],
            ['opus:source_opus', 'Identifier', 'int', 0],
            ['opus_files:name', 'File Description', 'collection', 0],
            ['opus_publications:uid', 'Access URL', 'varchar(20)', 0],
            ['opus_publications:urn', 'URN', 'varchar(40)', 0],
            ['opus_domain:url', 'URL', 'varchar(255)', 0],
            ['opus:date_modified', 'Modification Date', 'date', 0],

            ['opus:type', 'Document Type',
                'SELECT typeid type, type label FROM resource_type', 1, ' '],
            ['faculty:nr', 'Faculty',
                'SELECT nr, name from faculty order by name', 1],
            ['opus:verification', 'Email Address', 'email', 1],
            ['opus:language', 'Language',
                'SELECT code, sprache from language', 1],
            ['opus:lic', 'License',
                'SELECT shortname, longname from license order by sort', 1],
            ['spec:box', 'Publisher Permissions', 'box', ($admin ? 0 : 1), 
                'Dbib::accept'],
            ['spec:dsgvo', 'Dbib::DSGVO', 'box', ($admin ? 0 : 1), 
                'Dbib::dsgvo'],
            ['spec:file', 'Dbib::Files', 'file', 1, 'Dbib::files'],

            ['opus:title', 'Title', 'text', 2, ' '],
            ['opus:title_alt', 'Transliterated Title', 'text', 2, $lang2],
            ['opus_autor:creator_name', 'Authors', 'collection', 2],
            ['opus_autor:authorid', 'Identifier', 'collection', 2, 'Dbib::authorid'],
            ['opus:date_year', 'Created',  'year', 2],
            ['opus:date_creation', 'Publication Date', 'date', 2],
            // ['opus:date_modified', 'Modification Date', 'date', 2],
            // ['opus:date_valid', 'Return Date', 'date', 2],
            ['opus_diss:date_accepted', 'Dbib::Accepted', 'date', 
                $type=='8' ? 2 : 0],
            ['opus_diss:advisor', 'CreatorRoles::dgs', 'text', 2],
            ['opus:creator_corporate', 'Corporate Author', 'varchar(150)', 2, 'Dbib::creator_corporate'],
            ['opus:contributors_name', 'CreatorRoles::org', 'varchar(335)', 2, 'Dbib::contributors_name'],
            ['opus:contributors_corporate', 'Corporate Authors', 'varchar(150)', 2, 'Dbib::contributors_corporate'],
            ['opus_inst:inst_nr', 'Institute', 'SELECT nr as inst_nr, name'
                .' from institute where faculty="'.$fid.'"', 2],
            ['opus:publisher_university', 'Publisher', 
                'SELECT universitaet, universitaet from university', 2],

            ['opus:sachgruppe_ddc', 'browse_dewey', 
                'SELECT nr, sachgruppe_en from sachgruppe_ddc', 3, '<br/>'],
            ['opus:description', 'Summary', 'text', 3, $lang],
            ['opus:description2', 'Summary', 'text', 3, $lang2],
            ['opus:subject_swd', 'Subject Terms', 'text', 3],
            ['opus:subject_uncontrolled_german', 'Topics', 'text', 3, $lang],
            ['opus:subject_uncontrolled_english', 'Topics', 'text',3, $lang2],

            ['opus:source_title', 'Source', 'text', 4, '<br/>'],
            ['opus:isbn', 'ISBN/ISSN', 'varchar(30)', 4],
            ['schriftenreihen:sr_id', 'Serial', 'SELECT sr_id'
                .', left(name, 88) from schriftenreihen order by name', 4],
            ['opus_schriftenreihe:sequence_nr', 'Volume', 'varchar(11)', 4],
            ['opus_coll:coll_id', 'Collection', 'SELECT coll_id'
                .', left(coll_name, 88) from collections order by coll_name',
                ($admin ? 4 : 0)],
            // ['opus:domain', 'Range', 'SELECT id, name '
            //  .'from opus_domain where domain='.$this->domain.' order by id',
            //   3, true, ['Range', 'opus:status', 'ref2']],
            ['opus:status', 'Status', 'varchar(20)', ($admin ? 4 : 0)],

            ['opus_links:tag', 'URL', 'SELECT distinct tag, name from '
                .' opus_links where tag!="iiif"', ($admin ? 4 : 0)],
            ['opus_links:link', 'Link', 'url', ($admin ? 4 : 0)],
            // ['opus_links:name', 'URL', 'url', ($admin ? 4 : 0)], 

            //['opus:physical', 'Physical Description', 'text', 0, false],
            //['opus:research', null, 'text', 3, false, 'Research data'],
            //['opus:bem_extern', 'Comments', 'text', 0],
            //['opus:source_swb', 'Comments', 'text', 0],
            //['opus:dini_publtype', 'Comments', 'text', 0],
            //['opus:source_swb', 'Comments', 'text', 0],
            ['opus:bem_intern', 'Notes', 'text', 4, 'Staff View'],

        ];
    }

}

