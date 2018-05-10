<?php

/**
 * Project:     Bright framework
 * Author:      Jager Mesh (jagermesh@gmail.com)
 *
 * @version 1.1.0.0
 * @package Bright Core
 */

require_once(__DIR__.'/BrGenericDBProvider.php');

class BrGenericSQLDBProvider extends BrGenericDBProvider {

  private $__inTransaction = 0;
  private $__transactionBuffer = array();
  private $__deadlocksHandlerEnabled = true;

  protected $version;

  public function connection() {

  }

  public function startTransaction($force = false) {

    $this->resetTransaction();
    $this->__inTransaction++;

  }

  public function commitTransaction($force = false) {

    $this->resetTransaction();

  }

  public function rollbackTransaction($force = false) {

    $this->resetTransaction();

  }

  function incTransactionBuffer($sql) {

    if (!preg_match('/^SET( |$)/', $sql)) {
      if (!preg_match('/^SELECT( |$)/', $sql)) {
        if (!preg_match('/^CALL( |$)/', $sql)) {
          $this->__transactionBuffer[] = $sql;
        }
      }
    }

  }

  function internalRunQuery($sql, $args = array(), $iteration = 0, $rerunError = null) {

  }

  public function resetTransaction() {

    $this->__inTransaction = 0;
    $this->__transactionBuffer = array();

  }

  public function inTransaction() {

    return ($this->__inTransaction > 0);

  }

  public function isTransactionBufferEmpty() {

    return (count($this->__transactionBuffer) == 0);

  }

  public function transactionBufferLength() {

    return count($this->__transactionBuffer);

  }

  public function transactionBuffer() {

    return $this->__transactionBuffer;

  }

  public function disableDeadLocksHandler() {

    $this->__deadlocksHandlerEnabled = false;
    return $this->__deadlocksHandlerEnabled;

  }

  public function enableDeadLocksHandler() {

    $this->__deadlocksHandlerEnabled = true;
    return $this->__deadlocksHandlerEnabled;

  }

  public function isDeadLocksHandlerEnabled() {

    return $this->__deadlocksHandlerEnabled;

  }

  public function regexpCondition($value) {

    return new BrGenericSQLRegExp($value);

  }

  public function rowid($row, $fieldName = null) {

    if (is_array($row)) {
      return br($row, $fieldName ? $fieldName : $this->rowidField());
    } else {
      return $row;
    }

  }

  public function rowidField() {

    return 'id';

  }

  public function rowidValue($row, $fieldName = null) {

    if (is_array($row)) {
      return br($row, $fieldName ? $fieldName : $this->rowidField());
    } else {
      return $row;
    }

  }

  public function table($name, $alias = null, $params = array()) {

    $params['tableAlias'] = $alias;

    return new BrGenericSQLProviderTable($this, $name, $params);

  }


  function parseScript($script) {

    $result = array();
    $delimiter = ';';
    while(strlen($script) && preg_match('/((DELIMITER)[ ]+([^\n\r])|[' . $delimiter . ']|$)/is', $script, $matches, PREG_OFFSET_CAPTURE)) {
      if (count($matches) > 2) {
        $delimiter = $matches[3][0];
        $script = substr($script, $matches[3][1] + 1);
      } else {
        if (strlen($statement = trim(substr($script, 0, $matches[0][1])))) {
          $result[] = $statement;
        }
        $script = substr($script, $matches[0][1] + 1);
      }
    }

    return $result;

  }


  public function runScript($script) {

    if ($statements = $this->parseScript($script)) {
      foreach($statements as $statement) {
        $this->internalRunQuery($statement);
      }
    }

    return true;

  }

  public function runScriptFile($fileName) {

    $result = 0;

    if (file_exists($fileName)) {
      if ($script = br()->fs()->loadFromFile($fileName)) {
        return $this->runScript($script);
      } else {
        throw new Exception('Script file empty: ' . $fileName);
      }
    } else {
      throw new Exception('Script file not found: ' . $fileName);
    }

  }

  public function runQuery() {

    $args = func_get_args();
    $sql = array_shift($args);

    return $this->internalRunQuery($sql, $args);

  }

  public function openCursor() {

    $args = func_get_args();
    $sql = array_shift($args);

    return $this->internalRunQuery($sql, $args);

  }

  public function getCursor() {

    $args = func_get_args();
    $sql = array_shift($args);

    return new BrGenericSQLProviderCursor($sql, $args, $this, true);

  }

  public function select() {

    $args = func_get_args();
    $sql = array_shift($args);

    return $this->internalRunQuery($sql, $args);

  }

