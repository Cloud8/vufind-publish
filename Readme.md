
## A VuFind Publishing module

### Configure 

    mv module/Dbib $VUFIND_HOME/module
    mv themes/dbib $VUFIND_HOME/themes
    cp -i config/vufind/* $VUFIND_LOCAL_DIR/config/

    mysql> Create Database dbib3 CHARACTER SET utf8 COLLATE utf8_unicode_ci;
    mysql> Grant all on dbib3.* to admin;
    mysql --login-path=admin dbib3 < module/Dbib/sql/create-data.sql 
    mysql --login-path=admin dbib3 < module/Dbib/sql/create-view.sql 

### Permissions (see vufind/permissions.ini)

  - Database access : see config/vufind/Dbib.ini
  - Filesystem access : "path/base" from domain table 
    (e.g. /srv/archiv/adm/dbib) must be writable (e.g. for www-data)

  - Metadata editing and pubishing: requires permission to AdminModule
  - Content streaming : requires permission to StreamView

### Publishing

  - Use Dbib/Upload form to send a document

  - Use Dbib/Admin (with Admin permission from permissions.ini)
    Edit metadata && Push the "publish"-Button
    Edit, Create Access-URL and push the "publish"-Button 

____________________________________________________________________________
