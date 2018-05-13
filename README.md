# pgi
php class for PostgreSql database library, based on mysqli for MySql.

## DESCRIPTION
*The class is written for easy transition from MySQL to PostgreSQL. It was written because we were moving huge project
*It can also be used for new projects, if you want to use "mysqli" familiar syntax
*SQL binding is native to postgre php functions. So in contrast to mysqli, pgi isn't more secure than using normal php postgre functions (pg_*). Benefit of using this class is therefore only in structure of the code
*The class doesn't use all of mysqli properties. Below are described which ones are used (USAGE section)
*In contrast to mysqli, pgi class has additional methods, which allow using statements with unknow number of returned columns ``` SELECT * FROM table ```. (see USAGE section)

## TRANSITION
for transition from MySQL to PostgreSQL, simply change class initialization. For example:

*From ``` $db = new mysqli('localhost', 'my_user', 'my_password', 'my_db');  ```

*To ``` $db = new pgi('localhost', 'my_user', 'my_password', 'my_db');  ```

Then rename all tables, to include schema. For example:

*From ``` SELECT id FROM table_name ```

*To ``` SELECT id FROM schema_name.table_name ```

## TEST
Transition was successfully tested on two projects. In both cases there were only some minor issues, due to difference between logic. Only manual work was to rename all tables to include schema.

## USAGE
Below are listed all methods and described only with their difference to mysqli. For reference use mysqli docs http://php.net/manual/en/class.mysqli.php. 

### Global properties and methods
``` $db = new pgi('localhost', 'my_user', 'my_password', 'my_db'); ```
* ** $db->connect_errno ** Marks if there was error connecting to database
* ** $db->error ** text of last error. It can be set by php pg_* function or pgi class
* ** $db->insert_id **
* ** $db->set_charset($charset) **
* ** $db->prepare($sql_statement) **
* ** $db->real_escape_string($string) ** The difference is that return values will be wrapped in ```'```. For reference use http://php.net/manual/en/function.pg-escape-literal.php
* ** $db->check_connection($sql_statement) ** Checks if connection is still active (true/false)
* ** $db->reset_connection($sql_statement) **

### Statement properties and methods
``` $stmt = $db->prepare("SELECT id FROM table_name"); ```
* ** $stmt->num_rows **
* ** $stmt->affected_rows **
* ** $stmt->bind_param($types, ...$params) ** $types are converted manualy, because postgre converts them to string. supported are "sidb"
* ** $stmt->execute() **
* ** $stmt->store_result($with_buffer) ** added parameter $with_buffer. If set to TRUE (and result count is less than 60000) then all rows are fetched instantly and stored in php memory
* ** $stmt->bind_result(...$row_column) **
* ** $stmt->bind_asocciative_result($row_name) ** Extra method. Use instead of bind_result method, if you want results to be returned in associative array, or if you have unknown number of columns
* ** $stmt->fetch() **
* ** $stmt->close() **


## STRUCTURE
*PGI class is split on two classes, pgi and pgi_stmt.