  public function getRow() {

    $args = func_get_args();
    $sql = array_shift($args);

    return $this->selectNext($this->internalRunQuery($sql, $args));

  }

  public function getCachedRow() {

    $args = func_get_args();
    $sql = array_shift($args);

    $cacheTag = get_class($this) . ':getCachedRow:' . md5($sql) . md5(serialize($args));

    $result = br()->cache()->getEx($cacheTag);
    if ($result['success']) {
      $result = $result['value'];
    } else {
      $result = $this->selectNext($this->internalRunQuery($sql, $args));
      br()->cache()->set($cacheTag, $result);
    }

    return $result;

  }

  public function getRows() {

    $args = func_get_args();
    $sql = array_shift($args);

    $query = $this->internalRunQuery($sql, $args);
    $result = array();
    if (is_object($query) || is_resource($query)) {
      while($row = $this->selectNext($query)) {
        $result[] = $row;
      }
    }

    return $result;

  }

  public function getCachedRows() {

    $args = func_get_args();
    $sql = array_shift($args);

    $cacheTag = get_class($this) . ':getCachedRows:' . md5($sql) . md5(serialize($args));

    $result = br()->cache()->getEx($cacheTag);
    if ($result['success']) {
      $result = $result['value'];
    } else {
      $query = $this->internalRunQuery($sql, $args);
      $result = array();
      if (is_object($query) || is_resource($query)) {
        while($row = $this->selectNext($query)) {
          $result[] = $row;
        }
      }
      br()->cache()->set($cacheTag, $result);
    }

    return $result;

  }

  public function getValue() {

    $args = func_get_args();
    $sql = array_shift($args);

    $result = $this->selectNext($this->internalRunQuery($sql, $args));
    if (is_array($result)) {
      return array_shift($result);
    } else {
      return null;
    }

  }

  public function getCachedValue() {

    $args = func_get_args();
    $sql = array_shift($args);

    $cacheTag = get_class($this) . ':getCachedValue:' . md5($sql) . md5(serialize($args));

    $result = br()->cache()->getEx($cacheTag);
    if ($result['success']) {
      $result = $result['value'];
    } else {
      if ($result = $this->selectNext($this->internalRunQuery($sql, $args))) {
        if (is_array($result)) {
          $result = array_shift($result);
        } else {
          $result = null;
        }
      } else {
        $result = null;
      }
      br()->cache()->set($cacheTag, $result);
    }

    return $result;

  }

  public function getValues() {

    $args = func_get_args();
    $sql = array_shift($args);

    $query = $this->internalRunQuery($sql, $args);
    $result = array();
    if (is_object($query) || is_resource($query)) {
      while($row = $this->selectNext($query)) {
        array_push($result, array_shift($row));
      }
    }

    return $result;

  }

  public function getCachedValues() {

    $args = func_get_args();
    $sql = array_shift($args);

    $cacheTag = get_class($this) . ':getCachedValues:' . md5($sql) . md5(serialize($args));

    $result = br()->cache()->getEx($cacheTag);
    if ($result['success']) {
      $result = $result['value'];
    } else {
      $query = $this->internalRunQuery($sql, $args);
      $result = array();
      if (is_object($query) || is_resource($query)) {
        while($row = $this->selectNext($query)) {
          array_push($result, array_shift($row));
        }
      }
      br()->cache()->set($cacheTag, $result);
    }

    return $result;

  }

  protected function getCountSQL($sql) {

    return 'SELECT COUNT(1) FROM (' . $sql . ') a';

  }

  function internalGetRowsAmount($sql, $args) {

  }

  function getRowsAmount() {

    $args = func_get_args();
    $sql = array_shift($args);

    return $this->internalGetRowsAmount($sql, $args);

  }

  function toGenericDataType($type) {

    switch (strtolower($type)) {
      case "date";
        return "date";
      case "datetime":
      case "timestamp":
        return "date_time";
      case "time";
        return "time";
      case "int":
      case "smallint":
      case "integer":
      case "int64":
      case "long":
      case "long binary":
      case "tinyint":
        return "int";
      case "real":
      case "numeric":
      case "double":
      case "float":
        return "real";
      case "string":
      case "text":
      case "blob":
      case "varchar":
      case "char":
      case "long varchar":
      case "varying":
        return "text";
      default:
        return 'unknown';
        break;
    }

  }

  function command($command) {

    $this->runQuery($command);

  }

  function getLimitSQL($sql, $from, $count) {

    if (!is_numeric($from)) {
      $from = 0;
    } else {
      $from = number_format($from, 0, '', '');
    }
    if (!is_numeric($count)) {
      $count = 0;
    } else {
      $count = number_format($count, 0, '', '');
    }
    return $sql.br()->placeholder(' LIMIT ?, ?', $from, $count);

  }

