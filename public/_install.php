<?php
require '/www/app/bootstrap.php';
try {
  $log = \App\Core\Schema::install(true, true);
  echo implode("\n", $log);
} catch (Throwable $e) {
  echo "INSTALL ERROR: " . $e->getMessage() . "\n" . $e->getTraceAsString();
}
