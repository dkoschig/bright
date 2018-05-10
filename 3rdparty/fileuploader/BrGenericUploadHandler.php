<?php

class BrGenericUploadHandler {

  private $params;

  function __construct($params = array()) {

    $this->params = $params;

  }

  private function toBytes($str) {

    return br($str)->toBytes();

  }

  function handle() {

    // list of valid extensions, ex. array("jpeg", "xml", "bmp")
    $allowedExtensions = br($this->params, 'allowedExtensions', array());

    // max file size in bytes
    $sizeLimit  = br($this->params, 'uploadLimit', 1024 * 1024 * 1024);
    $postSize   = $this->toBytes(ini_get('post_max_size'));
    $uploadSize = $this->toBytes(ini_get('upload_max_filesize'));

    $sizeLimit = min($sizeLimit, $postSize, $uploadSize);

    if (br($this->params, 'checkLogin') || br($this->params, 'userBasedPath')) {
      if ($login = br()->auth()->checkLogin()) {

      } else {
        br()->response()->sendNotAuthorized();
      }
    }

    if (br($this->params, 'url')) {
      $url = br($this->params, 'url');
    } else
    if (br($this->params, 'path')) {
      $url = br($this->params, 'path');
    } else {
      $url = 'uploads/';
    }

    if (br($this->params, 'path')) {
      $path = br($this->params, 'path');
    } else {
      $path = 'uploads/';
    }

    if (br($this->params, 'userBasedPath')) {
      $url  .= br()->db()->rowidValue($login) . '/';
      $path .= br()->db()->rowidValue($login) . '/';
    }

    $uploader = new qqFileUploader($allowedExtensions, $sizeLimit, $this->params);
    if (!br($this->params, 'externalPath') && !preg_match('~^(/|[A-Z][:])~', $path)) {
      $path = br()->atBasePath($path);
    } else {

    }

    br()->fs()->makeDir($path);
    $result = $uploader->handleUpload($path, $url);

    // to pass data through iframe you will need to encode all html tags
    echo htmlspecialchars(json_encode($result), ENT_NOQUOTES);

  }

}