  function getMajorVersion() {

    if (preg_match('~^([0-9]+)[.]([0-9]+)[.]([0-9]+)~', $this->version, $matches)) {
      return (int)$matches[1];
    }

    return 0;

  }

  function getMinorVersion() {

    if (preg_match('~^([0-9]+)[.]([0-9])[.]([0-9]+)~', $this->version, $matches)) {
      return (int)$matches[2];
    }

    return 0;

  }

  function getBuildNumber() {

    if (preg_match('~^([0-9]+)[.]([0-9]+)[.]([0-9]+)~', $this->version, $matches)) {
      return (int)$matches[3];
    }

    return 0;

  }

}

class BrGenericSQLRegExp {

  private $value;

  public function __construct($value) {

    $this->value = $value;

  }

  public function getValue() {

    return $this->value;

  }

}

class BrGenericSQLProviderCursor implements Iterator {

  private $sql, $args, $provider, $position = -1, $query, $row, $limit, $skip;

  public function __construct($sql, $args, &$provider) {

    $this->sql = $sql;
    $this->args = $args;
    $this->provider = $provider;
    $this->position = -1;

  }

  // Interface methods

  function current() {

    return $this->row;

  }

  function key() {

    return $this->position;

  }

  function next() {

    $this->row = $this->provider->selectNext($this->query);
    $this->position++;

    return $this->row;

  }

  function rewind() {

    $this->getData();
    $this->position = 0;

    return $this;

  }

  function valid() {

    return $this->row;

  }

  // End of interface methods

  function limit($limit) {

    $this->limit = $limit;

    return $this;

  }

  function skip($skip) {

    $this->skip = $skip;

    return $this;

  }

  function sort($order) {

    if ($order) {
      $sql = ' ORDER BY ';
      foreach($order as $field => $direction) {
        $sql .= $field . ' ' . ($direction == 1?'ASC':'DESC') .', ';
      }
      $sql = rtrim($sql, ', ');
      $this->sql .= $sql;
    }

    return $this;

  }

  function group($order) {

    if ($order) {
      $sql = ' GROUP BY ';
      foreach($order as $field) {
        $sql .= $field . ', ';
      }
      $sql = rtrim($sql, ', ');
      $this->sql .= $sql;
    }

    return $this;

  }

  function having($having) {

    if ($having) {
      $sql = ' HAVING ' . br($having)->join(' AND ');
      $this->sql .= $sql;
    }

    return $this;

  }

  function count() {

    return $this->provider->internalGetRowsAmount($this->sql, $this->args);

  }

  function getStatement() {

    $sql = $this->sql;
    if (strlen($this->limit)) {
      $sql = $this->provider->getLimitSQL($sql, $this->skip, $this->limit);
    }

    return array('sql' => $sql, 'args' => $this->args);

  }

  function getSQL() {

    $sql = $this->sql;
    if (strlen($this->limit)) {
      $sql = $this->provider->getLimitSQL($sql, $this->skip, $this->limit);
    }
    if ($this->args) {
      return br()->placeholderEx($sql, $this->args, $error);
    } else {
      return $sql;
    }

  }

  // private

  private function getData() {

    if ($this->position == -1) {
      if (strlen($this->limit)) {
        $this->sql = $this->provider->getLimitSQL($this->sql, $this->skip, $this->limit);
      }
      $this->query = $this->provider->internalRunQuery($this->sql, $this->args);
      $this->row = $this->provider->selectNext($this->query);
      $this->position = 0;
    }

  }

}

class BrGenericSQLProviderTable {

  private $tableName;
  private $tableAlias;
  private $indexHint;
  private $provider;

  function __construct(&$provider, $tableName, $params = array()) {

    $this->tableName  = $tableName;
    $this->tableAlias = br($params, 'tableAlias');
    $this->indexHint  = br($params, 'indexHint');
    $this->provider   = $provider;

  }

