<?php
/**
 * SOURCE: MS SQL
 */
define('MSSQL_HOST','');
define('MSSQL_USER','');
define('MSSQL_PASSWORD','');
define('MSSQL_DATABASE','');

/**
 * DESTINATION: MySQL
 */
define('MYSQL_HOST', '');
define('MYSQL_USER', '');
define('MYSQL_PASSWORD','');
define('MYSQL_DATABASE','');

define('DEBUG', FALSE);

$tables_to_contain = [];
$tables_to_ignore = [];
$tables_data_to_contain = [];
$tables_data_to_ignore = [];

/*
 * SOME HELPER CONSTANT
 */
define('CHUNK_SIZE', 5000);

set_time_limit(0);
ini_set('memory_limit','1G');

function addTilde($string) {
  return "`".$string."`";
}

$mssql_connect = "sqlsrv:Server=" . MSSQL_HOST . ",1433;Database=" . MSSQL_DATABASE;
$mssql = new PDO($mssql_connect, MSSQL_USER, MSSQL_PASSWORD);
$mssql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mysql_connect = "mysql:host=" . MYSQL_HOST . ";dbname=" . MYSQL_DATABASE;
$mysql = new PDO($mysql_connect, MYSQL_USER, MYSQL_PASSWORD);
$mysql->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$mssql_tables = array();

// Get MS SQL tables
$sql = "SELECT * FROM sys.Tables where schema_id=1;";

echo "\n=> Getting tables..\n";
foreach ($mssql->query($sql) as $row) {
  if (!empty($tables_to_contain)) {
    foreach ($tables_to_contain as $ttc) {
      $doit = (str_contains($row['name'], $ttc)) ? TRUE : FALSE;
      if ($doit) {
        if (in_array($row['name'], $tables_to_ignore)) {
          continue;
        }
        array_push($mssql_tables, $row['name']);
      }
    }
  }
  else {
    array_push($mssql_tables, $row['name']);
  }
}
echo "==> Found ". number_format(count($mssql_tables)) ." tables\n\n";

