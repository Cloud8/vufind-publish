<?xml version="1.0" encoding="UTF-8"?>
<xsl:stylesheet 
     xmlns:xsl="http://www.w3.org/1999/XSL/Transform" 
     xmlns:rdf="http://www.w3.org/1999/02/22-rdf-syntax-ns#"
     xmlns:rdfs="http://www.w3.org/2000/01/rdf-schema#"
     xmlns:dcterms="http://purl.org/dc/terms/"
     xmlns:dctypes="http://purl.org/dc/dcmitype/"
     xmlns:skos="http://www.w3.org/2004/02/skos/core#"
     xmlns:foaf="http://xmlns.com/foaf/0.1/"
     xmlns:aiiso="http://purl.org/vocab/aiiso/schema#"
     xmlns:fabio="http://purl.org/spar/fabio/"
     xmlns:xsd="http://www.w3.org/2001/XMLSchema#"
     xmlns:sco="http://schema.org/"
     version="1.0" >

<!-- RDF Solr transformer 2023 -->
<xsl:output method="xml" encoding="UTF-8" indent="yes" />
<xsl:strip-space elements="*"/> 

<!-- ojs:article : use archive or source url (yes/no) -->
<xsl:param name="ojs-view" select="'yes'"/>

<xsl:template match="rdf:RDF">
    <add>
        <!-- Item -->
        <xsl:apply-templates select="dcterms:BibliographicResource"/>
        <!-- Collection Serial Issue -->
        <xsl:apply-templates select="dcterms:BibliographicResource/dcterms:isPartOf" mode="index"/>
        <!-- Journal -->
        <xsl:apply-templates select="dcterms:BibliographicResource/dcterms:isPartOf/dcterms:BibliographicResource/dcterms:isPartOf" mode="index"/>
        <!-- Collection root -->
        <xsl:apply-templates select="dctypes:Collection"/>
    </add>
</xsl:template>

<xsl:template match="dcterms:BibliographicResource[dcterms:identifier]|dctypes:Collection[dcterms:identifier]">
 <xsl:comment>Seaview Bibliographic Resource Transformer (2023)</xsl:comment>
 <doc>
    <xsl:apply-templates select="dcterms:*"/>
    <xsl:apply-templates select="sco:*"/>
    <xsl:apply-templates select="foaf:img"/>
    <xsl:apply-templates select="@rdf:about"/>

    <field name="allfields">
        <xsl:value-of 
            select="(substring-after(substring-after(@rdf:about,'//'),'/'))"/>
        <xsl:for-each select="//dcterms:identifier">
            <xsl:value-of select="concat(' ', normalize-space(text()))"/>
        </xsl:for-each>
        <xsl:for-each select="sco:*">
            <xsl:value-of select="concat(' ', normalize-space(text()))"/>
        </xsl:for-each>
        <xsl:for-each select="dcterms:isFormatOf">
            <xsl:value-of select="concat(' ', normalize-space(text()))"/>
        </xsl:for-each>
            <xsl:for-each select="dcterms:creator/foaf:Person/foaf:account">
            <xsl:value-of select="concat(' orcid:', text())"/>
        </xsl:for-each>
    </field>
    <field name="fullrecord">
        <xsl:apply-templates select="." mode="fullrecord"/>
    </field>
 </doc>
</xsl:template>

<!-- IDENTIFIERS -->
<xsl:template match="dcterms:identifier[starts-with(text(),'urn:')]">
    <xsl:variable name="oid" select="substring(.,0,string-length(.))"/>
    <field name="record_format">dcterms</field>
    <field name="id"><xsl:value-of select="$oid"/></field>
    <field name="uuid_str_mv"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:identifier[not(contains(text(),':'))]">
    <field name="record_format">dcterms</field>
    <field name="id"><xsl:value-of select="translate(.,'/','.')"/></field>
    <field name="uuid_str_mv"><xsl:value-of select="../@rdf:about"/></field>
</xsl:template>

<xsl:template match="dcterms:identifier[starts-with(text(),'https:')]">
    <field name="doi_str_mv"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:identifier[starts-with(text(),'oclc:')]">
    <field name="oclc_num"><xsl:value-of select="substring(.,6)"/></field>
</xsl:template>

<xsl:template match="dcterms:identifier[starts-with(text(),'uuid:')]">
    <field name="uuid_str_mv"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:BibliographicResource/@rdf:about[starts-with(.,'http')]">
    <xsl:variable name="uid">
        <xsl:value-of select="(substring-after(substring-after(.,'//'),'/'))"/>
    </xsl:variable>
    <!-- <field name="lccn"><xsl:value-of select="$uid"/></field> -->
    <field name="callnumber-raw"><xsl:value-of select="$uid"/></field>
    <field name="callnumber-sort"><xsl:value-of select="$uid"/></field>
    <field name="callnumber-label">
        <xsl:value-of select="translate($uid,'/',' ')"/>
    </field>
    <field name="callnumber-first">
        <xsl:value-of select="substring-before($uid,'/')"/>
    </field>
    <field name="callnumber-subject">
      <xsl:value-of select="concat(substring-before($uid,'/'),' ',
                    substring-before(substring-after($uid,'/'),'/'))"/>
    </field>
    <field name="uri_str">
        <xsl:value-of select="concat('https://',substring-after(.,'//'))"/>
    </field>
    <xsl:choose>
        <xsl:when test="contains(.,'/eb/')"></xsl:when>
        <xsl:when test="count(../dcterms:hasPart)=1 and count(../dcterms:hasPart/dctypes:MovingImage)=1"></xsl:when>
        <xsl:when test="contains(../dcterms:type/@rdf:resource,'/Series')"></xsl:when>
        <xsl:otherwise>
            <field name="oai_set_str_mv">
                <xsl:value-of select="'xMetaDissPlus'"/>
            </field>
        </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<xsl:template match="sco:isbn">
    <field name="isbn"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="sco:issn">
    <field name="issn"><xsl:value-of select="."/></field>
    <field name="oai_set_str_mv">
    <xsl:value-of select="concat('issn:',.)"/></field>
