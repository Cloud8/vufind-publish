<?xml version="1.0" encoding="UTF-8"?>

<xsl:stylesheet
     xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
     xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
     xmlns:dcterms="http://purl.org/dc/terms/"
     xmlns:dctypes="http://purl.org/dc/dcmitype/"
     xmlns:fabio="http://purl.org/spar/fabio/"
     xmlns:foaf="http://xmlns.com/foaf/0.1/"
     xmlns:aiiso="http://purl.org/vocab/aiiso/schema#"
     xmlns:skos="http://www.w3.org/2004/02/skos/core#"
     xmlns:void="http://rdfs.org/ns/void#"
     xmlns:sco="http://schema.org/"
     version="1.0" >

<!-- 
    @license http://www.apache.org/licenses/LICENSE-2.0
    @title Dpub to RDF Transformer 
    @date 2023-03-01
    @created 2015-11-01
-->

<xsl:output method="xml" indent="yes" encoding="UTF-8" />
<xsl:strip-space elements="*"/>

<xsl:param name="graph" 
  select="document/resultset[@table='opus_domain']/row[1]/field[@name='url']"/>

<xsl:template match="/">
  <xsl:apply-templates select="document" />
</xsl:template>

<!-- ITEM -->
<xsl:template match="document[count(resultset[@table='opus'])=1]">
    <rdf:RDF>
        <xsl:comment> Dpub2RDF Transformer (2023) </xsl:comment>
        <xsl:apply-templates select="resultset[@table='opus_publications']" />
    </rdf:RDF>
</xsl:template>

<!-- SERIES / COLLECTIONS -->
<xsl:template match="document[count(resultset[@table='opus'])=0]">
    <rdf:RDF>
        <xsl:apply-templates select="resultset[@table='schriftenreihen']/row"/>
        <xsl:apply-templates select="resultset[@table='collections']/row"/>
    </rdf:RDF>
</xsl:template>

<!-- 16 IDENTIFIER -->
<xsl:template match="resultset[@table='opus_publications']">
    <xsl:variable name="uri"><xsl:value-of select="concat(
            ../resultset[@table='opus_domain']/row/field[@name='url'], '/',
            row/field[@name='uid'])"/>
    </xsl:variable>
    <dcterms:BibliographicResource rdf:about="{$uri}">
        <xsl:apply-templates select="row/*"/>
        <xsl:apply-templates select="../resultset[@table='opus']">
            <xsl:with-param name="uri" select="$uri"/>
        </xsl:apply-templates>
        <xsl:apply-templates select="../resultset[@table='opus_autor']">
            <xsl:with-param name="uri" select="$uri"/>
        </xsl:apply-templates>
        <xsl:apply-templates select="../resultset[@table='opus_domain']/row"/>
        <xsl:apply-templates select="../resultset[@table='opus_toc']"/>
        <xsl:apply-templates select="../resultset[@table='opus_references']"/>
        <xsl:apply-templates select="../resultset[@table='opus_citations']"/>
        <xsl:apply-templates select="../resultset[@table='schriftenreihen']"/>
        <xsl:apply-templates select="../resultset[@table='collections']"/>
        <xsl:apply-templates select="../resultset[@table='opus_links']"/>
    </dcterms:BibliographicResource>
</xsl:template>

<xsl:template match="resultset[@table='opus']">
    <xsl:param name="uri"/>
    <xsl:apply-templates select="row">
        <xsl:with-param name="uri" select="$uri"/>
    </xsl:apply-templates>
</xsl:template>

<!-- Different from Agent URI -->
<xsl:template match="resultset[@table='opus_domain']/row">
  <dcterms:mediator>
      <dcterms:Agent rdf:about="{$graph}">
      <foaf:name><xsl:value-of select="field[@name='instname']"/></foaf:name>
      <rdfs:label><xsl:value-of select="field[@name='longname']"/></rdfs:label>
    </dcterms:Agent>
  </dcterms:mediator>
  <void:inDataset rdf:resource="{concat($graph,'/about.rdf')}"/>
</xsl:template>

<xsl:template match="resultset[@table='opus']/row">
  <xsl:param name="uri"/>
  <!-- 1 TITLE -->
  <xsl:apply-templates select="field[@name='title']" />
  <xsl:apply-templates select="field[@name='title_alt']" />
  <!-- 3 AUTHOR : separated -->
  <!-- 4 TOPIC DDC -->
  <xsl:apply-templates select="field[@name='sachgruppe_ddc']" />
  <!-- 4 TOPIC SWD -->
  <xsl:apply-templates select="field[@name='subject_swd']" />
  <!-- 4 TOPIC Klassifikationen -->
  <xsl:apply-templates select="../../resultset[@table='opus_ccs']"/>
  <xsl:apply-templates select="../../resultset[@table='opus_pacs']"/>
  <xsl:apply-templates select="../../resultset[@table='opus_msc']"/>
  <xsl:apply-templates select="../../resultset[@table='opus_jel']"/>
  <!-- 4 TOPIC noScheme -->
  <xsl:apply-templates select="field[@name='subject_uncontrolled_german']" />
  <xsl:apply-templates select="field[@name='subject_uncontrolled_english']" />
  <!-- 6 DESCRIPTION -->
  <xsl:apply-templates select="field[@name='description']" />
  <xsl:apply-templates select="field[@name='description2']" />
  <!-- 7 PUBLISHER -->
  <xsl:apply-templates select="field[@name='publisher_university']" />
  <xsl:apply-templates select="field[@name='publisher_faculty']" />
  <xsl:apply-templates select="field[@name='inst_name']" />
  <!-- 8 CONTRIBUTOR -->
  <xsl:apply-templates select="field[@name='advisor']"/>
  <xsl:apply-templates select="field[@name='contributors_name']"/>
  <xsl:apply-templates select="field[@name='contributors_corporate']"/>
  <!-- 9 CREATOR -->
  <xsl:apply-templates select="field[@name='creator_corporate']"/>
  <!-- 11 DATE ACCEPTED -->
  <xsl:apply-templates select="field[@name='date_accepted']"/>
  <!-- 12 DATE CREATED -->
  <xsl:apply-templates select="field[@name='date_creation']"/>
  <!-- 12 DATE MODIFIED -->
  <xsl:apply-templates select="field[@name='date_modified']"/>
  <!-- DATE YEAR -->
  <xsl:apply-templates select="field[@name='date_year']"/>
  <!-- 14 TYPE -->
  <xsl:apply-templates select="field[@name='type']"/>
  <xsl:apply-templates select="field[@name='dini_publtype']"/>
  <!-- 18 MEDIUM -->
  <!-- <xsl:apply-templates select="field[@name='medium']"/> -->
  <!-- 20 SOURCE -->
  <xsl:apply-templates select="field[@name='isbn']"/>
  <!-- 21 LANGUAGE -->
  <xsl:apply-templates select="field[@name='language']"/>
  <!-- GH2022-12 : see title@xml:lang
  <xsl:apply-templates select="field[@name='iso_639_1']"/>
  <xsl:apply-templates select="field[@name='iso_639_2']"/>
  -->
  <!-- 29 PART OF -->
  <xsl:apply-templates select="field[@name='source_title']"/>
  <xsl:apply-templates select="field[@name='source_swb']"/>
  <!-- 40 RIGHTS -->
  <xsl:apply-templates select="field[@name='license']" />
  <xsl:apply-templates select="field[@name='accessRights']" />
  <!-- subtitles Kirche und Welt -->
  <xsl:apply-templates select="field[@name='subtitle']"/>
  <!-- opac:physical corvey:manuscript -->
  <xsl:apply-templates select="field[@name='bem_extern']"/>
  <xsl:apply-templates select="../../resultset[@table='opus_files']">
      <xsl:with-param name="uri" select="$uri"/>
  </xsl:apply-templates>
