<?php

if (file_exists(dirname(__DIR__) . '/vendor/jagermesh/bright/BrightInit.php')) {
  require_once(dirname(__DIR__) . '/vendor/jagermesh/bright/BrightInit.php');
} else {
  require_once(dirname(__DIR__) . '/bright/Bright.php');
}

if ($patchName = @$argv[1]) {
  br()->importLib('JobCustomJob');
  BrJobCustomJob::generateJobScript($patchName, __DIR__);
} else {
  br()->log('Usage: php ' . basename(__FILE__) . ' JobName');
}