</xsl:template>

<!-- opac -->
<xsl:template match="dcterms:hasVersion[count(*)=0]">
    <field name="edition"><xsl:value-of select="."/></field>
</xsl:template>

<!-- dbib:version -->
<xsl:template match="dcterms:hasVersion[count(*)=1]">
    <xsl:apply-templates select="dctypes:Text"/>
</xsl:template>

<!-- versions handled as data material for now -->
<xsl:template match="dcterms:hasVersion/dctypes:Text">
    <!--
    <field name="version_str_mv"><xsl:value-of select="@rdf:about"/></field>
    -->
</xsl:template>

<xsl:template match="dcterms:title">
    <field name="title"><xsl:value-of select="." /></field>
    <field name="title_short"><xsl:value-of select="." /></field>
    <field name="title_full"><xsl:value-of select="." /></field>
    <field name="title_sort">
    <xsl:value-of select="concat(../sco:volumeNumber,' ',.)" /></field>
</xsl:template>

<!-- TITLE correct sort to ten -->
<!--
<xsl:template match="dcterms:title[@xml:lang]">
    <xsl:variable name="lang"><xsl:call-template name="getlang"/></xsl:variable>
    <xsl:comment><xsl:value-of select="concat(' lang: ',$lang)"/></xsl:comment>
    <xsl:choose>
        <xsl:when test="@xml:lang!=$lang">
            <field name="title_alt"><xsl:value-of select="." /></field>
        </xsl:when>
        <xsl:when test="@xml:lang=$lang">
        <field name="title"><xsl:value-of select="." /></field>
        <field name="title_short"><xsl:value-of select="." /></field>
        <field name="title_full"><xsl:value-of select="." /></field>
        <field name="title_sort">
        <xsl:value-of select="concat(../sco:volumeNumber,' ',.)" />
        </field>
        </xsl:when>
    </xsl:choose>
</xsl:template>
-->

<!-- MAIN TITLE -->
<!--
<xsl:template match="dcterms:title[not(@xml:lang)][1]">
    <field name="title"><xsl:value-of select="." /></field>
    <field name="title_short"><xsl:value-of select="." /></field>
    <field name="title_sort"><xsl:value-of select="." /></field>
    <xsl:choose>
        <xsl:when test="count(../dcterms:isPartOf//dcterms:title)=1 and contains(../dcterms:type/@rdf:resource, 'Issue')">
            <field name="title_full">
                <xsl:value-of select="concat(../dcterms:isPartOf//dcterms:title,': ',.)" />
            </field>
        </xsl:when>
        <xsl:otherwise>
            <field name="title_full"><xsl:value-of select="." /></field>
        </xsl:otherwise>
   </xsl:choose>
</xsl:template>
 -->

<!-- SUBTITLE -->
<xsl:template match="dcterms:alternative[not(@xml:lang)]">
    <field name="title_sub"><xsl:value-of select="." /></field>
</xsl:template>

<!-- TITLE TRANSLITERATED -->
<xsl:template match="dcterms:alternative[@xml:lang]">
    <field name="title_alt"><xsl:value-of select="." /></field>
</xsl:template>

<!-- AUTHOR -->
<xsl:template match="dcterms:creator">
    <xsl:apply-templates select="rdf:Seq"/>
    <xsl:apply-templates select="foaf:Person"/>
    <xsl:apply-templates select="foaf:Organization"/>
</xsl:template>

<xsl:template match="dcterms:creator/foaf:Organization">
    <xsl:apply-templates select="foaf:name"/>
    <xsl:apply-templates select="foaf:role"/>
</xsl:template>

<xsl:template match="dcterms:creator/foaf:Person">
    <xsl:apply-templates select="foaf:name"/>
    <xsl:apply-templates select="foaf:role"/>
    <xsl:apply-templates select="@rdf:about"/>
</xsl:template>

<xsl:template match="dcterms:creator/rdf:Seq">
    <xsl:apply-templates select="rdf:li/foaf:Person"/>
    <xsl:apply-templates select="rdf:li[@rdf:resource]"/>
</xsl:template>

<xsl:template match="dcterms:creator/rdf:Seq/rdf:li/foaf:Person">
    <xsl:apply-templates select="foaf:name"/>
    <xsl:apply-templates select="foaf:role"/>
    <xsl:apply-templates select="@rdf:about"/>
</xsl:template>