</xsl:template>

<xsl:template match="field[@name='oid']">
    <dcterms:identifier>
        <xsl:value-of select="concat('dpub:',.)"/>
    </dcterms:identifier>
</xsl:template>

<!-- 1. TITLE -->
<xsl:template match="field[@name='title']">
    <dcterms:title xml:lang="{../field[@name='iso_639_1']}">
        <xsl:value-of select="normalize-space(.)"/>
    </dcterms:title>
</xsl:template>

<xsl:template match="field[@name='title_alt']">
    <dcterms:alternative xml:lang="{../field[@name='iso_lang2']}">
        <xsl:value-of select="normalize-space(.)"/>
    </dcterms:alternative>
</xsl:template>

<!-- 3. AUTHOR -->
<xsl:template match="field[@name='creator_name']">
  <foaf:Person>
      <xsl:choose>
          <xsl:when test="../field[@name='orcid']!=''">
              <xsl:attribute name="rdf:about">
                  <xsl:value-of select="concat('https://orcid.org/',
                      ../field[@name='orcid'])"/>
              </xsl:attribute>
          </xsl:when>
          <xsl:when test="../field[@name='gnd']!=''">
              <xsl:attribute name="rdf:about">
                  <xsl:value-of select="concat('https://d-nb.info/gnd/',
                      ../field[@name='gnd'])"/>
              </xsl:attribute>
          </xsl:when>
      </xsl:choose>
      <xsl:call-template name="creator-names">
        <xsl:with-param name="text"><xsl:value-of select="."/></xsl:with-param>
      </xsl:call-template>
  </foaf:Person>
</xsl:template>

<xsl:template name="string-generic">
 <xsl:param name="text" select="."/>
 <xsl:value-of select="translate($text,
  translate($text, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyzüäö', ''),
  '')"/>
</xsl:template>

<!-- creator names separated ; comes within table schriftenreihen only -->
<xsl:template name="creator-names">
 <xsl:param name="text"/>
 <xsl:choose>
  <xsl:when test="contains($text,'(add)')">
      <xsl:element name="foaf:name">
          <xsl:value-of select="substring-before($text,'(add)')"/>
      </xsl:element>
      <xsl:element name="foaf:role"><!-- author_additional -->
          <xsl:value-of select="'add'"/>
      </xsl:element>
  </xsl:when>
  <xsl:when test="contains($text,', ')">
      <foaf:givenName>
       <xsl:value-of select="normalize-space(substring-after($text,','))"/>
      </foaf:givenName>
      <foaf:familyName>
        <xsl:value-of select="normalize-space(substring-before($text,','))"/>
      </foaf:familyName>
      <foaf:name><xsl:value-of select="normalize-space($text)"/></foaf:name>
      <!-- 
      <xsl:comment>
           <xsl:value-of select="../field[@name='reihenfolge']"/>
      </xsl:comment>
      -->
  </xsl:when>
  <xsl:otherwise>
     <foaf:name><xsl:value-of select="normalize-space($text)"/></foaf:name>
  </xsl:otherwise>
 </xsl:choose>
</xsl:template>

<!-- 4. DDC TOPIC -->
<xsl:template match="field[@name='sachgruppe_ddc']">
 <dcterms:subject>
  <skos:Concept rdf:about="http://dewey.info/class/{.}">
   <skos:prefLabel xml:lang="de">
     <xsl:value-of select="normalize-space(../field[@name='sachgruppe'])"/>
   </skos:prefLabel>
   <skos:prefLabel xml:lang="en">
     <xsl:value-of select="normalize-space(../field[@name='sachgruppe_en'])"/>
   </skos:prefLabel>
  </skos:Concept>
 </dcterms:subject>
</xsl:template>

<!-- 4. SWD TOPIC 2016 -->
<xsl:template match="field[@name='subject_swd'][text()!='']">
  <xsl:call-template name="swd2skos">
     <xsl:with-param name="text"><xsl:value-of select="."/></xsl:with-param>
     <xsl:with-param name="sep">
     <xsl:choose><xsl:when test="contains(text(),' , ')">
         <xsl:value-of select="' , '"/></xsl:when>
         <xsl:when test="contains(text(),';')">
           <xsl:value-of select="'; '"/></xsl:when>
       <xsl:otherwise><xsl:value-of select="', '"/></xsl:otherwise>
     </xsl:choose>
    </xsl:with-param>
  </xsl:call-template>
</xsl:template>

<!-- 4. TOPIC UNCONTROLLED 2016 -->
<xsl:template match="field[@name='subject_uncontrolled_german'][text()!='']">
  <xsl:call-template name="unc2skos">
     <xsl:with-param name="text"><xsl:value-of select="."/></xsl:with-param>
     <xsl:with-param name="lang"><xsl:value-of select="'de'"/></xsl:with-param>
  </xsl:call-template>
</xsl:template>

