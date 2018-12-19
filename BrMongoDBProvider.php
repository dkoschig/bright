<?php

/**
 * Project:     Bright framework
 * Author:      Jager Mesh (jagermesh@gmail.com)
 *
 * @version 1.1.0.0
 * @package Bright Core
 */

class BrMongoDBProvider extends BrGenericDBProvider {

  function __construct($cfg) {

    $this->connection = new Mongo();
    $this->database = $this->connection->{$cfg['name']};

  }

  function connect($iteration = 0, $rerunError = null) {

  }

  function table($name) {

    return new BrMongoProviderTable($this, $name);

  }

  function command($command) {

    return $this->database->command($command);

  }

  function rowidValue($row) {

    if (is_array($row)) {
      return (string)$row['_id'];
    } else {
      return (string)$row;
    }

  }

  function rowid($row) {

    if (is_array($row)) {
      return $row['_id'];
    } else {
      if (!is_object($row)) {
        return new MongoId($row);
      } else {
        return $row;
      }
    }

  }

  function rowidField() {

    return '_id';

  }

  function regexpCondition($value) {

    return new MongoRegex("/.*".$value.".*/i");

  }

  function toDateTime($date) {

    return new MongoDate($date);

  }

}

