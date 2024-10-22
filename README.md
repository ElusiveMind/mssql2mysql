# mssql2mysql #

Simple MSSQL Server to MySQL table converter using PHP CLI. Originally based off the excellent starting work [here](https://github.com/matriphe/mssql2mysql).

## Prerequisites ##

You will need to have the sqlserv driver for PHP PDO. I have done this on Ubuntu 22.04 and successfully used this script with PHP up to the latest release as of this writing which is PHP 8.3.

You can find instructions for installing this [here](https://learn.microsoft.com/en-us/sql/connect/php/installation-tutorial-linux-mac?view=sql-server-ver16#installing-on-ubuntu). My work for this inside of a Docker container can be viewed [HERE](https://github.com/ElusiveMind/mssql-ubuntu/blob/main/Dockerfile).

## Usage ##

Edit the MSSQL and MySQL *hostname*, *user*, *password*, and *database* section. Run the script from command line using PHP CLI.

#### Example:

1. Edit the file `mssql2mysql.php` using your favorite editor.

2. Change `MSSQL` and `MYSQL` variables:

    ```
    define('MSSQL_HOST','mssql_host');
    define('MSSQL_USER','mssql_user');
    define('MSSQL_PASSWORD','mssql_password');
    define('MSSQL_DATABASE','mssql_database');

    define('MYSQL_HOST', 'mysql_host');
    define('MYSQL_USER', 'mysql_user');
    define('MYSQL_PASSWORD','mysql_password');
    define('MYSQL_DATABASE','mysql_database');
    ```

3. Run the php script (make sure php is accessible in the path or environment variables)

  `php mssql2mysql.php`

## Limitations ##

Some of these limitations will be overcome in future versions of this script as it is under active development as of Oct 22, 2024. Pull requests and issue reports are welcome.

* Just converts tables
* No indexes
* No store procedure
* No triggers
* No views
* No Advanced MSSQL features