<xsl:template match="field[@name='subject_uncontrolled_english'][text()!='']">
  <xsl:call-template name="unc2skos">
     <xsl:with-param name="text"><xsl:value-of select="."/></xsl:with-param>
     <xsl:with-param name="lang"><xsl:value-of select="'en'"/></xsl:with-param>
  </xsl:call-template>
</xsl:template>

<!-- Klassifikation:CCS -->
<xsl:template match="resultset[@table='opus_ccs']">
    <xsl:apply-templates select="row"/>
</xsl:template>

<xsl:template match="resultset[@table='opus_ccs']/row">
  <dcterms:subject>
  <skos:Concept rdf:about="https://www.acm.org/CCS/{field[@name='class']}">
      <rdf:value><xsl:value-of select="field[@name='bez']"/></rdf:value>
  </skos:Concept>
  </dcterms:subject>
</xsl:template>

<!-- Klassifikation:MSC -->
<xsl:template match="resultset[@table='opus_msc']">
    <xsl:apply-templates select="row"/>
</xsl:template>

<xsl:template match="resultset[@table='opus_msc']/row">
  <dcterms:subject>
  <skos:Concept rdf:about="http://www.iwi-iuk.org/MSC/{field[@name='class']}">
      <rdf:value><xsl:value-of select="field[@name='bez']"/></rdf:value>
  </skos:Concept>
  </dcterms:subject>
</xsl:template>

<!-- Klassifikation:JEL -->
<xsl:template match="resultset[@table='opus_jel']">
    <xsl:apply-templates select="row"/>
</xsl:template>

<xsl:template match="resultset[@table='opus_jel']/row">
  <dcterms:subject>
  <skos:Concept rdf:about="https://www.aeaweb.org/JEL/{field[@name='class']}">
      <rdf:value><xsl:value-of select="field[@name='bez']"/></rdf:value>
  </skos:Concept>
  </dcterms:subject>
</xsl:template>

<!-- Klassifikation:PACS -->
<xsl:template match="resultset[@table='opus_pacs']">
  <xsl:apply-templates select="row"/>
</xsl:template>

<xsl:template match="resultset[@table='opus_pacs']/row">
  <dcterms:subject>
  <skos:Concept rdf:about="http://publish.aps.org/PACS/{field[@name='class']}">
      <rdf:value><xsl:value-of select="field[@name='bez']"/></rdf:value>
  </skos:Concept>
  </dcterms:subject>
</xsl:template>

<!-- corvey:manuscript: iiif desc item -->
<xsl:template match="resultset[@table='opus_links']/row">
    <xsl:apply-templates select="field[@name='tag'][text()='iiif']"/>
    <xsl:apply-templates select="field[@name='tag'][text()='desc']"/>
    <xsl:apply-templates select="field[@name='tag'][text()='item']"/>
</xsl:template>

<!-- opus:links to external resources -->
<xsl:template match="resultset[@table='opus_links']/row/field[@name='tag'][text()='iiif']">
    <dcterms:hasFormat>
        <dctypes:Text rdf:about="{../field[@name='link']}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'application/json'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
        </dctypes:Text>
    </dcterms:hasFormat>
</xsl:template>

<!-- opus:links to external description -->
<xsl:template match="resultset[@table='opus_links']/row/field[@name='tag'][text()='desc']">
    <xsl:variable name="desc" select="substring-before(substring-after(../../../resultset[@table='opus']/row/field[@name='description'],'Ausführliche Beschreibung:'),'-')"/> 
    <xsl:variable name="link" select="../field[@name='link']"/> 
    <dcterms:isReferencedBy>
        <dcterms:BibliographicResource>
            <xsl:choose>
                <xsl:when test="string-length($desc)>9">
                    <dcterms:title>
                        <xsl:value-of select="normalize-space($desc)"/>
                    </dcterms:title>
                </xsl:when>
                <xsl:otherwise>
                    <dcterms:title>
                        <xsl:value-of select="$link"/>
                    </dcterms:title>
                </xsl:otherwise>
            </xsl:choose>
            <xsl:choose>
                <xsl:when test="starts-with($link,'/')">
                    <dcterms:relation>
                        <xsl:value-of select="$link"/>
                    </dcterms:relation>
                </xsl:when>
                <xsl:when test="starts-with($link,'http')">
                    <dcterms:relation>
                        <xsl:value-of select="$link"/>
                    </dcterms:relation>
                </xsl:when>
                <xsl:otherwise></xsl:otherwise>
            </xsl:choose>
        </dcterms:BibliographicResource>
    </dcterms:isReferencedBy>
</xsl:template>

<!-- opus:links to external item -->
<xsl:template match="resultset[@table='opus_links']/row/field[@name='tag'][text()='item']">
    <dcterms:relation>
        <dcterms:BibliographicResource rdf:about="{../field[@name='link']}">
            <dcterms:title>
                <xsl:value-of select="../field[@name='name']"/>
            </dcterms:title>
        </dcterms:BibliographicResource>
    </dcterms:relation>
</xsl:template>

<!-- 6 DESCRIPTION ABSTRACT -->
<xsl:template match="field[@name='description']">
    <dcterms:abstract>
        <xsl:if test="count(../field[@name='iso_639_1'])=1">
            <xsl:attribute name="xml:lang">
                <xsl:value-of select="../field[@name='iso_639_1']"/>
            </xsl:attribute>
        </xsl:if>
        <xsl:value-of select="."/>
    </dcterms:abstract>

 <xsl:if test="contains(text(),'Erschienen:')"> 
     <xsl:choose>
     <xsl:when test="contains(substring-after(.,'Erschienen:'),' -')"> 
         <dcterms:date><xsl:value-of 
           select="substring-before(substring-after(.,'Erschienen:'),' -')"/>
         </dcterms:date>
     </xsl:when>
     <xsl:when test="contains(substring-after(.,'Erschienen:'),'  ')"> 
         <dcterms:date><xsl:value-of select="normalize-space(substring-before(
                 substring-after(.,'Erschienen:'),'  '))"/>
         </dcterms:date>
     </xsl:when>
     </xsl:choose>
 </xsl:if>
 <xsl:if test="contains(text(),'Signatur:')"> 
     <xsl:variable name="signatur" select="substring-after(.,'Signatur:')"/>
     <xsl:choose>
     <xsl:when test="contains($signatur,' -')"> 
         <xsl:if test="string-length(substring-before($signatur,'-')) &lt; 25"> 
             <dcterms:isVersionOf>
                 <xsl:value-of select="substring-before($signatur,' -')"/>
             </dcterms:isVersionOf>
         </xsl:if>
     </xsl:when>
     <xsl:when test="contains($signatur,'  ')"> 
         <xsl:variable name="sig" select="substring-before($signatur,'  ')"/>
         <xsl:if test="string-length($sig)&gt;0 and string-length($sig)&lt;25">
             <dcterms:isVersionOf>
                 <xsl:value-of select="$sig"/>
             </dcterms:isVersionOf>
         </xsl:if>
     </xsl:when>
     </xsl:choose>
 </xsl:if>