<xsl:template match="dcterms:creator/rdf:Seq/rdf:li[@rdf:resource]">
    <xsl:param name="about" select="@rdf:resource"/>
    <xsl:apply-templates select="//foaf:Person[@rdf:about=$about]"/>
</xsl:template>

<xsl:template match="dcterms:creator//foaf:name">
    <field name="author"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:creator//foaf:role">
    <field name="author_role"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:creator//foaf:Person/@rdf:about">
    <field name="author_variant"><xsl:value-of select="."/></field>
</xsl:template>

<!-- Not displayed -->
<xsl:template match="dcterms:creator/rdf:Seq/rdf:li/foaf:Person[foaf:role='add']/foaf:name">
    <field name="author_additional"><xsl:value-of select="."/></field>
</xsl:template>

<!-- CONTRIBUTOR -->
<xsl:template match="dcterms:contributor">
    <xsl:apply-templates select="rdf:Seq"/>
    <xsl:apply-templates select="foaf:Person"/>
    <xsl:apply-templates select="foaf:Organization"/>
    <xsl:apply-templates select="aiiso:Faculty" />
    <xsl:apply-templates select="aiiso:Center" />
    <xsl:apply-templates select="aiiso:Division" />
    <xsl:apply-templates select="aiiso:Institute" />
</xsl:template>

<xsl:template match="dcterms:contributor/rdf:Seq">
    <xsl:apply-templates select="rdf:li"/>
</xsl:template>

<xsl:template match="dcterms:contributor/rdf:Seq/rdf:li">
    <xsl:apply-templates select="foaf:Person"/>
</xsl:template>

<xsl:template match="dcterms:contributor//foaf:Person">
    <xsl:comment><xsl:value-of select="'author2'"/></xsl:comment>
    <xsl:apply-templates select="foaf:name"/>
    <xsl:apply-templates select="foaf:role"/>
</xsl:template>

<xsl:template match="dcterms:contributor//foaf:Person/foaf:name[1]">
     <field name="author2"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:contributor//foaf:Person/foaf:role[1]">
     <field name="author2_role"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:contributor/foaf:Organization">
    <xsl:apply-templates select="foaf:name"/>
    <xsl:apply-templates select="foaf:role"/>
</xsl:template>

<xsl:template match="dcterms:contributor/foaf:Organization/foaf:name[1]">
   <field name="author_corporate"><xsl:value-of select="."/></field>
   <xsl:if test="count(../foaf:role)=0">
   <field name="author_corporate_role"><xsl:value-of select="'isb'"/></field>
   </xsl:if>
</xsl:template>

<xsl:template match="dcterms:contributor/foaf:Organization/foaf:role[1]">
   <field name="author_corporate_role"><xsl:value-of select="."/></field>
</xsl:template>

<!-- Opac -->
<xsl:template match="dcterms:medium">
   <xsl:apply-templates select="rdf:Seq/rdf:li/dcterms:PhysicalMedium" />
</xsl:template>

<xsl:template match="dcterms:PhysicalMedium">
   <xsl:apply-templates select="dcterms:spatial/dcterms:Location" />
   <xsl:apply-templates select="dcterms:spatial[@rdf:resource]" />
   <xsl:apply-templates select="rdfs:label" />
</xsl:template>

<xsl:template match="dcterms:PhysicalMedium/rdfs:label">
    <field name="callnumber-raw"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:spatial/dcterms:Location">
    <xsl:apply-templates select="foaf:name"/>
</xsl:template>

<xsl:template match="dcterms:spatial[@rdf:resource]">
    <xsl:variable name="about" select="@rdf:resource"/>
    <xsl:apply-templates select="//*/dcterms:Location[@rdf:about=$about]"/>
</xsl:template>

<xsl:template match="dcterms:spatial/dcterms:Location/foaf:name">
    <field name="institution"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:publisher">
    <xsl:apply-templates select="foaf:Organization" />
</xsl:template>

<xsl:template match="dcterms:publisher/foaf:Organization">
    <field name="publisher"><xsl:value-of select="foaf:name"/></field>
</xsl:template>

<xsl:template match="dcterms:contributor/aiiso:Faculty">
    <field name="building"><xsl:value-of select="foaf:name"/></field>
</xsl:template>

<xsl:template match="dcterms:contributor/aiiso:Center">
    <field name="building"><xsl:value-of select="foaf:name"/></field>
</xsl:template>

<xsl:template match="dcterms:contributor/aiiso:Division">
    <field name="building"><xsl:value-of select="foaf:name"/></field>
</xsl:template>

<xsl:template match="dcterms:contributor/aiiso:Institute">
    <field name="institution"><xsl:value-of select="foaf:name"/></field>
</xsl:template>

<xsl:template match="dcterms:source[@rdf:resource]">
    <xsl:apply-templates select="@rdf:resource"/>
</xsl:template>

<xsl:template match="dcterms:source/@rdf:resource">
    <xsl:if test="$ojs-view='yes'">
        <field name="url"><xsl:value-of select="."/></field>
    </xsl:if>
</xsl:template>

<xsl:template match="dcterms:source[not(@rdf:resource)]">
    <field name="external_str_mv">
        <xsl:value-of select="concat('[Source](',.,')')"/>
    </field>
</xsl:template>

<!-- FORMAT : see type -->
<xsl:template match="dcterms:format">
  <!-- <field name="format"><xsl:value-of select="."/></field> -->
