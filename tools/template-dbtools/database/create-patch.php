<?php

require_once(dirname(__DIR__).'/bright/Bright.php');

if ($patchName = @$argv[1]) {
  br()->importLib('DataBasePatch');
  BrDataBasePatch::generatePatchScript($patchName, __DIR__);
} else {
  br()->log('Usage: php ' . basename(__FILE__) . ' PatchName');
}

