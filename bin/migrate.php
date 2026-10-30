<?php
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/includes/config.php';
require dirname(__DIR__).'/includes/Migration.php';
$options=getopt('',['admin:','dry-run','backup-confirmed']);
if (!isset($options['admin']) || (!isset($options['dry-run']) && !isset($options['backup-confirmed']))) { fwrite(STDERR,"Usage: php bin/migrate.php --admin=existing_username --dry-run | --backup-confirmed\nStop web traffic/workers and take a tested backup before applying.\n");exit(1); }
try { $plan=Migration::apply($conn,(string)$options['admin'],isset($options['dry-run']));echo implode(";\n",$plan).";\n";echo isset($options['dry-run'])?"Dry run only; no changes made.\n":"Migration complete.\n"; }
catch (Throwable $e) { fwrite(STDERR,$e->getMessage()."\n");exit(1); }