</xsl:template>

<!-- LANGUAGE -->
<xsl:template match="dcterms:language">
   <field name="language"><xsl:value-of select="."/></field>
</xsl:template>

<!-- OAI server skips records without this -->
<xsl:template match="dcterms:modified[1]">
  <xsl:variable name="dateX" select="'2021-02-26'"/>
  <xsl:variable name="date" select="."/>
  <field name="last_indexed">
      <xsl:value-of select="concat($date,'T23:59:59Z')"/>
  </field>
</xsl:template>

<xsl:template match="dcterms:issued[1]">
  <field name="publishDateSort"><xsl:value-of select="." /></field>
  <field name="first_indexed">
      <xsl:value-of select="concat(.,'T00:00:01Z')" />
  </field>
  <xsl:if test="count(../dcterms:modified)=0">
  <field name="last_indexed">
      <xsl:value-of select="concat(.,'T03:59:59Z')"/>
  </field>
  </xsl:if>
</xsl:template>

<!-- dbib:manuscript -->
<xsl:template match="dcterms:date">
    <field name="dateSpan"><xsl:value-of select="."/></field>
    <xsl:variable name="date" select="normalize-space(translate(.,'.',''))"/>
    <xsl:if test="string-length($date)=4">
        <field name="era_facet"><xsl:value-of select="$date" /></field>
    </xsl:if>
</xsl:template>

<!-- to item tab for simplicity -->
<xsl:template match="dcterms:dateAccepted">
    <field name="external_str_mv">
        <xsl:value-of select="concat('[Accepted](',.,')')" />
    </field>
</xsl:template>

<xsl:template match="dcterms:created">
    <field name="era_facet"><xsl:value-of select="."/></field>
    <field name="publishDate"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:extent">
  <xsl:apply-templates select="dcterms:SizeOrDuration"/>
</xsl:template>

<xsl:template match="dcterms:extent/dcterms:SizeOrDuration">
  <field name="physical"><xsl:value-of select="rdf:value"/></field>
</xsl:template>

<xsl:template match="foaf:img[starts-with(.,'http')]">
    <field name="thumbnail">
        <xsl:value-of select="substring-after(.,':')"/>
    </field>
</xsl:template>

<xsl:template match="foaf:img[starts-with(.,'file')]">
    <field name="thumbnail"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="foaf:img[starts-with(.,'//')]">
    <field name="thumbnail"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:type[@rdf:resource]">
    <xsl:apply-templates select="@rdf:resource"/>
</xsl:template>

<xsl:template match="dcterms:type/@rdf:resource">
    <field name="format">
        <xsl:value-of select="substring-after(.,'/fabio/')"/>
    </field>
</xsl:template>

<xsl:template match="sco:additionalType">
    <field name="oai_set_str_mv">
        <xsl:value-of select="concat('doc-type:',.)"/>
    </field>
</xsl:template>

<xsl:template match="dcterms:subject">
    <xsl:apply-templates select="skos:Concept"/>
</xsl:template>

<xsl:template match="dcterms:subject/skos:Concept[@rdf:about]">
    <xsl:apply-templates select="rdf:value"/>
    <xsl:apply-templates select="rdfs:label"/>
</xsl:template>

<!-- CCS MSC PACS JEL -->
<xsl:template match="skos:Concept[@rdf:about]/rdf:value">
    <field name="topic"><xsl:value-of select="."/></field>
</xsl:template>

<!-- SWD -->
<xsl:template match="skos:Concept[@rdf:about]/rdfs:label">
    <field name="topic"><xsl:value-of select="."/></field>
</xsl:template>

<!-- RVK -->
<xsl:template match="skos:Concept[contains(@rdf:about,'rvk')]">
    <field name="genre"><xsl:value-of select="rdfs:label"/></field>
    <field name="genre_facet"><xsl:value-of select="rdfs:label"/></field>
</xsl:template>

<!-- DDC -->
<xsl:template match="skos:Concept[contains(@rdf:about,'dewey')]">
  <xsl:param name="class" 
       select="normalize-space(substring-after(@rdf:about,'class/'))"/>
  <xsl:if test="$class!=''">
  <field name="oai_set_str_mv">
   <xsl:value-of select="concat('ddc:',translate($class,translate($class,'0123456789.',''),''))"/>
  </field>
  <field name="dewey-raw"><xsl:value-of select="$class"/></field>
  </xsl:if>
  <xsl:apply-templates select="skos:prefLabel" />
</xsl:template>

<!-- DDC skos:prefLabel -->
<xsl:template match="skos:Concept[@rdf:about]/skos:prefLabel[@xml:lang='de']">
    <field name="topic_facet"><xsl:value-of select="." /></field>
    <field name="topic"><xsl:value-of select="." /></field>
</xsl:template>

<!-- DDC skos:prefLabel -->
<xsl:template match="skos:Concept[@rdf:about]/skos:prefLabel[@xml:lang='en']">
    <field name="genre"><xsl:value-of select="." /></field>
    <field name="genre_facet"><xsl:value-of select="." /></field>
</xsl:template>

<!-- topics uncontrolled -->
<xsl:template match="dcterms:subject/skos:Concept[not(@rdf:about)]">
    <field name="topic"><xsl:value-of select="."/></field>