</xsl:template>

<xsl:template match="field[@name='description2']">
    <xsl:if test="normalize-space(.)!=''">
        <dcterms:description xml:lang="{../field[@name='iso_lang2']}">
            <xsl:value-of select="."/>
        </dcterms:description>
    </xsl:if>
</xsl:template>

<!-- 7 PUBLISHER -->
<xsl:template match="field[@name='publisher_university']">
 <xsl:choose>
     <xsl:when test="../../../resultset[@table='schriftenreihen']/row/field[@name='uni_gnd']">
         <!-- container has publisher -->
     </xsl:when>
     <xsl:when test="../../../resultset[@table='opus_domain']/row/field[@name='uni_gnd']">
         <dcterms:publisher>
             <foaf:Organization rdf:about="{../../../resultset[@table='opus_domain']/row/field[@name='uni_gnd']}">
                 <foaf:name><xsl:value-of select="."/></foaf:name>
             </foaf:Organization>
         </dcterms:publisher>
     </xsl:when>
     <xsl:otherwise>
         <dcterms:publisher>
             <foaf:Organization>
                 <foaf:name><xsl:value-of select="."/></foaf:name>
             </foaf:Organization>
         </dcterms:publisher>
     </xsl:otherwise>
 </xsl:choose>
</xsl:template>

<!-- FAKULTAET -->
<xsl:template match="field[@name='publisher_faculty']">
 <xsl:if test="normalize-space(../field[@name='faculty_name'])!=''">
 <dcterms:contributor>
     <aiiso:Faculty rdf:about="{concat($graph},'/aut/',.)}">
     <foaf:name>
       <xsl:value-of select="normalize-space(../field[@name='faculty_name'])"/>
     </foaf:name>
   </aiiso:Faculty>
 </dcterms:contributor>
 </xsl:if>
</xsl:template>

<xsl:template match="field[@name='inst_name']">
 <xsl:variable name="iid">
  <xsl:call-template name="string-generic"/>
 </xsl:variable>
 <xsl:if test="normalize-space(.)!=''">
  <dcterms:contributor>
   <xsl:choose>
    <xsl:when test="contains(.,'Universitätsbibliothek')">
     <aiiso:Division rdf:about="http://d-nb.info/gnd/11210-0">
      <foaf:name><xsl:value-of select="'Universitätsbibliothek'" /></foaf:name>
     </aiiso:Division>
    </xsl:when>
    <xsl:when test="starts-with(.,'Center') or starts-with(.,'Zentrum')">
     <aiiso:Center rdf:about="{concat($graph, '/aut/', $iid)}">
      <foaf:name><xsl:value-of select="normalize-space(.)" /></foaf:name>
     </aiiso:Center>
    </xsl:when>
    <xsl:otherwise>
     <aiiso:Institute rdf:about="{concat($graph, '/aut/', $iid)}">
      <foaf:name><xsl:value-of select="normalize-space(.)" /></foaf:name>
     </aiiso:Institute>
    </xsl:otherwise>
   </xsl:choose>
  </dcterms:contributor>
 </xsl:if>
</xsl:template>

<!-- 8 CONTRIBUTOR -->
<xsl:template match="field[@name='advisor']">
 <xsl:if test="normalize-space(.)!=''">
  <dcterms:contributor>
    <foaf:Person>
        <foaf:name><xsl:value-of select="normalize-space(.)"/></foaf:name>
        <xsl:choose>
          <xsl:when test="contains(.,',')">
            <foaf:givenName><xsl:value-of select="normalize-space(substring-before(substring-after(.,','),'('))"/></foaf:givenName>
            <foaf:familyName><xsl:value-of select="normalize-space(substring-before(.,','))"/></foaf:familyName>
            <xsl:if test="contains(.,'(') and contains(.,')')">
                <foaf:title><xsl:value-of select="normalize-space(substring-before(substring-after(.,'('),')'))"/></foaf:title>
            </xsl:if>
          </xsl:when>
        </xsl:choose>
        <xsl:if test="contains(//resultset[@table='opus']/row/field[@name='type'],'DoctoralThesis')">
        <foaf:role><xsl:value-of select="'ths'"/></foaf:role>
        </xsl:if>
    </foaf:Person>
  </dcterms:contributor>
 </xsl:if>
</xsl:template>

<!-- contains semicolon as delimiter -->
<xsl:template match="field[@name='contributors_name']">
 <xsl:if test="normalize-space(.)!=''">
  <dcterms:contributor>
   <rdf:Seq>
    <xsl:call-template name="tokenize">
     <xsl:with-param name="text" select="normalize-space(.)"/>
    </xsl:call-template>
   </rdf:Seq>
  </dcterms:contributor>
 </xsl:if>
</xsl:template>

<xsl:template match="field[@name='contributors_corporate']">
 <xsl:if test="normalize-space(.)!=''">
  <xsl:variable name="aut">
      <xsl:call-template name="string-generic"/>
  </xsl:variable>
  <dcterms:contributor>
    <foaf:Organization rdf:about="{concat($graph, '/aut/', $aut)}">
        <foaf:name><xsl:value-of select="normalize-space(.)"/></foaf:name>
    </foaf:Organization>
  </dcterms:contributor>
 </xsl:if>
</xsl:template>

<xsl:template match="field[@name='creator_corporate']">
  <xsl:variable name="aut">
      <xsl:call-template name="string-generic"/>
  </xsl:variable>
  <xsl:choose>
      <xsl:when test="normalize-space(.)=''"></xsl:when>
      <!--
      <xsl:when test="count(../../resultset[@table='opus_autor'])=0">
          <dcterms:creator>
            <foaf:Organization rdf:about="{concat($graph, '/aut/', $aut)}">
            <foaf:name><xsl:value-of select="normalize-space(.)"/></foaf:name>
            </foaf:Organization> 
          </dcterms:creator>
      </xsl:when>
      -->
      <xsl:otherwise>
          <dcterms:contributor>
            <foaf:Organization rdf:about="{concat($graph, '/aut/', $aut)}">
            <foaf:name><xsl:value-of select="normalize-space(.)"/></foaf:name>
            </foaf:Organization> 
          </dcterms:contributor>
      </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<!-- DINI Publication types : additional type or change code -->