  private function compileJoin($filter, $tableName, $fieldName, $link, &$joins, &$joinsTables, &$where, &$args, $joinType = 'INNER') {

    $first = true;
    $initialJoinTableName = '';
    foreach($filter as $joinTableName => $joinField) {
      if ($first) {
        $initialJoinTableName = $joinTableName;
        if (in_array($joinTableName, $joinsTables)) {
          // already joined
        } else {
          $joinsTables[] = $joinTableName;
          $tmp = br($joinTableName)->split(' ');
          if (count($tmp) > 1) {
            $joinTableName = $tmp[0];
            $joinTableAlias = $tmp[1];
          } else {
            $joinTableAlias = $joinTableName;
          }
          if (is_array($joinField)) {
            if (br($joinField, '$sql')) {
              $joins .= ' ' . $joinType . ' JOIN ' . $joinTableName . ' ' . $joinTableAlias . ' ON ' . $joinField['$sql'];
            } else {
              throw new Exception('Wrong join format');
            }
          } else {
            if (strpos($fieldName, '.') === false) {
              $joins .= ' ' . $joinType . ' JOIN ' . $joinTableName . ' ' . $joinTableAlias . ' ON ' . $tableName . '.' . $fieldName . ' = ' . $joinTableAlias . '.' . $joinField;
            } else {
              $joins .= ' ' . $joinType . ' JOIN ' . $joinTableName . ' ' . $joinTableAlias . ' ON ' . $fieldName . ' = ' . $joinTableAlias . '.' . $joinField;
            }
          }
        }
        $first = false;
      } else {
        if (is_numeric($joinTableName)) {
          foreach($joinField as $a => $b) {
            $joinTableName = $a;
            $joinField = $b;
          }
        }
        if (strpos($joinTableName, '.') === false) {
          $joinLeftPart = $initialJoinTableName . '.' . $joinTableName;
        } else {
          $joinLeftPart = $joinTableName;
        }
        if (is_array($joinField)) {
          if (br()->isRegularArray($joinField)) {
            $joins .= br()->placeholder(' AND ' . $joinLeftPart . ' IN (?@)', $joinField);
          } else {
            foreach($joinField as $operation => $joinFieldNameOrValue) {
              $operation = (string)$operation;
              switch($operation) {
                case '$lt':
                case '<':
                  $joins .= ' AND ' . $joinLeftPart . ' < ' . $joinFieldNameOrValue;
                  break;
                case '$lte':
                case '<=':
                  $joins .= ' AND ' . $joinLeftPart . ' <= ' . $joinFieldNameOrValue;
                  break;
                case '$gt':
                case '>':
                  $joins .= ' AND ' . $joinLeftPart . ' > ' . $joinFieldNameOrValue;
                  break;
                case '$gte':
                case '>=':
                  $joins .= ' AND ' . $joinLeftPart . ' >= ' . $joinFieldNameOrValue;
                  break;
                case '$in':
                  if (is_array($joinFieldNameOrValue)) {
                    $joinFieldNameOrValue = br()->removeEmptyValues($joinFieldNameOrValue);
                    if ($joinFieldNameOrValue) {
                      $joins .= br()->placeholder(' AND ' . $joinLeftPart . ' IN (?@)', $joinFieldNameOrValue);
                    } else {
                      $joins .= ' AND ' . $joinLeftPart . ' IN (NULL)';
                    }
                  } else
                  if (strlen($joinFieldNameOrValue) > 0) {
                    $joins .= ' AND ' . $joinLeftPart . ' IN (' . $joinFieldNameOrValue . ')';
                  } else {
                    $joins .= ' AND ' . $joinLeftPart . ' IN (NULL)';
                  }
                  break;
                case '$nin':
                  if (is_array($joinFieldNameOrValue)) {
                    $joinFieldNameOrValue = br()->removeEmptyValues($joinFieldNameOrValue);
                    if ($joinFieldNameOrValue) {
                      $joins .= br()->placeholder(' AND ' . $joinLeftPart . ' NOT IN (?@)', $joinFieldNameOrValue);
                    } else {
                      $joins .= ' AND ' . $joinLeftPart . ' NOT IN (NULL)';
                    }
                  } else
                  if (strlen($joinFieldNameOrValue) > 0) {
                    $joins .= ' AND ' . $joinLeftPart . ' NOT IN (' . $joinFieldNameOrValue . ')';
                  } else {
                    $joins .= ' AND ' . $joinLeftPart . ' NOT IN (NULL)';
                  }
                  break;
                case '$eq':
                case '=':
                  if (is_array($joinFieldNameOrValue)) {
                    $joinFieldNameOrValue = br()->removeEmptyValues($joinFieldNameOrValue);
                    if ($joinFieldNameOrValue) {
                      $joins .= br()->placeholder(' AND ' . $joinLeftPart . ' IN (?@)', $joinFieldNameOrValue);
                    } else {
                      $joins .= ' AND ' . $joinLeftPart . ' IN (NULL)';
                    }
                  } else
                  if (strlen($joinFieldNameOrValue) > 0) {
                    $joins .= ' AND ' . $joinLeftPart . ' = ' . $joinFieldNameOrValue;
                  } else {
                    $joins .= ' AND ' . $joinLeftPart . ' IS NULL';
                  }
                  break;
                case '$ne':
                case '!=':
                  if (is_array($joinFieldNameOrValue)) {
                    $joinFieldNameOrValue = br()->removeEmptyValues($joinFieldNameOrValue);
                    if ($joinFieldNameOrValue) {
                      $joins .= br()->placeholder(' AND ' . $joinLeftPart . ' NOT IN (?@)', $joinFieldNameOrValue);
                    } else {
                      $joins .= ' AND ' . $joinLeftPart . ' NOT IN (NULL)';
                    }
                  } else
                  if (strlen($joinFieldNameOrValue) > 0) {
                    $joins .= ' AND ' . $joinLeftPart . ' != ' . $joinFieldNameOrValue;
                  } else {
                    $joins .= ' AND ' . $joinLeftPart . ' NOT NULL';
                  }
                  break;
              }
            }
          }
        } else {
          $joins .= ' AND '.$joinLeftPart.' = '.$joinField;
        }
      }
    }

  }