</xsl:template>

<!--
<xsl:template match="skos:Concept[not(@rdf:about)]/rdfs:label">
   <field name="topic"><xsl:value-of select="."/></field>
</xsl:template>
-->

<!-- description_str_mv for abstract description toc -->
<xsl:template match="dcterms:abstract">
   <field name="description"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:description">
    <xsl:comment>description <xsl:value-of select="@xml:lang"/></xsl:comment>
    <xsl:choose>
    <xsl:when test="count(../dcterms:tableOfContents)=0">
        <field name="contents"><xsl:value-of select="."/></field>
    </xsl:when>
    <xsl:otherwise>
        <field name="description_str_mv"><xsl:value-of select="."/></field>
    </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<xsl:template match="dcterms:hasPart[not(@rdf:resource)]">
    <xsl:apply-templates select="dctypes:Text"/>
    <xsl:apply-templates select="dctypes:MovingImage"/>
    <xsl:apply-templates select="dctypes:Dataset"/>
    <xsl:apply-templates select="dctypes:Image"/>
    <xsl:apply-templates select="rdf:Seq/rdf:li/dctypes:Image"/>
    <xsl:apply-templates select="dctypes:Collection"/>
</xsl:template>

<!-- data.zip -->
<xsl:template match="dcterms:hasPart/dctypes:Dataset">
    <field name="format"><xsl:value-of select="'Dataset'"/></field>
    <xsl:apply-templates select="@rdf:about"/>
</xsl:template>

<!-- image sequence : manifest requires first link only -->
<xsl:template match="dcterms:hasPart/rdf:Seq/rdf:li[1]/dctypes:Image">
    <field name="url">
        <xsl:value-of select="substring-after(@rdf:about,':')"/>
    </field>
</xsl:template>

<xsl:template match="dcterms:hasPart/dctypes:Text|dcterms:hasPart/dctypes:MovingImage|dcterms:hasPart/dctypes:Image">
    <xsl:apply-templates select="@rdf:about"/>
</xsl:template>

<xsl:template match="dcterms:hasPart/dctypes:*/@rdf:about">
    <xsl:choose>
        <xsl:when test="substring(., string-length(.)-3)='.xml'">
            <field name="url">
                <xsl:value-of select="concat('https:',substring-after(.,':'))"/>
            </field>
            <xsl:apply-templates select="../dcterms:extent"/>
        </xsl:when>
        <xsl:when test="contains(., '/All.pdf')"></xsl:when>
        <xsl:when test="starts-with(.,'file://')">
            <field name="url"><xsl:value-of select="."/></field>
        </xsl:when>
        <!-- Deprecated. Will go to _links -->
        <xsl:when test="contains(.,'Provenienz')">
            <field name="url">
                <xsl:value-of select="concat('[Provenance](',substring-after(.,':'),')')"/>
            </field>
        </xsl:when>
        <xsl:when test="../dcterms:source/@rdf:resource and $ojs-view='yes'">
        </xsl:when>
        <xsl:otherwise>
            <field name="url">
                <xsl:value-of select="substring-after(.,':')"/>
            </field>
            <xsl:apply-templates select="../dcterms:extent"/>
        </xsl:otherwise>
    </xsl:choose>
</xsl:template>

<!-- dbib:manuscript signatur -->
<xsl:template match="dcterms:isVersionOf">
    <field name="ctrlnum"><xsl:value-of select="."/></field>
</xsl:template>

<!-- LICENSE : core extension -->
<xsl:template match="dcterms:license[@rdf:resource]">
  <field name="license_str"><xsl:value-of select="@rdf:resource"/></field>
  <xsl:choose>
   <xsl:when test="../dcterms:accessRights"><!--restricted-->
     <field name="oai_set_str_mv">
            <xsl:value-of select="'restricted_access'"/></field>
   </xsl:when>
   <xsl:otherwise>
     <field name="oai_set_str_mv"><xsl:value-of select="'open_access'"/></field>
   </xsl:otherwise>
  </xsl:choose>
</xsl:template>

<!-- RIGHTS : not evaluated yet -->
<xsl:template match="dcterms:rights[@rdf:resource]">
</xsl:template>

<!-- POLICY : es/2011/0004 since 2019-04 : to be deleted -->
<!--
<xsl:template match="dcterms:instructionalMethod">
    <field name="policy_str"><xsl:value-of select="."/></field>
</xsl:template>
-->

<!-- RESTRICTED ACCESS : core extension : IP address list -->
<xsl:template match="dcterms:accessRights">
    <field name="rights_str"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:tableOfContents">
    <xsl:apply-templates select="dcterms:BibliographicResource"/>
    <xsl:apply-templates select="rdf:Seq"/>
</xsl:template>

<!-- opac toc link -->
<xsl:template match="dcterms:tableOfContents/dcterms:BibliographicResource">
    <field name="contents">
      <xsl:value-of select="concat('[',dcterms:title,'](',@rdf:about,')')"/>
    </field>
</xsl:template>

<xsl:template match="dcterms:tableOfContents/rdf:Seq">
    <field name="contents">
        <xsl:for-each select="rdf:li">
            <xsl:value-of 
            select="concat('&lt;br&gt;',substring-before(text(),'&#9;'))"/>
        </xsl:for-each>
    </field>