<xsl:template match="field[@name='dini_publtype']">
    <sco:additionalType><xsl:value-of select="."/></sco:additionalType>
</xsl:template>

<xsl:template match="field[@name='type']">
  <dcterms:type rdf:resource="{concat('http://purl.org/spar/fabio/',.)}"/>
</xsl:template>

<!-- DATE YEAR : Erstellungsdatum -->
<xsl:template match="field[@name='date_year']">
    <xsl:choose>
    <xsl:when test="contains(//resultset[@table='opus']/row/field[@name='description']/text(),'Erschienen:')"> 
         <dcterms:created>
             <xsl:value-of select="substring(../field[@name='date_creation'],1,4)"/>
         </dcterms:created>
    </xsl:when>
    <xsl:when test="starts-with(//resultset[@table='opus_publications']/row/field[@name='uid']/text(),'eb/')"> 
         <dcterms:date><xsl:value-of select="."/></dcterms:date>
         <dcterms:created>
             <xsl:value-of select="substring(../field[@name='date_creation'],1,4)"/>
         </dcterms:created>
    </xsl:when>
    <xsl:otherwise>
        <dcterms:created><xsl:value-of select="."/></dcterms:created>
    </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<!-- 11 DATE ACCEPTED : Datum der Promotion -->
<xsl:template match="field[@name='date_accepted']">
<xsl:if test=".!=''">
  <dcterms:dateAccepted><xsl:value-of select="."/></dcterms:dateAccepted>
</xsl:if>
</xsl:template>

<!-- 12 DATE ISSUED : Datum der Erstveroeffentlichung -->
<xsl:template match="field[@name='date_creation']">
<xsl:if test=".!=''">
  <dcterms:issued><xsl:value-of select="."/></dcterms:issued>
</xsl:if>
</xsl:template>

<!-- 13 DATE MODIFIED : Änderungsdatum des Dokuments -->
<xsl:template match="field[@name='date_modified']">
<xsl:if test=".!=''">
   <dcterms:modified><xsl:value-of select="."/></dcterms:modified>
</xsl:if>
</xsl:template>

<!-- 20 SOURCE -->
<xsl:template match="field[@name='isbn']">
  <xsl:choose>
  <xsl:when test="normalize-space(.)=''"></xsl:when>
  <xsl:when test="string-length(.)=9 and substring(.,5,1)='-'">
    <sco:issn><xsl:value-of select="."/></sco:issn>
  </xsl:when>
  <xsl:otherwise>
    <sco:isbn><xsl:value-of select="."/></sco:isbn>
  </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<!-- 21 LANGUAGE -->
<xsl:template match="field[@name='language']">
    <xsl:variable name="v1" select="'http://id.loc.gov/vocabulary/iso639-1/'"/>
    <xsl:variable name="v2" select="'http://id.loc.gov/vocabulary/iso639-2/'"/>
    <xsl:variable name="iso1" select="concat($v1,../field[@name='iso_639_1'])"/>
    <xsl:variable name="iso2" select="concat($v2,../field[@name='iso_639_2'])"/>
    <dcterms:language>
        <dcterms:LinguisticSystem rdf:about="{$iso2}">
            <rdf:value xml:lang="en"><xsl:value-of select="."/></rdf:value>
            <dcterms:hasVersion rdf:resource="{$iso1}"/>
        </dcterms:LinguisticSystem>
    </dcterms:language>
</xsl:template>

<!-- 29 SOURCE : no blank nodes -->
<xsl:template match="field[@name='source_title']">
  <xsl:choose>
      <!-- heyne links in source_title are deprectated since 2022-05-05 -->
      <xsl:when test="count(../../../resultset[@table='opus_links']/row/field[@name='tag'][text()='desc'])=1"></xsl:when>
  <xsl:otherwise>
    <dcterms:source><xsl:value-of select="normalize-space(.)"/></dcterms:source>
  </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<!-- PPN identifier -->
<xsl:template match="field[@name='source_swb'][not(contains(.,'-'))]">
    <dcterms:isFormatOf>
        <xsl:value-of select="concat('ppn:',.)"/>
    </dcterms:isFormatOf>
</xsl:template>

<!-- ZDB identifier -->
<xsl:template match="field[@name='source_swb'][contains(.,'-')]">
    <dcterms:isFormatOf>
        <xsl:value-of select="concat('zdb:',.)"/>
    </dcterms:isFormatOf>
</xsl:template>

<!-- 40 RIGHTS -->
<xsl:template match="field[@name='license']">
    <dcterms:license rdf:resource="{normalize-space(.)}"/>
</xsl:template>

<xsl:template match="field[@name='accessRights']">
    <xsl:choose> 
    <xsl:when test="starts-with(.,'/srv/archiv/')"></xsl:when>
    <xsl:when test="normalize-space(.)!=''">
        <dcterms:accessRights><xsl:value-of select="normalize-space(.)"/>
        </dcterms:accessRights>
    </xsl:when>
    </xsl:choose>
</xsl:template>

<!-- opac:physical -->
<!--
<xsl:template match="field[@name='bem_extern']">
    <dcterms:extent>
        <dcterms:SizeOrDuration>
            <rdf:value><xsl:value-of select="."/></rdf:value>
        </dcterms:SizeOrDuration>
    </dcterms:extent>
</xsl:template>
-->

<!-- GH2022-07-13 : Channel policy : may become extra column later -->
<xsl:template match="field[@name='bem_extern']">
    <dcterms:accrualPolicy>
        <xsl:value-of select="."/>
    </dcterms:accrualPolicy>
</xsl:template>

<xsl:template match="row/field[@name='doi'][text()!='']">
    <dcterms:identifier>
        <xsl:value-of select="concat('https://doi.org/',.)"/>
    </dcterms:identifier>
</xsl:template>

<!-- skip null values -->
<xsl:template match="field[@name='urn'][starts-with(text(),'urn:')]">
    <dcterms:identifier><xsl:value-of select="."/></dcterms:identifier>
</xsl:template>

