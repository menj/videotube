<?php
if (PHP_SAPI!=='cli') { http_response_code(404);exit; }
require dirname(__DIR__).'/includes/config.php';
require dirname(__DIR__).'/includes/UploadQueue.php';
$queue=new UploadQueue($conn);
$limit=in_array('--once',$argv,true)?1:100;
for ($i=0;$i<$limit && $queue->workOne();$i++) echo "Processed one job.\n";