</xsl:template>

<!-- opac -->
<xsl:template match="dcterms:coverage">
    <field name="description_str_mv"><xsl:value-of select="."/></field>
</xsl:template>

<!-- see rdf/SolrStorage.java -->
<xsl:template match="dcterms:temporal">
    <field name="fulltext"><xsl:value-of select="."/></field>
</xsl:template>

<!-- REFERENCES : core extension : retrieve from solr record since 2020-04 -->
<xsl:template match="dcterms:references">
    <xsl:apply-templates select="rdf:Seq"/>
</xsl:template>

<!-- References to solr -->
<xsl:template match="dcterms:BibliographicResource/dcterms:references/rdf:Seq">
    <field name="references_str"><xsl:value-of select="count(rdf:li)"/></field>
</xsl:template>

<!-- CITATIONS : core extension -->
<xsl:template match="dcterms:isReferencedBy">
    <xsl:apply-templates select="rdf:Seq"/>
    <xsl:apply-templates select="dcterms:BibliographicResource"/>
</xsl:template>

<xsl:template match="dcterms:isReferencedBy/rdf:Seq">
    <field name="cites_str"><xsl:value-of select="count(rdf:li)"/></field>
</xsl:template>

<!-- external link / heyne : descripted elsewhere -->
<xsl:template match="dcterms:isReferencedBy/dcterms:BibliographicResource">
    <xsl:apply-templates select="dcterms:title"/>
</xsl:template>

<xsl:template match="dcterms:isReferencedBy/dcterms:BibliographicResource[dcterms:relation]/dcterms:title">
    <field name="description_str_mv">
        <xsl:value-of select="concat('[',.,'](',../dcterms:relation,')')"/>
    </field>
</xsl:template>

<xsl:template match="dcterms:isReferencedBy/dcterms:BibliographicResource[not(dcterms:relation)]/dcterms:title">
    <field name="description_str_mv"><xsl:value-of select="."/></field>
</xsl:template>

<!-- external link : item tab -->
<xsl:template match="dcterms:relation">
    <xsl:apply-templates select="dcterms:BibliographicResource"/>
</xsl:template>

<xsl:template match="dcterms:relation/dcterms:BibliographicResource">
    <field name="external_str_mv">
        <xsl:value-of select="concat('[',dcterms:title,'](',@rdf:about,')')"/>
    </field>
</xsl:template>

<!-- PARTS -->
<xsl:template match="dcterms:BibliographicResource/dcterms:isPartOf">
    <xsl:apply-templates select="dctypes:Collection[dcterms:identifier]"/>
    <xsl:apply-templates select="dcterms:BibliographicResource"/>
</xsl:template>

<!-- Collection : item may be part of multiple collections -->
<xsl:template match="dcterms:isPartOf/dctypes:Collection">
    <xsl:comment><xsl:value-of select="local-name()"/></xsl:comment>
    <field name="collection"><xsl:value-of select="dcterms:title"/></field>
    <xsl:apply-templates select="dcterms:identifier" mode="parent"/>
    <xsl:apply-templates select="dcterms:identifier" mode="top"/>
    <xsl:apply-templates select="dcterms:title" mode="parent"/>
    <xsl:apply-templates select="dcterms:title" mode="top"/>
    <xsl:choose>
        <xsl:when test="count(following-sibling::dcterms:isPartOf)=0">
        </xsl:when>
        <xsl:otherwise>
            <field name="hierarchytype">default</field>
        </xsl:otherwise>
    </xsl:choose>
    <xsl:apply-templates select="dcterms:accrualPolicy"/>
</xsl:template>

<!-- GH2022-07-13 -->
<xsl:template match="dcterms:accrualPolicy">
    <field name="description_str_mv"><xsl:value-of select="."/></field>
</xsl:template>

<!-- Container Version 5.8 -->
<xsl:template match="dcterms:isPartOf/dcterms:BibliographicResource[dcterms:type/@rdf:resource]">
    <xsl:variable name="type" select="substring-after(dcterms:type/@rdf:resource,'/fabio/')"/>
    <xsl:comment><xsl:value-of select="concat(' part : ',$type)"/></xsl:comment>
    <xsl:apply-templates select="dcterms:isPartOf//dcterms:contributor"/>
    <xsl:choose>
        <xsl:when test="$type='JournalIssue'">
            <field name="hierarchytype">flat</field>
            <field name="container_title">
                <xsl:value-of select="dcterms:isPartOf//dcterms:title"/></field>
            <field name="hierarchy_parent_title">
                <xsl:value-of select="dcterms:title"/></field>
            <xsl:apply-templates select="dcterms:isPartOf//dcterms:publisher"/>
        </xsl:when>
        <xsl:otherwise>
            <field name="hierarchytype">default</field>
            <field name="container_title">
                <xsl:value-of select="dcterms:title"/></field>
            <xsl:apply-templates select="dcterms:publisher"/>
            <xsl:apply-templates select="../../dcterms:identifier" mode="self"/>
            <xsl:apply-templates select="../../dcterms:title" mode="self"/>
            <xsl:apply-templates select="dcterms:identifier" mode="parent"/>
            <xsl:apply-templates select="dcterms:title" mode="parent"/>
        </xsl:otherwise>
    </xsl:choose>
    <xsl:apply-templates select="dcterms:identifier" mode="top"/>
    <xsl:apply-templates select="dcterms:title" mode="top"/>