<xsl:template match="resultset[@table='opus_publications']/row/field[@name='ppn'][text()!='']">
    <dcterms:identifier><xsl:value-of select="concat('ppn:',.)"/></dcterms:identifier>
</xsl:template>

<!-- default cover if no png image exists -->
<xsl:template match="resultset[@table='opus_files']">
  <xsl:param name="uri"/>
  <xsl:apply-templates select="row">
      <xsl:with-param name="uri" select="$uri"/>
  </xsl:apply-templates>
  <xsl:if test="count(row/field[@name='name'][contains(text(),'.png')])=0">
    <foaf:img><xsl:value-of select="concat(substring-after($uri,':'),'/cover.png')"/></foaf:img>
  </xsl:if>
</xsl:template>

<xsl:template match="resultset[@table='opus_files']/row">
  <xsl:param name="uri"/>
  <xsl:apply-templates select="field[@name='name']">
     <xsl:with-param name="uri" select="normalize-space($uri)"/>
  </xsl:apply-templates>
</xsl:template>

<!-- Create part or a sequence of jpg images -->
<xsl:template match="resultset[@table='opus_files']/row/field[@name='name']">
  <xsl:param name="uri"/>
  <xsl:choose>
   <xsl:when test="contains(.,'mets-')"><!-- viewer -->
       <dcterms:hasPart>
           <dctypes:Text rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'application/xml'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
           <xsl:apply-templates select="../field[@name='extent']"/>
       </dctypes:Text></dcterms:hasPart>
   </xsl:when>
   <xsl:when test="contains(.,'.zip')">
       <dcterms:hasPart>
           <dctypes:Dataset rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'application/zip'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
       </dctypes:Dataset></dcterms:hasPart>
   </xsl:when>
   <!-- GH2022-01 : Versions put to archive
   <xsl:when test="starts-with(.,'data/') and contains(.,'.pdf')">
       <dcterms:hasVersion>
           <dctypes:Text rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'application/pdf'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
           <xsl:apply-templates select="../field[@name='extent']"/>
       </dctypes:Text></dcterms:hasVersion>
   </xsl:when>
   -->
   <xsl:when test="contains(.,'.pdf')">
       <dcterms:hasPart>
           <dctypes:Text rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'application/pdf'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
           <xsl:apply-templates select="../field[@name='extent']"/>
       </dctypes:Text></dcterms:hasPart>
   </xsl:when>
   <xsl:when test="contains(.,'html')">
       <dcterms:hasPart>
           <dctypes:Text rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'text/html'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
       </dctypes:Text></dcterms:hasPart>
   </xsl:when>
   <xsl:when test="contains(.,'.mp4')">
       <dcterms:hasPart>
           <dctypes:MovingImage rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'video/mp4'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
           <xsl:apply-templates select="../field[@name='extent']"/>
       </dctypes:MovingImage></dcterms:hasPart>
   </xsl:when>
   <xsl:when test="contains(.,'.epub')">
     <dcterms:hasPart>
         <dctypes:Text rdf:about="{concat($uri,'/',normalize-space(.))}">
             <dcterms:format><dcterms:MediaTypeOrExtent><rdfs:label>
                 <xsl:value-of select="'application/epub+zip'"/></rdfs:label>
         </dcterms:MediaTypeOrExtent></dcterms:format>
         </dctypes:Text></dcterms:hasPart>
   </xsl:when>
   <xsl:when test="contains(.,'.png')">
     <foaf:img><xsl:value-of select="concat($uri,'/',.)"/></foaf:img>
     <dcterms:hasPart>
         <dctypes:Image rdf:about="{concat($uri,'/',normalize-space(.))}">
         <dcterms:format><dcterms:MediaTypeOrExtent>
         <rdfs:label><xsl:value-of select="'image/png'"/></rdfs:label>
         </dcterms:MediaTypeOrExtent></dcterms:format>
         </dctypes:Image></dcterms:hasPart>
   </xsl:when>
   <xsl:when test="contains(.,'.jpg')">
       <dcterms:hasPart>
       <rdf:Seq>
       <xsl:for-each select="../../row/field[@name='name'][contains(.,'.jpg')]">
       <rdf:li>
           <dctypes:Image rdf:about="{concat($uri,'/',normalize-space(.))}">
           <dcterms:format><dcterms:MediaTypeOrExtent>
           <rdfs:label><xsl:value-of select="'image/jpeg'"/></rdfs:label>
           </dcterms:MediaTypeOrExtent></dcterms:format>
           </dctypes:Image>
       </rdf:li>
       </xsl:for-each>
       </rdf:Seq>
       </dcterms:hasPart>
   </xsl:when>
  </xsl:choose>
</xsl:template>

<!--
<xsl:template match="resultset[@table='license']">
    <dcterms:license rdf:resource="{row/field[@name='link']}"/>
</xsl:template>
-->

<xsl:template match="resultset[@table='opus_files']/row/field[@name='extent'][text()!='' and text()!='NULL']">
  <dcterms:extent><dcterms:SizeOrDuration>
      <rdf:value><xsl:value-of select="."/></rdf:value>
  </dcterms:SizeOrDuration></dcterms:extent>
</xsl:template>

<!-- SERIAL -->
<xsl:template match="resultset[@table='schriftenreihen']">
    <!-- <xsl:comment><xsl:value-of select="' Serial '"/></xsl:comment> -->
    <xsl:for-each select="row">
        <dcterms:isPartOf><xsl:apply-templates select="."/></dcterms:isPartOf>
    </xsl:for-each>
    <xsl:apply-templates select="row/field[@name='sequence_nr']"/>
</xsl:template>

<xsl:template match="resultset[@table='schriftenreihen']/row">
    <dcterms:BibliographicResource rdf:about="{field[@name='url']}">
        <dcterms:title>
            <xsl:value-of select="field[@name='name']"/>
        </dcterms:title>
        <xsl:apply-templates select="field[@name='urn']"/>
        <xsl:apply-templates select="../../../field[@name='isbn']"/>
        <xsl:apply-templates select="field[@name='year']"/>
        <xsl:apply-templates select="field[@name='zdb']"/>
        <xsl:apply-templates select="field[@name='doi']"/>
        <xsl:apply-templates select="field[@name='type']"/>
        <foaf:img>
            <xsl:value-of select="concat(field[@name='url'],'/cover.png')"/>
        </foaf:img>
        <xsl:apply-templates select="field[@name='organization']"/>
        <xsl:apply-templates select="field[@name='universitaet']"/>
        <xsl:apply-templates select="field[@name='contributor']"/>
        <xsl:apply-templates select="field[@name='description']"/>
        <xsl:apply-templates select="//resultset[@table='opus']/row/field[@name='license']"/> 
        <xsl:apply-templates select="//resultset[@table='opus_schriftenreihe']/row/field[@name='url']"/> 

    </dcterms:BibliographicResource>