  private function compileExists($filter, $tableName, $fieldName, $link, &$joins, &$joinsTables, &$where, &$args) {

    $where .= $link.' EXISTS (';
    if (is_array($filter)) {
      if ($existsSql = br($filter, '$sql')) {
        $where .= str_replace('$', $tableName, $existsSql) . ')';
      }
    } else {
      $where .= str_replace('$', $tableName, $filter) . ')';
    }

  }

  private function compileNotExists($filter, $tableName, $fieldName, $link, &$joins, &$joinsTables, &$where, &$args) {

    $where .= $link.' NOT EXISTS (';
    if (is_array($filter)) {
      if ($existsSql = br($filter, '$sql')) {
        $where .= str_replace('$', $tableName, $existsSql) . ')';
      }
    } else {
      $where .= str_replace('$', $tableName, $filter) . ')';
    }

  }

  private function compileFilter($filter, $tableName, $fieldName, $link, &$joins, &$joinsTables, &$where, &$args) {

    foreach($filter as $currentFieldName => $filterValue) {
      $currentFieldName = (string)$currentFieldName;
      if (strpos($currentFieldName, '.') === false) {
        $fname = $tableName.'.'.$currentFieldName;
      } else {
        $fname = $currentFieldName;
      }
      if (preg_match('~^[@]~', $fieldName)) {
        $fname2 = ltrim($fieldName, '@');
      } else
      if (strpos($fieldName, '.') === false) {
        $fname2 = $tableName.'.'.$fieldName;
      } else {
        $fname2 = $fieldName;
      }
      switch($currentFieldName) {
        // FUCKING BUG! 0 = '$and' //
        case '$and':
          $where .= $link . ' ( 1=1 ';
          $this->compileFilter($filterValue, $tableName, '', ' AND ', $joins, $joinsTables, $where, $args);
          $where .= ' ) ';
          break;
        case '$andNot':
          $where .= $link . ' NOT ( 1=1 ';
          $this->compileFilter($filterValue, $tableName, '', ' AND ', $joins, $joinsTables, $where, $args);
          $where .= ' ) ';
          break;
        case '$or':
          $where .= $link . ' ( 1=2 ';
          $this->compileFilter($filterValue, $tableName, '', ' OR ', $joins, $joinsTables, $where, $args);
          $where .= ' ) ';
          break;
        case '$exists':
          $this->compileExists($filterValue, $tableName, '', $link, $joins, $joinsTables, $where, $args);
          break;
        case '$sql':
          $where .= $link . ' (' . $filterValue . ')';
          break;
        case '$notExists':
          $this->compileNotExists($filterValue, $tableName, '', $link, $joins, $joinsTables, $where, $args);
          break;
        case '$join':
          $this->compileJoin($filterValue, $tableName, $fieldName, $link, $joins, $joinsTables, $where, $args, 'INNER');
          break;
        case '$leftJoin':
          $this->compileJoin($filterValue, $tableName, $fieldName, $link, $joins, $joinsTables, $where, $args, 'LEFT');
          break;
        case '$in':
          if (is_array($filterValue)) {
            $filterValue = br()->removeEmptyValues($filterValue);
            if ($filterValue) {
              $where .= $link . $fname2 . ' IN (?@)';
              $args[] = $filterValue;
            } else {
              $where .= $link . $fname2 . ' IN (NULL)';
            }
          } else
          if (strlen($filterValue) > 0) {
            // we assuming it's SQL statement
            $where .= $link . $fname2 . ' IN (' . $filterValue . ')';
          } else {
            $where .= $link . $fname2 . ' IN (NULL)';
          }
          break;
        case '$nin':
          if (is_array($filterValue)) {
            $filterValue = br()->removeEmptyValues($filterValue);
            if ($filterValue) {
              $where .= $link . $fname2 . ' NOT IN (?@)';
              $args[] = $filterValue;
            } else {
              $where .= $link . $fname2 . ' NOT IN (NULL)';
            }
          } else
          if (strlen($filterValue) > 0) {
            // we assuming it's SQL statement
            $where .= $link . $fname2 . ' NOT IN (' . $filterValue . ')';
          } else {
            $where .= $link . $fname2 . ' NOT IN (NULL)';
          }
          break;
        case '$eq':
        case '=':
          if (is_array($filterValue)) {
            $filterValue = br()->removeEmptyValues($filterValue);
            if ($filterValue) {
              $where .= $link . $fname2 . ' IN (?@)';
              $args[] = $filterValue;
            } else {
              $where .= $link . $fname2 . ' IN (NULL)';
            }
          } else
          if (strlen($filterValue)) {
            $where .= $link . $fname2 . ' = ?';
            $args[] = $filterValue;
          } else {
            $where .= $link . $fname2 . ' IS NULL';
          }
          break;
        case '$ne':
        case '!=':
          if (is_array($filterValue)) {
            $filterValue = br()->removeEmptyValues($filterValue);
            if ($filterValue) {
              $where .= $link . $fname2 . ' NOT IN (?@)';
              $args[] = $filterValue;
            } else {
              $where .= $link . $fname2 . ' NOT IN (NULL)';
            }
          } else
          if (strlen($filterValue)) {
            $where .= $link . $fname2 . ' != ?';
            $args[] = $filterValue;
          } else {
            $where .= $link . $fname2 . ' IS NOT NULL';
          }
          break;
        case '$nn':
          $where .= $link . $fname2 . ' IS NOT NULL';
          break;
        case '$gt':
        case '>':
          $where .= $link . $fname2 . ' > ?';
          $args[] = $filterValue;
          break;
        case '$gte':
        case '>=':
          $where .= $link . $fname2 . ' >= ?';
          $args[] = $filterValue;
          break;
        case '$lt':
        case '<':
          $where .= $link . $fname2 . ' < ?';
          $args[] = $filterValue;
          break;
        case '$lte':
        case '<=':
          $where .= $link . $fname2 . ' <= ?';
          $args[] = $filterValue;
          break;
        case '$like':
          $where .= $link . $fname2 . ' LIKE ?';
          $args[] = $filterValue;
          break;
        case '$contains':
          if (is_array($filterValue)) {
            $where .= $link . '(1=2 ';
            foreach($filterValue as $name => $value) {
              if (strpos($name, '.') === false) {
                $tmpFName2 = $tableName.'.'.$name;
              } else {
                $tmpFName2 = $name;
              }
              $where .= ' OR ' . $tmpFName2 . ' LIKE ?';
              $args[] = '%'.$value.'%';
            }
            $where .= ')';
          } else {
            $where .= $link . $fname2 . ' LIKE ?';
            $args[] = '%'.$filterValue.'%';
          }
          break;
        case '$fulltext':
          if (is_array($filterValue)) {
            $tmpFName = '';
            $tmpValue = '';
            foreach($filterValue as $name => $value) {
              if (strpos($name, '.') === false) {
                $tmpFName2 = $tableName.'.'.$name;
              } else {
                $tmpFName2 = $name;
              }
              $tmpFName = br($tmpFName)->inc($tmpFName2);
              $tmpValue = $value;
            }
            $where .= $link . 'MATCH (' . $tmpFName . ') AGAINST (? IN BOOLEAN MODE)';
            $filterValue = $tmpValue;
          } else {
            $where .= $link . 'MATCH (' . $fname2 . ') AGAINST (? IN BOOLEAN MODE)';
          }
          $filterValue = preg_replace('~[@()]~', ' ', $filterValue);
          $args[] = $filterValue;
          break;
        case '$starts':
          $where .= $link . $fname2 . ' LIKE ?';
          $args[] = $filterValue.'%';
          break;
        case '$ends':
          $where .= $link . $fname2 . ' LIKE ?';
          $args[] = '%'.$filterValue;
          break;
        case '$regexp':
          $where .= $link . $fname2 . ' REGEXP ?&';
          $args[] = preg_replace('~([?*+\(\)])~', '[$1]', str_replace('\\', '\\\\', rtrim(ltrim($filterValue, '/'), '/i')));
          break;
        default:
          if (is_array($filterValue)) {
            if ($currentFieldName && br()->isRegularArray($filterValue)) {
              $filterValue = br()->removeEmptyValues($filterValue);
              if ($filterValue) {
                $where .= $link . $fname . ' IN (?@)';
                $args[] = $filterValue;
              } else {
                $where .= $link . $fname . ' IS NULL';
              }
            } else {
              $this->compileFilter($filterValue, $tableName, is_numeric($currentFieldName) ? $fieldName : $currentFieldName, $link, $joins, $joinsTables, $where, $args);
            }
          } else {
            if (is_object($filterValue) && ($filterValue instanceof BrGenericSQLRegExp)) {
              $where .= $link.$fname.' REGEXP ?&';
              $args[] = preg_replace('~([?*+\(\)])~', '[$1]', str_replace('\\', '\\\\', rtrim(ltrim($filterValue->getValue(), '/'), '/i')));
            } else {
              if (strlen($filterValue) > 0) {
                if (is_numeric($currentFieldName)) {
                  $where .= $link.$fname2.' = ?';
                } else {
                  $where .= $link.$fname.' = ?';
                }
                $args[] = $filterValue;
              } else {
                if (is_numeric($currentFieldName)) {
                  $where .= $link.$fname2.' IS NULL';
                } else {
                  $where .= $link.$fname.' IS NULL';
                }
              }
            }
          }
          break;
      }

    }
  }