</xsl:template>

<xsl:template match="sco:volumeNumber">
    <field name="container_volume"><xsl:value-of select="."/></field>
    <field name="container_reference">
        <xsl:value-of select="concat(' (Band ',.,')')"/></field>
</xsl:template>

<!-- Opac serials -->
<xsl:template match="dcterms:isPartOf/dcterms:BibliographicResource[count(dcterms:identifier)=0]">
    <field name="series"><xsl:value-of select="dcterms:title"/></field>
</xsl:template>

<!-- ambigous rule match on line 149 -->
<!--
<xsl:template match="dcterms:isPartOf/dcterms:BibliographicResource[count(dcterms:identifier)=0]/dcterms:title">
    <field name="series"><xsl:value-of select="."/></field>
</xsl:template>
-->

<xsl:template match="dcterms:isPartOf" mode="index">
    <!-- Serial Issue Journal -->
    <xsl:apply-templates select="dcterms:BibliographicResource[dcterms:identifier]" mode="index"/>
    <!-- Collection -->
    <xsl:apply-templates select="dctypes:Collection[dcterms:identifier]" mode="index"/>
</xsl:template>

<!-- Version 5.9 -->
<xsl:template match="dcterms:isPartOf/dcterms:BibliographicResource|dcterms:isPartOf/dctypes:Collection" mode="index">
    <xsl:variable name="type" select="substring-after(dcterms:type/@rdf:resource,'/fabio/')"/>
  <doc>
    <xsl:comment><xsl:value-of select="concat(' type : ',$type)"/></xsl:comment>
    <xsl:apply-templates select="dcterms:identifier"/>
    <xsl:apply-templates select="dcterms:title"/>
    <xsl:apply-templates select="dcterms:alternative"/>
    <xsl:apply-templates select="dcterms:creator"/>
    <xsl:apply-templates select="dcterms:contributor"/>
    <xsl:apply-templates select="dcterms:publisher"/>
    <xsl:apply-templates select="dcterms:type/@rdf:resource"/>
    <xsl:choose>
        <xsl:when test="$type='Journal'">
            <field name="hierarchytype">flat</field>
            <field name="container_title">
                <xsl:value-of select="dcterms:title"/>
            </field>
            <xsl:apply-templates select="dcterms:identifier" mode="self"/>
            <xsl:apply-templates select="dcterms:identifier" mode="top"/>
            <xsl:apply-templates select="dcterms:title" mode="top"/>
        </xsl:when>
        <xsl:when test="$type='JournalIssue'">
            <field name="hierarchytype">flat</field>
            <field name="container_title">
                <xsl:value-of select="dcterms:isPartOf//dcterms:title"/>
            </field>
            <xsl:apply-templates select="dcterms:identifier" mode="self"/>
            <xsl:apply-templates select="dcterms:isPartOf//dcterms:identifier" mode="top"/>
        </xsl:when>
        <xsl:otherwise>
            <field name="hierarchytype">default</field>
            <xsl:apply-templates select="dcterms:title" mode="self"/>
            <xsl:apply-templates select="dcterms:identifier" mode="self"/>
            <xsl:apply-templates select="dcterms:identifier" mode="parent"/>
            <xsl:apply-templates select="dcterms:title" mode="parent"/>
            <xsl:apply-templates select="dcterms:identifier" mode="top"/>
            <xsl:apply-templates select="dcterms:title" mode="top"/>
        </xsl:otherwise>
    </xsl:choose>
    <xsl:apply-templates select="foaf:img"/>
    <xsl:apply-templates select="dcterms:license"/>
    <xsl:apply-templates select="dcterms:format"/>
    <xsl:apply-templates select="dcterms:abstract"/>
    <xsl:apply-templates select="dcterms:description"/>
    <field name="url"><xsl:value-of select="@rdf:about"/></field>
    <field name="fullrecord">
        <xsl:apply-templates select="." mode="fullrecord"/>
    </field>
  </doc>
</xsl:template>

<xsl:template match="dcterms:isPartOf//dcterms:title" mode="top">
    <field name="hierarchy_top_title"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:isPartOf//dcterms:identifier[starts-with(text(),'urn:')]" mode="top">
    <xsl:variable name="oid" select="substring(.,0,string-length(.))"/>
    <field name="hierarchy_top_id"><xsl:value-of select="$oid"/></field>
</xsl:template>

<!-- GH2020-12-06 : free journal indexing -->
<xsl:template match="dcterms:isPartOf//dcterms:identifier[not(starts-with(text(),'urn:'))][count(../dcterms:identifier)=1]" mode="top">
    <field name="hierarchy_top_id"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:isPartOf//dcterms:identifier[starts-with(text(),'urn:')]" mode="parent">
    <xsl:variable name="oid" select="substring(.,0,string-length(.))"/>
    <field name="hierarchy_parent_id"><xsl:value-of select="$oid"/></field>
    <field name="hierarchy_browse">
        <xsl:value-of select="concat(../dcterms:title,'{{{_ID_}}}',$oid)"/>
    </field>
</xsl:template>

