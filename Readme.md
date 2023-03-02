
## A VuFind Publishing module

### Create Database
  
    mysql> Create Database dpub3 CHARACTER SET utf8 COLLATE utf8_unicode_ci
    mysql> Grant all on dpub3.* to admin 

### Configure Dpub module for VuFind

    mv module/Dpub $VUFIND_HOME/module
    mv themes/dpub $VUFIND_HOME/themes
    cp -i config/vufind/* $VUFIND_LOCAL_DIR/config/

    mysql --login-path=admin dpub3 < module/Dpub/sql/create-data.sql 
    mysql --login-path=admin dpub3 < module/Dpub/sql/create-view.sql 

### Permissions

  - Database access : edit config/vufind/Dpub.ini
  - Filesystem access : "path/base" from opus_domain table 
    (e.g. /srv/archiv/adm/dpub) must be writable to server (e.g. www-data)

  - Metadata editing and pubishing: requires permission to AdminModule
  - Content streaming : requires permission StreamView

### Publishing

  - Use Dpub/Upload form to send a document

  - Use Dpub/Admin / see themes/dpub/templates/myresearch/menu.phtml
    as a user with AdminModule permission
    Edit metadata && Push the "publish"-Button
    Edit, Create Access-URL and push the "publish"-Button 


____________________________________________________________________________