  function find($filter = array(), $fields = array(), $distinct = false) {

    $where = '';
    $joins = '';
    $joinsTables = array();
    $args = array();

    $filter = array('$and' => $filter);

    if ($this->tableAlias) {
      $tableName = $this->tableAlias;
    } else {
      $tableName = $this->tableName;
    }

    $this->compileFilter($filter, $tableName, '', ' AND ', $joins, $joinsTables, $where, $args);

    $sql = 'SELECT ';
    if ($distinct) {
      $sql .= ' DISTINCT ';
    }
    if ($fields) {
      foreach($fields as $name => $rule) {
        if (is_numeric($name)) {
          $sql .= $tableName.'.'.$rule.',';
        } else {
          $sql .= str_replace('$', $tableName, $rule).' '.$name.',';
        }
      }
      $sql = rtrim($sql, ',').' ';
    } else {
      $sql = 'SELECT '.$tableName.'.* ';
    }

    $sql .= ' FROM '.$this->tableName;
    if ($this->tableAlias) {
      $sql .= ' ' . $this->tableAlias;
    }
    if ($this->indexHint) {
      $sql .= ' FORCE INDEX (' . $this->indexHint . ')';
    }
    $sql .= $joins.' WHERE 1=1 '.$where;

    return new BrGenericSQLProviderCursor($sql, $args, $this->provider);

  }

