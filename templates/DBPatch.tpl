<?php

class Patch[[name]] extends BrDatabasePatch {

  function init() {

    $this->setGuid('[[guid]]');

    // add dependencies from other partches here
    // $this->addDependency('OTHER PATCH GUID');

  }

  function up() {

    // put your patch code here using $this->execute($sql, $stepName);

  }

  function down($failedUpStep, $erroMessage) {

    // put your error recovering code here

  }

}
