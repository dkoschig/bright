<?php

/**
 * Project:     Bright framework
 * Author:      Jager Mesh (jagermesh@gmail.com)
 *
 * @version 1.1.0.0
 * @package Bright Core
 */

namespace Bright;

class BrErrorFileLogAdapter extends BrGenericFileLogAdapter {

  private $writingHeader = false;

  public function __construct($baseFilePath = null, $baseFileName = null) {

    $this->writeAppInfoWithEveryMessage = true;

    parent::__construct($baseFilePath, 'errors.log');

  }

  public function write($message, $group = 'MSG', $tagline = null) {

    if ($group == 'ERR') {
      parent::write($message . "\n", $group, $tagline);
    }

  }

  protected function generateLogFileName() {

    $this->filePath = $this->baseFilePath ? $this->baseFilePath : br()->getLogsPath();

    $this->filePath = rtrim($this->filePath, '/') . '/';

    $date = @strftime('%Y-%m-%d');
    $hour = @strftime('%H');

    $this->filePath .= $date . '/' . $this->baseFileName;

  }

}