  function remove($filter) {

    $where = '';
    $joins = '';
    $args = array();

    if ($filter) {
      if (is_array($filter)) {
        $joinsTables = array();
        $filter = array('$and' => $filter);
        $this->compileFilter($filter, $this->tableName, '', ' AND ', $joins, $joinsTables, $where, $args);
      } else {
        $where .= ' AND ' . $this->provider->rowidField() . ' = ?';
        $args[] = $filter;
      }
    } else {
      throw new Exception('It is not allowed to invoke remove method without passing filter condition');
    }

    if ($where) {
      $sql = 'DELETE ';
      $sql .= ' FROM '.$this->tableName.$joins.' WHERE 1=1 '.$where;
    } else {
      throw new Exception('It is not allowed to invoke remove method without passing filter condition');
    }

    return $this->provider->internalRunQuery($sql, $args);

  }

  function findOne($filter = array()) {

    if ($rows = $this->find($filter)) {
      foreach($rows as $row) {
        return $row;
      }
    }
  }

  function save($values, $dataTypes = null) {

    $fields_str = '';
    $values_str = '';

    $sql = 'UPDATE '.$this->tableName.' SET ';
    foreach($values as $field => $value) {
      if ($field != $this->provider->rowidField()) {
        $sql .= $field . ' = ?';
        if (is_array($dataTypes)) {
          if (br($dataTypes, $field) == 's') {
            $sql .= '&';
          }
        }
        $sql .= ', ';
      }
    }
    $sql = rtrim($sql, ', ');
    $sql .= ' WHERE ' . $this->provider->rowidField() . ' = ?';

    $args = array();
    $key = null;
    foreach($values as $field => $value) {
      if ($field != $this->provider->rowidField()) {
        array_push($args, $value);
      } else {
        $key = $value;
      }
    }
    array_push($args, $key);

    $this->provider->internalRunQuery($sql, $args);

    return $values[$this->provider->rowidField()];

  }

