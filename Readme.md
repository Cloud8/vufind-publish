
## A VuFind Publishing module

### Configure 

    mv module/Dbib $VUFIND_HOME/module
    mv themes/dbib $VUFIND_HOME/themes
    cp -i config/vufind/* $VUFIND_LOCAL_DIR/config/

    mysql> Create Database dcterms CHARACTER SET utf8 COLLATE utf8_unicode_ci;
    mysql> Grant all on dcterms.* to admin;
    mysql --login-path=admin dcterms < module/Dbib/sql/create-data.sql 
    mysql --login-path=admin dcterms < module/Dbib/sql/create-view.sql 

### Permissions (see vufind/permissions.ini)

  - Database access : see config/vufind/Dbib.ini
  - Filesystem access : "path/base" from domain table 
    (e.g. /srv/archiv/adm/pub/dbib) must be writable (e.g. for www-data)

  - Metadata editing and publishing: requires permission to AdminModule
  - Content streaming : requires permission to StreamView (from permissions.ini)

### Publishing

  - Use Dbib/Upload form to send a document

  - Use Dbib/Admin (with Admin permission from permissions.ini)
    Edit metadata && Push the "publish"-Button
    Edit, Create Access-URL and push the "publish"-Button 

____________________________________________________________________________
