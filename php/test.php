<?php

include_once(__DIR__ . '/test.inc.php');
include_once(__DIR__ . '/jzon.inc.php');

function parse_config($file)
{
  $jzon = file_get_contents($file);
  $jzon = config_extract_header($jzon);
  //$res = jzon_parse($jzon);
  $res = json_decode($jzon, true);
  if(!is_array($res))
    throw new Exception("Bad json: $file " . json_last_error());
}

function parse_configs(array $files)
{
  $t = microtime(true);
  foreach($files as $idx => $file)
  {
    if($idx > 0 && ($idx % 1000) === 0)
      echo "Done $idx\n";
    parse_config($file);
  }
  echo "Parse: " . (microtime(true) - $t) . "\n";
}

if(isset($argv[1]))
{
  $args = unserialize(file_get_contents($argv[1]));
  list($idx, $files) = $args;
  echo "Worker $idx " . sizeof($files) . "\n";
  parse_configs($files);
  echo "Worker $idx done\n";
  file_put_contents($argv[2], serialize(true));
}
else
{
  $t = microtime(true);
  $files = scan_files_rec(array(__DIR__ . '/../files/'), array('.js'));
  echo "Scan: " . (microtime(true) - $t) . "\n";
  echo "Files: " . sizeof($files) . "\n";

  //$t = microtime(true);
  //parse_configs($files);
  //echo "Done " . (microtime(true) - $t) . "\n";

  $max_workers = 4;
  $chunk_size = ceil(sizeof($files)/$max_workers);
  $jobs = array();
  foreach(array_chunk($files, $chunk_size) as $idx => $chunk_files)
    $jobs[] = array($idx, $chunk_files);

  $t = microtime(true);
  run_background_workers(__FILE__, $jobs);
  echo "Done " . (microtime(true) - $t) . "\n";
}