  function update($values, $filter, $dataTypes = null) {

    $fields_str = '';
    $values_str = '';

    $sql = 'UPDATE '.$this->tableName.' SET ';
    foreach($values as $field => $value) {
      if ($field != $this->provider->rowidField()) {
        $sql .= $field . ' = ?';
        if (is_array($dataTypes)) {
          if (br($dataTypes, $field) == 's') {
            $sql .= '&';
          }
        }
        $sql .= ', ';
      }
    }
    $sql = rtrim($sql, ', ');
    $sql .= ' WHERE ';

    $where = '';

    if ($filter) {
      if (is_array($filter)) {
        foreach($filter as $field => $value) {
          if ($where) {
            $where .= ' AND ';
          }
          $where .= $field . ' = ?';
        }
      } else {
        $where .= $this->provider->rowidField() . ' = ?';
      }
    } else {
      throw new Exception('It is not allowed to invoke update method without passing filter condition');
    }

    if ($where) {
      $sql .= $where;
    } else {
      throw new Exception('Update without WHERE statements are not supported');
    }

    $args = array();

    foreach($values as $field => $value) {
      if ($field != $this->provider->rowidField()) {
        array_push($args, $value);
      }
    }

    if (is_array($filter)) {
      foreach($filter as $field => $value) {
        array_push($args, $value);
      }
    } else
    if ($filter) {
      array_push($args, $filter);
    }

    $this->provider->internalRunQuery($sql, $args);

    return true;//$filter;//$values[$this->provider->rowidField()];

  }

  function insertIgnore(&$values, $dataTypes = null, $fallbackSql = null) {

    $fields_str = '';
    $values_str = '';

    if ($dataTypes) {
      if (!is_array($dataTypes)) {
        $fallbackSql = $dataTypes;
        $dataTypes = null;
      }
    }

    foreach($values as $field => $value) {
      if (is_array($value)) {

      }
      $fields_str .= ($fields_str?',':'').$field;
      $values_str .= ($values_str?',':'').'?';
      if (is_array($dataTypes)) {
        if (br($dataTypes, $field) == 's') {
          $values_str .= '&';
        }
      }
    }
    $sql = 'INSERT IGNORE INTO '.$this->tableName.' ('.$fields_str.') VALUES ('.$values_str.')';

    $args = array();
    foreach($values as $field => $value) {
      array_push($args, $value);
    }

    $this->provider->internalRunQuery($sql, $args);
    if ($newId = $this->provider->getLastId()) {
      if ($newValues = $this->findOne(array($this->provider->rowidField() => $newId))) {
        $values = $newValues;
      }
      return $newId;
    } else
    if ($fallbackSql) {
      return $this->provider->getValue($fallbackSql);
    } else {
      return null;
    }

  }

  function replace(&$values, $dataTypes = null) {

    $fields_str = '';
    $values_str = '';

    foreach($values as $field => $value) {
      if (is_array($value)) {

      }
      $fields_str .= ($fields_str?',':'').$field;
      $values_str .= ($values_str?',':'').'?';
      if (is_array($dataTypes)) {
        if (br($dataTypes, $field) == 's') {
          $values_str .= '&';
        }
      }
    }
    $sql = 'REPLACE INTO '.$this->tableName.' ('.$fields_str.') VALUES ('.$values_str.')';

    $args = array();
    foreach($values as $field => $value) {
      array_push($args, $value);
    }

    $this->provider->internalRunQuery($sql, $args);
    if ($newId = $this->provider->getLastId()) {
      if ($newValues = $this->findOne(array($this->provider->rowidField() => $newId))) {
        $values = $newValues;
        return $newId;
      } else {
        throw new Exception('Can not find inserted record');
      }
    }

  }

  function insert(&$values, $dataTypes = null) {

    $fields_str = '';
    $values_str = '';

    foreach($values as $field => $value) {
      if (is_array($value)) {

      }
      $fields_str .= ($fields_str?',':'').$field;
      $values_str .= ($values_str?',':'').'?';
      if (is_array($dataTypes)) {
        if (br($dataTypes, $field) == 's') {
          $values_str .= '&';
        }
      }
    }
    $sql = 'INSERT INTO '.$this->tableName.' ('.$fields_str.') VALUES ('.$values_str.')';

    $args = array();
    foreach($values as $field => $value) {
      array_push($args, $value);
    }

    $this->provider->internalRunQuery($sql, $args);
    if ($newId = $this->provider->getLastId()) {
      if ($newValues = $this->findOne(array($this->provider->rowidField() => $newId))) {
        $values = $newValues;
        return $newId;
      } else {
        throw new Exception('Can not find inserted record');
      }
    } else {
      throw new Exception('Can not get ID of inserted record');
    }

  }

}
