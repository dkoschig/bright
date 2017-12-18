<?php

/**
 * Project:     Bright framework
 * Author:      Jager Mesh (jagermesh@gmail.com)
 *
 * @version 1.1.0.0
 * @package Bright Core
 */

class BrDataBasePatch {

  private $stepNo = 0;
  private $guid = null;
  private $dependencies = array();
  private $className;
  private $patchFile;
  private $logObject;

  protected $dbManager;

  const DO_ABORT    = 0;
  const DO_CONTINUE = 1;
  const DO_RETRY    = 2;

  function __construct($patchFile, $dbManager, $logObject) {

    $this->patchFile = $patchFile;
    $this->className = get_called_class();
    $this->patchHash = sha1_file($patchFile);
    $this->dbManager = $dbManager;
    $this->logObject = $logObject;

  }

  function setGuid($value) {

    $this->guid = $value;

  }

  function logPrefix() {

    return '[' . br()->db()->getDataBaseName() . '] [' . $this->className . ']';

  }

  function addDependency($value) {

    $this->dependencies[] = $value;

  }

  function checkDependencies($raiseError = false) {

    foreach($this->dependencies as $dependency) {
      if (!br()->db()->getValue('SELECT id FROM br_db_patch WHERE guid = ?', $dependency)) {
        if ($raiseError) {
          $this->logObject->log('Error. Dependency not met: ' . $dependency, 'RED');
        }
        return false;
      }
    }

    return true;

  }

  function checkRequirements($raiseError = false, $command = 'run') {

    br()->assert($this->guid, 'Please generate GUID for this patch');

    if ($patch = br()->db()->getRow('SELECT * FROM br_db_patch WHERE guid = ?', $this->guid)) {
      if (($patch['patch_hash'] != $this->patchHash) || ($patch['patch_file'] != basename($this->patchFile))) {
        switch ($command) {
          case 'force':
            return true;
          case 'register':
            br()->db()->runQuery( 'UPDATE br_db_patch
                                      SET patch_file = ?
                                        , patch_hash = ?
                                        , body = ?
                                        , re_installed_at = NOW()
                                    WHERE guid = ?'
                                , basename($this->patchFile)
                                , $this->patchHash
                                , br()->fs()->loadFromFile($this->patchFile)
                                , $this->guid
                                );
            return false;
          default:
            if ($patch['patch_file'] != basename($this->patchFile)) {
              br()->log('');
              $this->logObject->log('Apply');
              $this->logObject->log('Error. Same patch already registered but has different name: ' . $patch['patch_file'], 'RED');
              if ($patch['body']) {
                br($patch['body'])->logDifference(br()->fs()->loadFromFile($this->patchFile), $this->logObject);
              }
            } else
            if ($patch['patch_hash'] != $this->patchHash) {
              br()->log('');
              $this->logObject->log('Apply');
              $this->logObject->log('Error. Same patch already registered but has different hash: ' . $patch['patch_hash'], 'RED');
              if ($patch['body']) {
                br($patch['body'])->logDifference(br()->fs()->loadFromFile($this->patchFile), $this->logObject);
              }
            } else
            if ($raiseError) {
              br()->log('');
              $this->logObject->log('Apply');
              $this->logObject->log('Error. Already applied', 'RED');
            }
            return false;
        }
      } else {
        if ($raiseError) {
          br()->log('');
          $this->logObject->log('Apply');
          $this->logObject->log('Error. Already applied', 'RED');
        }
        return false;
      }
    } else
    if ($command == 'register') {
      br()->db()->runQuery( 'INSERT IGNORE INTO br_db_patch (guid, patch_file, patch_hash, body, installed_at, re_installed_at) VALUES (?, ?, ?, ?, NOW(), NOW())'
                          , $this->guid
                          , basename($this->patchFile)
                          , $this->patchHash
                          , br()->fs()->loadFromFile($this->patchFile)
                          );
      return false;
    }

    return true;

  }

  function run() {

    br()->log('');
    $this->logObject->log('Apply');

    try {
      $this->up();

      br()->db()->runQuery( 'INSERT INTO br_db_patch (guid, patch_file, patch_hash, body, installed_at) VALUES (?, ?, ?, ?, NOW())
                                 ON DUPLICATE KEY
                             UPDATE patch_file = ?, patch_hash = ?, body = ?, re_installed_at = NOW()'
                          , $this->guid, basename($this->patchFile), $this->patchHash, br()->fs()->loadFromFile($this->patchFile)
                                       , basename($this->patchFile), $this->patchHash, br()->fs()->loadFromFile($this->patchFile)
                          );

      $this->logObject->log('Applied', 'GREEN');
    } catch (Exception $e) {
      $this->logObject->logException($e->getMessage());
    }

  }