</xsl:template>

<xsl:template match="resultset[@table='schriftenreihen']/row/field[@name='year']">
    <dcterms:created><xsl:value-of select="."/></dcterms:created>
</xsl:template>

<!--
    <foaf:Organization>
        <foaf:name><xsl:value-of select="."/></foaf:name>
    </foaf:Organization>
-->
<!--
<xsl:template match="resultset[@table='schriftenreihen']/row/field[@name='contributor']">
    <dcterms:creator>
        <xsl:call-template name="person"> 
            <xsl:with-param name="text" select="."/>
        </xsl:call-template>
    </dcterms:creator>
</xsl:template>
-->
<xsl:template match="resultset[@table='schriftenreihen']/row/field[@name='contributor']">
    <dcterms:contributor><rdf:Seq>
      <xsl:call-template name="tokenize">
        <xsl:with-param name="text"><xsl:value-of select="."/></xsl:with-param>
      </xsl:call-template>
    </rdf:Seq></dcterms:contributor>
</xsl:template>

<!-- ZDB Erstkat-ID -->
<xsl:template match="resultset[@table='schriftenreihen']/row/field[@name='zdb']">
    <sco:leiCode><xsl:value-of select="."/></sco:leiCode>
</xsl:template>

<xsl:template match="resultset[@table='schriftenreihen']/row/field[@name='universitaet']">
  <dcterms:publisher>
      <foaf:Organization rdf:about="{../field[@name='uni_gnd']}">
          <foaf:name><xsl:value-of select="."/></foaf:name>
      </foaf:Organization>
  </dcterms:publisher>
</xsl:template>

<!-- ZDB ZS-Ausgabe vol.year -->
<!-- all sequences are listed so choose the right one -->

<xsl:template match="resultset[@table='schriftenreihen']/row/field[@name='sequence_nr']">
    <sco:volumeNumber><xsl:value-of select="."/></sco:volumeNumber>
</xsl:template>

<xsl:template match="resultset[@table='opus_schriftenreihe']/row/field[@name='url']">
    <dcterms:hasPart rdf:resource="{.}"/>
</xsl:template>

<!-- COLLECTION -->
<xsl:template match="resultset[@table='collections']">
    <!-- <xsl:comment><xsl:value-of select="' Collection '"/></xsl:comment> -->
    <xsl:for-each select="row">
        <dcterms:isPartOf><xsl:apply-templates select="."/></dcterms:isPartOf>
    </xsl:for-each>
</xsl:template>

<xsl:template match="resultset[@table='collections']/row">
    <dctypes:Collection rdf:about="https:{field[@name='url']}">
        <dcterms:title>
            <xsl:value-of select="field[@name='coll_name']"/>
        </dcterms:title>
        <xsl:apply-templates select="field[@name='urn']"/>
        <dcterms:type rdf:resource="http://purl.org/spar/fabio/Collection"/>
        <xsl:apply-templates select="//resultset[@table='opus_coll']/row/field[@name='url']"/> 
        <xsl:apply-templates select="//resultset[@table='opus']/row/field[@name='license']"/> 
        <xsl:apply-templates select="field[@name='description']"/>
        <foaf:img>
            <xsl:value-of select="concat(field[@name='url'],'/cover.png')"/>
        </foaf:img>
    </dctypes:Collection>
</xsl:template>

<xsl:template match="resultset[@table='opus_coll']/row/field[@name='url']">
    <dcterms:hasPart rdf:resource="{.}"/>
</xsl:template>

<xsl:template match="document/resultset[@table='opus_autor']">
    <xsl:param name="uri"/>
  <xsl:choose>
  <xsl:when test="count(row)=0"></xsl:when>
  <xsl:when test="count(row)>1 and $uri=''">
  <dcterms:creator>
   <rdf:Seq>
     <xsl:apply-templates select="row"/>
   </rdf:Seq>
  </dcterms:creator>
  </xsl:when>
  <xsl:when test="count(row)>1">
  <dcterms:creator>
   <rdf:Seq rdf:about="{concat($uri,'/Authors')}">
     <xsl:apply-templates select="row"/>
   </rdf:Seq>
  </dcterms:creator>
  </xsl:when>
  <xsl:otherwise>
  <dcterms:creator>
     <xsl:apply-templates select="row/field[@name='creator_name']"/>
  </dcterms:creator>
  </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<xsl:template match="document/resultset[@table='opus_autor']/row">
  <rdf:li>
  <xsl:apply-templates select="field[@name='creator_name']"/>
  </rdf:li>
</xsl:template>

<xsl:template match="resultset[@table='opus_toc'][count(row)>0]">
  <dcterms:tableOfContents>
    <rdf:Seq>
      <xsl:apply-templates select="row"/>
    </rdf:Seq>
  </dcterms:tableOfContents>
</xsl:template>

<xsl:template match="resultset[@table='opus_toc']/row">
 <rdf:li>
     <xsl:value-of select="concat(field[@name='label'], '&#9;',
         field[@name='page'], '&#9;', field[@name='number'])"/>
 </rdf:li>
</xsl:template>

<xsl:template match="resultset[@table='opus_references'][count(row)>0]">
  <dcterms:references>
    <rdf:Seq>
      <xsl:apply-templates select="row"/>
    </rdf:Seq>
  </dcterms:references>
</xsl:template>

<!-- skipped:
    <xsl:apply-templates select="field[@name='authors']"/>
    <xsl:apply-templates select="field[@name='date']"/>
    <xsl:apply-templates select="field[@name='title']"/>
-->
<xsl:template match="resultset[@table='opus_references']/row">
  <rdf:li>
      <dcterms:BibliographicResource rdf:about="{field[@name='uri']}">
          <dcterms:bibliographicCitation>
              <xsl:value-of select="field[@name='cite']"/>
          </dcterms:bibliographicCitation>
      </dcterms:BibliographicResource>
  </rdf:li>
</xsl:template>

