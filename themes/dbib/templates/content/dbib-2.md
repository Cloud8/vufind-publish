
### Publikationsangaben

##### Datumsangaben
<!--
 | Datenbank     |  DCTerms             | Solr-Index      | Verwendung  |
 |---------------|----------------------|-----------------|------------:|
 | date_year     | dcterms:created      | publishDate     | Erstellt    |
 | date_creation | dcterms:issued       | publishDateSort | Publiziert  |
 | date_modified | dcterms:modified     | last_indexed    | Modifiziert |
 | date_accepted | dcterms:dateAccepted |                 | Prüfung     |
--> 
  
  * "Jahr der Fertigstellung" ist in der Regel das Jahr, in dem die Arbeit 
    verfasst wurde, bei Digitalisaten das Erscheinungsjahr des Originals.

  * Komplexe Erscheinungsverläufe wie 'Süddeutschland, 12. Jh., Ende; 15. Jh.' 
    können innerhalb der Beschreibung angegeben werden, für die Darstellung 
    ausgewertet wird der Text zwischen 'Erschienen:' und dem folgenden ' -'.

  * Bereichsangaben zur Herstellungszeit wie z.B. 18XX für das 19. Jhdt. sind 
    4-stellig und können so auch gespeichert werden, allerdings ist eine 
    Formularvalidierung nur für Zahlen möglich. Um dennoch die Eingabe solcher 
    Datumsangaben über das Web-Formular zu ermöglichen, werden 
    Herstellungsjahre wie '18XX' mit vorgestellter '11' als '1118' angezeigt, 
    aber als '18XX' gespeichert und indexiert. 

--------------------------------------------------------------------------
