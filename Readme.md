
## A VuFind Publishing module

### Configure Publishing module for VuFind

    mv module/Dpub $VUFIND_HOME/module
    mv themes/dpub $VUFIND_HOME/themes
    cp -i config/vufind/* $VUFIND_LOCAL_DIR/config/

    mysql> Create Database dpub3 CHARACTER SET utf8 COLLATE utf8_unicode_ci
    mysql> Grant all on dpub3.* to admin 
    mysql --login-path=admin dpub3 < module/Dpub/sql/create-data.sql 
    mysql --login-path=admin dpub3 < module/Dpub/sql/create-view.sql 

### Permissions (see vufind/permissions.ini)

  - Database access : see config/vufind/Dpub.ini
  - Filesystem access : "path/base" from domain table 
    (e.g. /srv/archiv/adm/dpub) must be writable (e.g. for www-data)

  - Metadata editing and pubishing: requires permission to AdminModule
  - Content streaming : requires permission to StreamView

### Publishing

  - Use Dpub/Upload form to send a document

  - Use Dpub/Admin (with Admin permission)
    Edit metadata && Push the "publish"-Button
    Edit, Create Access-URL and push the "publish"-Button 

____________________________________________________________________________