<xsl:template match="resultset[@table='opus_citations'][count(row)>0]">
  <dcterms:isReferencedBy>
    <rdf:Seq>
      <xsl:apply-templates select="row"/>
    </rdf:Seq>
  </dcterms:isReferencedBy>
</xsl:template>

<xsl:template match="resultset[@table='opus_citations']/row">
  <rdf:li>
      <dcterms:BibliographicResource rdf:about="{concat($graph, '/', field[@name='isReferencedBy'])}">
          <dcterms:title>
              <xsl:value-of select="field[@name='title']"/>
          </dcterms:title>
      </dcterms:BibliographicResource>
  </rdf:li>
</xsl:template>

<xsl:template name="tokenize">
    <xsl:param name="text"/>
    <xsl:param name="delimiter" select="'; '"/>
    <xsl:choose>
      <xsl:when test="contains($text,$delimiter)">
        <xsl:call-template name="person">
            <xsl:with-param name="text" 
                 select="substring-before($text,$delimiter)"/>
        </xsl:call-template>
        <xsl:call-template name="tokenize">
          <xsl:with-param name="text" select="substring-after($text,$delimiter)"/>
          <xsl:with-param name="delimiter" select="$delimiter"/>
        </xsl:call-template>
      </xsl:when>
      <xsl:when test="$text">
        <xsl:call-template name="person">
            <xsl:with-param name="text" select="$text"/>
        </xsl:call-template>
      </xsl:when>
    </xsl:choose>
</xsl:template>

<xsl:template name="person">
    <xsl:param name="text"/>
    <xsl:element name="rdf:li">
      <xsl:element name="foaf:Person">
        <!--
        <xsl:attribute name="rdf:about">
            <xsl:value-of select="concat($graph, '/aut/',$aut)"/>
        </xsl:attribute>
        -->
        <xsl:choose>
        <xsl:when test="contains($text,'(Hrsg')">
            <xsl:element name="foaf:name">
              <xsl:value-of select="normalize-space(substring-before($text,'(Hrsg'))"/>
            </xsl:element>
            <xsl:element name="foaf:role">
              <xsl:value-of select="'edt'"/>
            </xsl:element>
        </xsl:when>
        <xsl:when test="contains($text,'(Übers')">
            <xsl:element name="foaf:name">
              <xsl:value-of select="normalize-space(substring-before($text,'(Übers'))"/>
            </xsl:element>
            <xsl:element name="foaf:role">
              <xsl:value-of select="'trl'"/>
            </xsl:element>
        </xsl:when>
        <xsl:when test="contains($text,'[')">
            <xsl:element name="foaf:name">
              <xsl:value-of select="normalize-space(substring-before($text,'['))"/>
            </xsl:element>
            <xsl:element name="foaf:role">
              <xsl:value-of select="substring-after(substring-before($text,']'),'[')"/>
            </xsl:element>
        </xsl:when>
        <xsl:otherwise>
            <xsl:element name="foaf:name">
              <xsl:value-of select="$text"/>
            </xsl:element>
        </xsl:otherwise>
        </xsl:choose>
        </xsl:element>
    </xsl:element>
</xsl:template>

<!-- <xsl:comment><xsl:value-of select="$text"/></xsl:comment> -->
<xsl:template name="swd2skos">
  <xsl:param name="text"/>
  <xsl:param name="sep"/>
  <xsl:choose>
    <xsl:when test="contains($text,$sep)">
      <xsl:call-template name="swd2skos">
        <xsl:with-param name="text" select="substring-before($text,$sep)"/>
        <xsl:with-param name="sep"  select="$sep"/>
      </xsl:call-template>
      <xsl:call-template name="swd2skos">
        <xsl:with-param name="text" select="substring-after($text,$sep)"/>
        <xsl:with-param name="sep"  select="$sep"/>
      </xsl:call-template>
    </xsl:when>
    <xsl:otherwise>
    <!-- SWD tentatively : http://d-nb.info/gnd/4331361-9 -->
    <xsl:variable name="cls">
    <xsl:call-template name="string-generic">
        <xsl:with-param name="text" select="$text"/>
    </xsl:call-template>
    </xsl:variable>
    <dcterms:subject>
     <skos:Concept rdf:about="{concat('http://example.org/swd/',$cls)}">
      <rdfs:label><xsl:value-of select="normalize-space($text)"/></rdfs:label>
     </skos:Concept>
    </dcterms:subject>
   </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<xsl:template name="unc2skos">
  <xsl:param name="text"/>
  <xsl:param name="lang"/>
  <xsl:param name="sep">
      <xsl:choose>
          <xsl:when test="contains($text,' , ')">
              <xsl:value-of select="' , '"/>
          </xsl:when>
          <xsl:when test="contains($text,';')">
              <xsl:value-of select="'; '"/>
          </xsl:when>
          <xsl:otherwise>
              <xsl:value-of select="', '"/>
          </xsl:otherwise>
    </xsl:choose>
  </xsl:param>
  <xsl:call-template name="unc2skosX">
     <xsl:with-param name="text"><xsl:value-of select="$text"/></xsl:with-param>
     <xsl:with-param name="lang"><xsl:value-of select="$lang"/></xsl:with-param>
     <xsl:with-param name="sep"><xsl:value-of select="$sep"/></xsl:with-param>
  </xsl:call-template>
</xsl:template>

<xsl:template name="unc2skosX">
  <xsl:param name="text"/>
  <xsl:param name="lang"/>
  <xsl:param name="sep"/>
  <xsl:choose>
    <xsl:when test="contains($text,$sep)">
      <xsl:call-template name="unc2skosX">
        <xsl:with-param name="text" select="substring-before($text,$sep)"/>
        <xsl:with-param name="lang" select="$lang"/>
        <xsl:with-param name="sep" select="$sep"/>
      </xsl:call-template>
      <xsl:call-template name="unc2skosX">
        <xsl:with-param name="text" select="substring-after($text,$sep)"/>
        <xsl:with-param name="lang" select="$lang"/>
        <xsl:with-param name="sep" select="$sep"/>
      </xsl:call-template>
    </xsl:when>
    <xsl:otherwise>
    <dcterms:subject>
     <skos:Concept>
      <rdfs:label xml:lang="{$lang}"><xsl:value-of select="normalize-space($text)"/></rdfs:label>
     </skos:Concept>
    </dcterms:subject>
   </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<xsl:template match="text()"/>
</xsl:stylesheet>