  function registerTableForAuditing($tableName, $auditMode = 7) {

    $this->logObject->log(br('=')->repeat(80));
    $this->logObject->log('registerTableForAuditing(' . $tableName . ', ' . $auditMode . ')');

    return $this->dbManager->registerTableForAuditing($tableName, $auditMode);

  }

  function refreshTableSupport($tableName, $auditMode = 7) {

    $this->logObject->log(br('=')->repeat(80));
    $this->logObject->log('refreshTableSupport(' . $tableName . ', ' . $auditMode . ')');

    return $this->dbManager->refreshTableSupport($tableName, $auditMode);

  }

  function execute($sql, $stepName = null) {

    $this->stepNo++;
    $stepName = $stepName ? $stepName : $this->stepNo;

    return $this->internalExecute($sql, $stepName, false);

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


  public function executeScript($script, $stepName = null) {

    $this->stepNo++;
    $stepName = $stepName ? $stepName : $this->stepNo;

    $result = 0;

    if ($statements = $this->parseScript($script)) {
      foreach($statements as $statement) {
        $result += $this->internalExecute($statement, $stepName, false);

      }
    }

    return $result;

  }

  public function executeScriptFile($fileName, $stepName = null) {

    $this->stepNo++;
    $stepName = $stepName ? $stepName : $this->stepNo;

    $result = 0;

    if (file_exists($fileName)) {
      if ($script = br()->fs()->loadFromFile($fileName)) {
        return $this->executeScript($script);
      } else {
        $error = 'Error. UP step "' . $stepName . '":' . "\n\nScript file empty: " . $fileName;
        throw new BrAppException($error);
      }
    } else {
      $error = 'Error. UP step "' . $stepName . '":' . "\n\nScript file not found: " . $fileName;
      throw new BrAppException($error);
    }

  }

  private function internalExecute($sql, $stepName = null) {

    $stepName = $stepName ? $stepName : $this->stepNo;

    $this->logObject->log(br('=')->repeat(20) . ' ' . 'UP step "' . $stepName . '"' . ' ' . br('=')->repeat(20), 'YELLOW');
    // $this->logObject->log();
    try {
      if (is_callable($sql)) {
        $sql();
      } else {
        $this->logObject->log($sql);
        br()->db()->runQuery($sql);
      }
    } catch (Exception $e) {
      $error = 'Error. UP step "' . $stepName . '":' . "\n\n" . $e->getMessage();
      $this->logObject->log(br('=')->repeat(20) . ' ' . 'DOWN step "' . $stepName . '"' . ' ' . br('=')->repeat(20), 'YELLOW');
      try {
        $retry = $this->down($stepName, $e->getMessage());
      } catch (Exception $e2) {
        $error = $error . "\n" .
                 'DOWN error step "' . $stepName . '":' . "\n\n" . $e2->getMessage();
        throw new BrAppException($error);
      }
      switch ($retry) {
        case self::DO_CONTINUE:
          break;
        case self::DO_RETRY:
          $this->logObject->log($error, 'RED');
          $this->logObject->log('DOWN step "' . $stepName . '" requested rerun');
          try {
            if (is_callable($sql)) {
              $sql();
            } else {
              $this->logObject->log(br('=')->repeat(80));
              $this->logObject->log($sql);
              br()->db()->runQuery($sql);
            }
          } catch (Exception $e) {
            throw new BrAppException('UP error step "' . $stepName . '":' . "\n\n" . $e->getMessage());
          }
          break;
        default:
          throw new BrAppException($error);
      }
    }

    $this->logObject->log(br()->db()->getAffectedRowsAmount() . ' row(s) affected', 'GREEN');

    return br()->db()->getAffectedRowsAmount();

  }

  static function generatePatchScript($name, $path) {

    $name     = ucfirst($name);
    $fileName = $path . '/patches/Patch' . $name . '.php';

    if (file_exists($fileName)) {
      throw new BrAppException('Such patch already exists - ' . $fileName);
    } else {
      br()->fs()->saveToFile( $fileName
                            , br()->renderer()->fetchString( br()->fs()->loadFromFile(__DIR__ . '/templates/DataBasePatch.tpl')
                                                           , array( 'guid' => br()->guid()
                                                                  , 'name' => $name
                                                                  )));
    }

  }

}
