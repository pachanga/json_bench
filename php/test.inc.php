<?php

function is_win()
{
  return !(DIRECTORY_SEPARATOR == '/');
}

function scan_files($dir, array $fnmatch_patterns = array())
{
  $results = array();
  $files = scandir($dir);

  foreach($files as $file) 
  {
    $path = realpath($dir . '/' . $file);
    if(!is_dir($path)) 
    {
      if($fnmatch_patterns)
      {
        foreach($fnmatch_patterns as $pattern)
        {
          if(fnmatch($pattern, $file))
          {
            $results[] = $path;
            break;
          }
        }
      }
      else
        $results[] = $path;
    } 
    else if($file != "." && $file != "..") 
    {
      $results = array_merge($results, scan_files($path, $fnmatch_patterns));
    }
  }

  return $results;
}

function scan_files_old(array $dirs, array $only_extensions = array())
{
  $files = array();
  foreach($dirs as $dir)
  {
    if(!is_dir($dir))
      continue;

    $dir = normalize_path($dir);

    $iter_mode = RecursiveIteratorIterator::LEAVES_ONLY;
    $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir), $iter_mode);

    foreach($iter as $filename => $cur) 
    {
      if(!$cur->isDir())
      {
        if(!$only_extensions)
          $files[] = $filename;
        else
        {
          $flen = strlen($filename); 
          foreach($only_extensions as $ext)
          {
            if(substr_compare($filename, $ext, $flen-strlen($ext)) === 0)
              $files[] = $filename;
          }
        }
      }
    }
  }
  return $files;
}

function normalize_path($path, $unix=null/*null means try to guess*/)
{
  if(is_null($unix)) 
    $unix = !is_win();

  $path = str_replace('\\', '/', $path);
  $path = preg_replace('/\/+/', '/', $path);
  $parts = explode('/', $path);
  $absolutes = array();
  foreach($parts as $part) 
  {
    if('.' == $part) 
      continue;

    if('..' == $part) 
      array_pop($absolutes);
    else
      $absolutes[] = $part;
  }
  $res = implode($unix ? '/' : '\\', $absolutes);
  return $res;
}

function config_extract_header($contents)
{
  return preg_replace('~(\s*\{)\s*/\*\s*proto_id\s*=\s*\d+\s*;\s*alias\s*=\s*[^\s]+\s*\*/~', '$1', $contents);
}

function run_background_proc($cmd)
{
  if(is_win())
  {
    $wshell = new COM("WScript.Shell");
    $ret = $wshell->Run($cmd, 0, false);
    if($ret !== 0) 
      throw new Exception("Error starting worker: $cmd");
  }
  else
  {
    exec("$cmd &", $output, $ret);
    if($ret !== 0)
      throw new Exception("Error starting worker: $cmd");
  }
}

function run_background_workers($script, array $worker_args)
{
  $results = array();
  $workers = array();

  $tmp_dir = sys_get_temp_dir();

  foreach($worker_args as $idx => $args)
  {
    $in_file = tempnam($tmp_dir, 'in_');
    $out_file = tempnam($tmp_dir, 'out_');
    $log_file = tempnam($tmp_dir, 'log_');
    $err_file = tempnam($tmp_dir, 'err_');

    $workers[] = array($in_file, $out_file, $log_file, $err_file);

    file_put_contents($in_file, serialize($args));

    $proc_cmd = PHP_BINARY . " $script " . escapeshellarg($in_file) . ' ' . escapeshellarg($out_file) . ' > ' . escapeshellarg($log_file) . ' 2> ' . escapeshellarg($err_file);

    run_background_proc($proc_cmd);
  }

  try
  {
    $log_handles = array();
    while(sizeof($results) < sizeof($workers))
    {
      //sleep 0.5 sec
      usleep(50000);

      foreach($workers as $idx => $worker)
      {
        if(isset($results[$idx]))
          continue;

        list($in_file, $out_file, $log_file, $err_file) = $worker;

        if(!isset($log_handles[$idx]) && is_file($log_file))
          $log_handles[$idx] = fopen($log_file, 'r');

        if(isset($log_handles[$idx]))
        {
          while(($buffer = fgets($log_handles[$idx])) !== false)
            echo $buffer;

          $pos = ftell($log_handles[$idx]);
          fclose($log_handles[$idx]);
          $log_handles[$idx] = fopen($log_file, "r");
          fseek($log_handles[$idx], $pos);
        }

        if(is_file($err_file) && filesize($err_file) > 0)
          throw new Exception("Error in worker $idx:\n" . file_get_contents($err_file));

        if(is_file($out_file) && filesize($out_file) > 0)
          $results[$idx] = @unserialize(file_get_contents($out_file));
      }
    }
  }
  finally
  {
    foreach($workers as $item)
    {
      list($in_file, $log_file, $err_file) = $item;
      @unlink($in_file);
      @unlink($out_file);
      @unlink($log_file);
      @unlink($err_file);
    }
  }

  return $results;
}