// Get Table Structures
if (!empty($mssql_tables)) {
  foreach ($mssql_tables as $key => $table) {

    echo '====> ' . ($key + 1) . '. ' . $table . "\n";
    echo "=====> Getting info table " . $table . " from SQL Server\n";

    $mssql_query = "SELECT * FROM information_schema.columns WHERE table_name = '" . $table . "'";
    $mssql_rows = $mssql->query($mssql_query, PDO::FETCH_ASSOC)->fetchAll();

    if (!empty($mssql_rows)) {
      $mssql_tables[$table] = array();

      $mysql_query = "DROP TABLE IF EXISTS `" . $table . "`";
      $mysql->query($mysql_query);

      $mysql_query = "CREATE TABLE `" . $table . "`";
      $strctsql = $fields = $field_type = $field_integer = $field_null = array();

      foreach ($mssql_rows as $row) {
        $integer = FALSE;
        array_push($mssql_tables[$table], $row);

        switch ($row['DATA_TYPE']) {
          case 'bit':
            $data_type = 'bit';
            break;

          case 'tinyint':
            $integer = TRUE;
            $data_type = 'smallint';
            break;

          case 'smallint':
          case 'int':
          case 'bigint':
            $integer = TRUE;
            $data_type = $row['DATA_TYPE'] . (!empty($row['NUMERIC_PRECISION']) ? '(' . $row['NUMERIC_PRECISION'] . ')' : '' );
            break;
          
          case 'money':
            $data_type = 'decimal(19,4)';
            break;

          case 'smallmoney':
            $data_type = 'decimal(10,4)';
            break;

          case 'real':
          case 'float':
          case 'decimal':
          case 'numeric':
            $data_type = $row['DATA_TYPE'] . (!empty($row['NUMERIC_PRECISION']) ? '(' . $row['NUMERIC_PRECISION'] . (!empty($row['NUMERIC_SCALE']) ? ',' . $row['NUMERIC_SCALE'] : '').')' : '' );
            break;

          case 'date':
          case 'datetime':
          case 'timestamp':
          case 'time':
            $data_type = $row['DATA_TYPE'];
            break;

          case 'datetime2':
          case 'datetimeoffset':
          case 'smalldatetime':
            $data_type = 'datetime';
            break;

          case 'nchar':
          case 'char':
            $data_type = 'char' . (!empty($row['CHARACTER_MAXIMUM_LENGTH']) && $row['CHARACTER_MAXIMUM_LENGTH'] > 0 ? '(' . $row['CHARACTER_MAXIMUM_LENGTH'] . ')' : '(255)' );
            break;

          case 'nvarchar':
          case 'varchar':
            if ($row['CHARACTER_MAXIMUM_LENGTH'] < 1 || $row['CHARACTER_MAXIMUM_LENGTH'] > 255) {
              $data_type = 'longtext';
            }
            else {
              $data_type = 'varchar' . (!empty($row['CHARACTER_MAXIMUM_LENGTH']) && $row['CHARACTER_MAXIMUM_LENGTH'] > 0 ? '(' . $row['CHARACTER_MAXIMUM_LENGTH'] . ')' : '(255)' );
            }
            break;

          case 'ntext':
          case 'text':
            if ($row['CHARACTER_MAXIMUM_LENGTH'] > 65534) {
              $data_type = 'longblob';
            }
            else {
              $data_type = 'longtext';
            }
            break;

          case 'binary':
          case 'varbinary':
            $data_type = 'binary';
            break;

          case 'image':
          case 'sql_variant':
            $data_type = 'blob';
            break;

          case 'uniqueidentifier':
            $data_type = 'char(36)';
            break;

          case 'cursor':
          case 'hierarchyid':
          case 'table':
          case 'xml':
          default:
            print "Errant Data Type: " . $row['DATA_TYPE'] . "\n\n";
            $data_type = 'blob';
            break;
        }


        if (!empty($data_type)) {
          if ($data_type == 'binary' || $data_type == 'varbinary') {
            $ssql = "`" . $row['COLUMN_NAME'] . "` " . $data_type;
          }
          else {
            $ssql = "`" . $row['COLUMN_NAME'] . "` " . $data_type . " " . ($row['IS_NULLABLE'] == 'YES' ? 'NULL' : 'NOT NULL');
          }
          if (in_array($row['COLUMN_NAME'], $fields)) {
            unset($ssql);
            continue;
          }
          array_push($strctsql, $ssql);
          array_push($fields, $row['COLUMN_NAME']);  
          array_push($field_type, $row['DATA_TYPE']);
          array_push($field_integer, $integer);
          array_push($field_null, ($row['IS_NULLABLE'] == 'YES') ? TRUE : FALSE);
        }
      }

      $mysql_query .= "(" . implode(',', $strctsql) . ");";
      echo "======> Creating table " . $table . " on MySQL... \n";
      if (DEBUG) {
        print $mysql_query . "\n";
      }
      $res = $mysql->query($mysql_query);

      if (in_array($table, $tables_data_to_ignore)) {
        continue;
      }

      if (!empty($tables_data_to_contain)) {
        $doit = FALSE;
        foreach ($tables_data_to_contain as $tdtc) {
          if (str_contains($table, $tdtc)) {
            $doit = TRUE;
            break;
          }
        }
        if ($doit === FALSE) {
          continue;
        }
      }

      echo "=====> Getting data from table " . $table . " on SQL Server\n";
      $sql = "SELECT * FROM " . $table;
      $rows = $mssql->query($sql, PDO::FETCH_ASSOC)->fetchAll();
      $numrows = count($rows);
      echo "======> Found " . number_format($numrows) . " rows\n";

      if (!empty($rows)) {
        echo "=====> Inserting to table " . $table . " on MySQL\n";
        $numdata = 0;
        if (!empty($fields)) {
          $sfield = array_map('addTilde', $fields);
          foreach ($rows as $row) {
            $datas = array();
            foreach ($fields as $key => $field) {
              if ($field_integer[$key] === TRUE) {
                $ddata = (!empty($row[$field])) ? (int) $row[$field] : 0;
              }
              elseif ($field_type[$key] == 'bit') {
                $ddata = (!empty($row[$field])) ? "B'" . $row[$field] . "'" : "B'0'";
              }
              else {
                if ($field_null[$key]) {
                  $ddata = (!empty($row[$field])) ? $mysql->quote($row[$field]) : 'NULL';
                }
                else {
                  $ddata = (!empty($row[$field])) ? $mysql->quote($row[$field]) : "''";
                }
              }
              array_push($datas, $ddata);
            }

            if (!empty($datas)) {
              $mysql_query = "INSERT INTO `" . $table . "` (" . implode(',', $sfield) . ") VALUES (" . implode(',', $datas) . ");";
              if (DEBUG) {
                print $mysql_query . "\n\n";
              }
              $q = $mysql->query($mysql_query);
              $numdata += ($q ? 1 : 0 );
            }
            if ($numdata % CHUNK_SIZE == 0) {
              echo "===> " . number_format($numdata) . " data inserted so far\n";
            }
          }
        }
        echo "======> " . number_format($numdata) . " data inserted total\n\n";
      }
    }
  }
}