<!-- GH2021-02 : handling of urn above may be bad -->
<xsl:template match="dcterms:isPartOf//dcterms:identifier[not(starts-with(text(),'http')  or starts-with(text(),'urn:'))]" mode="parent">
    <field name="hierarchy_parent_id"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:isPartOf//dcterms:title" mode="parent">
    <field name="hierarchy_parent_title"><xsl:value-of select="."/></field>
</xsl:template>

<xsl:template match="dcterms:identifier[starts-with(text(),'urn:')]" mode="self">
    <field name="is_hierarchy_id">
        <xsl:value-of select="substring(.,0,string-length(.))"/>
    </field>
</xsl:template>

<!-- GH2021-02 -->
<xsl:template match="dcterms:identifier[starts-with(text(),'http')]" mode="self">
</xsl:template>

<!-- GH2021-02 : magazined journals -->
<xsl:template match="dcterms:identifier[not(contains(text(),':'))]" mode="self">
    <field name="is_hierarchy_id"><xsl:value-of select="."/></field>
</xsl:template>

<!-- Deprecated. Was : CALLNUMBER : uri part -->
<xsl:template match="*[starts-with(@rdf:about,'http')]" mode="call">
    <!-- <xsl:value-of select="@rdf:about"/></field> -->
    <field name="uri_str">
        <xsl:choose>
            <xsl:when test="contains(@rdf:about, 'archiv')">
                <xsl:value-of select="concat('https:', substring-after(@rdf:about,':'))"/>
            </xsl:when>
            <xsl:otherwise>
                <xsl:value-of select="@rdf:about"/>
            </xsl:otherwise>
        </xsl:choose>
    </field>
  <xsl:variable name="callnumber"><xsl:value-of 
       select="(substring-after(substring-after(@rdf:about,'//'),'/'))"/>
  </xsl:variable>
  <field name="callnumber-raw"><xsl:value-of select="$callnumber"/></field>
  <field name="callnumber-sort"><xsl:value-of select="$callnumber"/></field>
  <field name="callnumber-label">
         <xsl:value-of select="translate($callnumber,'/',' ')"/></field>
  <field name="callnumber-first">
      <xsl:value-of select="substring-before($callnumber,'/')"/>
  </field>
  <field name="callnumber-subject">
      <xsl:value-of select="concat(substring-before($callnumber,'/'),' ',
                    substring-before(substring-after($callnumber,'/'),'/'))"/>
  </field>
</xsl:template>

<xsl:template match="*[starts-with(@rdf:about,'file')]" mode="call">
</xsl:template>

<!-- full record copy -->
<xsl:template match="dcterms:BibliographicResource|dctypes:Collection" mode="fullrecord">
    <xsl:value-of select="concat('&lt;', name(/rdf:RDF))" />
    <xsl:for-each select="namespace::*"><xsl:value-of 
            select="concat(' xmlns:',local-name(),'=&quot;',.,'&quot;')" />
    </xsl:for-each><xsl:value-of select="'&gt;'" />
    <xsl:text>
</xsl:text>
    <xsl:value-of select="concat('&lt;', name())" />
    <xsl:for-each select="@*">
        <xsl:value-of select="concat(' ', name(), '=&quot;', ., '&quot;')" />
    </xsl:for-each><xsl:value-of select="'&gt;'" />
    <xsl:apply-templates mode="fullrecord_"/>
    <xsl:value-of select="concat('&lt;/', name(), '&gt;')" />
    <xsl:value-of select="concat('&lt;/', name(/rdf:RDF), '&gt;')" />
</xsl:template>

<xsl:template match="*[not(self::dcterms:temporal)]" mode="fullrecord_">
    <xsl:value-of select="concat('&lt;', name())" />
    <xsl:for-each select="@*">
        <xsl:value-of select="concat(' ', name(), '=&quot;', ., '&quot;')" />
    </xsl:for-each><xsl:value-of select="'&gt;'" />
    <xsl:apply-templates mode="fullrecord_" />
    <xsl:value-of select="concat('&lt;/', name(), '&gt;')" />
    <xsl:text>
</xsl:text>
</xsl:template>

<xsl:template match="text()" mode="fullrecord_">
    <!-- Topics & Argument's was a mistake -->
    <!--
    <xsl:value-of select="translate(translate(., '&lt;', '['), '&gt;',']')"/>
    <xsl:value-of select="translate(translate(translate(., '&lt;', '['), '&gt;',']'),'&amp;','+')"/>
    <xsl:value-of disable-output-escaping="yes" select="translate(translate(., '&lt;', '['), '&gt;',']')"/>
    -->
    <xsl:value-of select="translate(translate(translate(., '&lt;', '['), '&gt;',']'),'&amp;','+')"/>
</xsl:template>

<!-- suppress emptyness -->
<xsl:template match="*|@*" priority="-1"/>
<xsl:template match="*" mode="fullrecord" priority="-1"/>
<xsl:template match="*" mode="fullrecord_" priority="-1"/>
<xsl:template match="*" mode="index" priority="-1"/>
<xsl:template match="*" mode="spec" priority="-1"/>
<xsl:template match="*" mode="self" priority="-1"/>
<xsl:template match="*" mode="parent" priority="-1"/>
<xsl:template match="*" mode="top" priority="-1"/>

</xsl:stylesheet>
