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

////////////////

class M3ConfLevelCell
{
  const CLASS_ID = 55895124;

  /** @var M3ConfPos */
public $pos = null;
/** @var M3Dir */
public $gravity;
/** @var uint32 */
public $spawner;
/** @var M3Covers */
public $chip_cover;
/** @var uint32 */
public $chip_cover_health;
/** @var M3Chips */
public $chip;
/** @var uint32 */
public $chip_health;
/** @var M3ChipsLayered */
public $chip_layer0;
/** @var M3ChipsLayered */
public $chip_layer1;
/** @var M3Mats */
public $chip_mat;
/** @var uint32 */
public $chip_layer0_health;
/** @var M3ChipsBlocker */
public $chip_blocker;
/** @var uint32 */
public $chip_blocker_health;
/** @var M3ChipsBlocked */
public $chip_blocked;
/** @var M3Belts */
public $chip_belt;
/** @var M3ConfPos */
public $chip_belt_next = null;
/** @var bool */
public $protected_from_starting_boosters;
/** @var M3ChipsRider */
public $chip_marker;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos','gravity','spawner','chip_cover','chip_cover_health','chip','chip_health','chip_layer0','chip_layer1','chip_mat','chip_layer0_health','chip_blocker','chip_blocker_health','chip_blocked','chip_belt','chip_belt_next','protected_from_starting_boosters','chip_marker',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => 'M3ConfPos','gravity' => 'M3Dir','spawner' => 'uint32','chip_cover' => 'M3Covers','chip_cover_health' => 'uint32','chip' => 'M3Chips','chip_health' => 'uint32','chip_layer0' => 'M3ChipsLayered','chip_layer1' => 'M3ChipsLayered','chip_mat' => 'M3Mats','chip_layer0_health' => 'uint32','chip_blocker' => 'M3ChipsBlocker','chip_blocker_health' => 'uint32','chip_blocked' => 'M3ChipsBlocked','chip_belt' => 'M3Belts','chip_belt_next' => 'M3ConfPos','protected_from_starting_boosters' => 'bool','chip_marker' => 'M3ChipsRider',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => array (),'gravity' => array (),'spawner' => array (  'default' => '0',),'chip_cover' => array (  'default' => '"clear"',),'chip_cover_health' => array (  'default' => '0',),'chip' => array (  'default' => '"clear"',),'chip_health' => array (  'default' => '1',),'chip_layer0' => array (  'default' => '"clear"',),'chip_layer1' => array (  'default' => '"clear"',),'chip_mat' => array (  'default' => '"clear"',),'chip_layer0_health' => array (  'default' => '0',),'chip_blocker' => array (  'default' => '"clear"',),'chip_blocker_health' => array (  'default' => '1',),'chip_blocked' => array (  'default' => '"clear"',),'chip_belt' => array (  'default' => '"clear"',),'chip_belt_next' => array (  'default' => '{"x":0,"y":0}',),'protected_from_starting_boosters' => array (  'default' => 'false',),'chip_marker' => array (  'default' => '"clear"',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->pos = new M3ConfPos();
    $this->gravity = M3Dir::DEFAULT_VALUE;
    $this->spawner = mtg_php_val_uint32(0);
    $this->chip_cover = M3Covers::clear;
    $this->chip_cover_health = mtg_php_val_uint32(0);
    $this->chip = M3Chips::clear;
    $this->chip_health = mtg_php_val_uint32(1);
    $this->chip_layer0 = M3ChipsLayered::clear;
    $this->chip_layer1 = M3ChipsLayered::clear;
    $this->chip_mat = M3Mats::clear;
    $this->chip_layer0_health = mtg_php_val_uint32(0);
    $this->chip_blocker = M3ChipsBlocker::clear;
    $this->chip_blocker_health = mtg_php_val_uint32(1);
    $this->chip_blocked = M3ChipsBlocked::clear;
    $this->chip_belt = M3Belts::clear;
    $this->chip_belt_next = new M3ConfPos();
    $this->protected_from_starting_boosters = mtg_php_val_bool(false);
    $this->chip_marker = M3ChipsRider::clear;


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'pos', null);
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->pos = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'gravity', null);
          $tmp_val__ = $tmp_val__;
          $this->gravity = mtg_php_val_enum('M3Dir', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'spawner', 0);
          $tmp_val__ = $tmp_val__;
          $this->spawner = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_cover', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_cover = mtg_php_val_enum('M3Covers', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_cover_health', 0);
          $tmp_val__ = $tmp_val__;
          $this->chip_cover_health = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip = mtg_php_val_enum('M3Chips', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_health', 1);
          $tmp_val__ = $tmp_val__;
          $this->chip_health = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_layer0', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_layer0 = mtg_php_val_enum('M3ChipsLayered', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_layer1', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_layer1 = mtg_php_val_enum('M3ChipsLayered', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_mat', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_mat = mtg_php_val_enum('M3Mats', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_layer0_health', 0);
          $tmp_val__ = $tmp_val__;
          $this->chip_layer0_health = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_blocker', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_blocker = mtg_php_val_enum('M3ChipsBlocker', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_blocker_health', 1);
          $tmp_val__ = $tmp_val__;
          $this->chip_blocker_health = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_blocked', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_blocked = mtg_php_val_enum('M3ChipsBlocked', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_belt', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_belt = mtg_php_val_enum('M3Belts', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_belt_next', $assoc ? array (  'x' => 0,  'y' => 0,) : array_values(array (  'x' => 0,  'y' => 0,)));
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->chip_belt_next = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'protected_from_starting_boosters', false);
          $tmp_val__ = $tmp_val__;
          $this->protected_from_starting_boosters = mtg_php_val_bool($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_marker', "clear");
          $tmp_val__ = $tmp_val__;
          $this->chip_marker = mtg_php_val_enum('M3ChipsRider', $tmp_val__, true);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelCell':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'pos';$__last_val = $this->pos;      mtg_php_array_set_value($message, $assoc, 'pos', is_array($this->pos) ? $this->pos : $this->pos->export($assoc));
$__last_var = 'gravity';$__last_val = $this->gravity;      mtg_php_array_set_value($message, $assoc, 'gravity', 1*$this->gravity);
$__last_var = 'spawner';$__last_val = $this->spawner;      mtg_php_array_set_value($message, $assoc, 'spawner', 1*$this->spawner);
$__last_var = 'chip_cover';$__last_val = $this->chip_cover;      mtg_php_array_set_value($message, $assoc, 'chip_cover', 1*$this->chip_cover);
$__last_var = 'chip_cover_health';$__last_val = $this->chip_cover_health;      mtg_php_array_set_value($message, $assoc, 'chip_cover_health', 1*$this->chip_cover_health);
$__last_var = 'chip';$__last_val = $this->chip;      mtg_php_array_set_value($message, $assoc, 'chip', 1*$this->chip);
$__last_var = 'chip_health';$__last_val = $this->chip_health;      mtg_php_array_set_value($message, $assoc, 'chip_health', 1*$this->chip_health);
$__last_var = 'chip_layer0';$__last_val = $this->chip_layer0;      mtg_php_array_set_value($message, $assoc, 'chip_layer0', 1*$this->chip_layer0);
$__last_var = 'chip_layer1';$__last_val = $this->chip_layer1;      mtg_php_array_set_value($message, $assoc, 'chip_layer1', 1*$this->chip_layer1);
$__last_var = 'chip_mat';$__last_val = $this->chip_mat;      mtg_php_array_set_value($message, $assoc, 'chip_mat', 1*$this->chip_mat);
$__last_var = 'chip_layer0_health';$__last_val = $this->chip_layer0_health;      mtg_php_array_set_value($message, $assoc, 'chip_layer0_health', 1*$this->chip_layer0_health);
$__last_var = 'chip_blocker';$__last_val = $this->chip_blocker;      mtg_php_array_set_value($message, $assoc, 'chip_blocker', 1*$this->chip_blocker);
$__last_var = 'chip_blocker_health';$__last_val = $this->chip_blocker_health;      mtg_php_array_set_value($message, $assoc, 'chip_blocker_health', 1*$this->chip_blocker_health);
$__last_var = 'chip_blocked';$__last_val = $this->chip_blocked;      mtg_php_array_set_value($message, $assoc, 'chip_blocked', 1*$this->chip_blocked);
$__last_var = 'chip_belt';$__last_val = $this->chip_belt;      mtg_php_array_set_value($message, $assoc, 'chip_belt', 1*$this->chip_belt);
$__last_var = 'chip_belt_next';$__last_val = $this->chip_belt_next;      mtg_php_array_set_value($message, $assoc, 'chip_belt_next', is_array($this->chip_belt_next) ? $this->chip_belt_next : $this->chip_belt_next->export($assoc));
$__last_var = 'protected_from_starting_boosters';$__last_val = $this->protected_from_starting_boosters;      mtg_php_array_set_value($message, $assoc, 'protected_from_starting_boosters', (bool)$this->protected_from_starting_boosters);
$__last_var = 'chip_marker';$__last_val = $this->chip_marker;      mtg_php_array_set_value($message, $assoc, 'chip_marker', 1*$this->chip_marker);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelCell'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}

class M3Mats
{
  const CLASS_ID = 112314308;

  const clear = 0;
  const carpet = 37925772;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'carpet' => 37925772
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Mats. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',37925772 => 'carpet'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Mats. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Mats. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,37925772
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','carpet'
          );
    } 
    return self::$names_list_;
  } 
}

class M3Chips
{
  const CLASS_ID = 178973294;

  const clear = 0;
  const random = 159587649;
  const empty = 149856411;
  const green = 173571093;
  const red = 20043541;
  const blue = 152790968;
  const pink = 266541332;
  const yellow = 152369935;
  const cyan = 17824785;
  const box = 34681458;
  const plate = 182865318;
  const key = 165835097;
  const flower = 17201424;
  const chain = 103514130;
  const jelly = 168633071;
  const ice = 109310110;
  const gift = 130928691;
  const candle = 47307072;
  const rocket = 158564410;
  const bbomb = 203671966;
  const vbomb = 144508370;
  const hbomb = 108834744;
  const rainbow = 4153540;
  const tray = 150880180;
  const lyre = 223856470;
  const lyre_note = 72057805;
  const random_1 = 220370293;
  const random_2 = 78621104;
  const random_3 = 64273907;
  const random_4 = 129355834;
  const foam = 107133260;
  const feather = 99200788;
  const pillow = 264630631;
  const soap = 156014744;
  const soap_bubble = 131958942;
  const phone = 219800740;
  const paint_tray_3x1 = 27253688;
  const paint_tray_2x1 = 20890305;
  const mouse = 77815192;
  const mouse_hole = 203259574;
  const box_with_ring = 156916201;
  const cuckoo_clock = 268228540;
  const ring = 99382431;
  const bowknot = 169073011;
  const bowknot_violet = 204595850;
  const bowknot_ribbon = 126361439;
  const bowknot_unlink = 7588332;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'random' => 159587649,'empty' => 149856411,'green' => 173571093,'red' => 20043541,'blue' => 152790968,'pink' => 266541332,'yellow' => 152369935,'cyan' => 17824785,'box' => 34681458,'plate' => 182865318,'key' => 165835097,'flower' => 17201424,'chain' => 103514130,'jelly' => 168633071,'ice' => 109310110,'gift' => 130928691,'candle' => 47307072,'rocket' => 158564410,'bbomb' => 203671966,'vbomb' => 144508370,'hbomb' => 108834744,'rainbow' => 4153540,'tray' => 150880180,'lyre' => 223856470,'lyre_note' => 72057805,'random_1' => 220370293,'random_2' => 78621104,'random_3' => 64273907,'random_4' => 129355834,'foam' => 107133260,'feather' => 99200788,'pillow' => 264630631,'soap' => 156014744,'soap_bubble' => 131958942,'phone' => 219800740,'paint_tray_3x1' => 27253688,'paint_tray_2x1' => 20890305,'mouse' => 77815192,'mouse_hole' => 203259574,'box_with_ring' => 156916201,'cuckoo_clock' => 268228540,'ring' => 99382431,'bowknot' => 169073011,'bowknot_violet' => 204595850,'bowknot_ribbon' => 126361439,'bowknot_unlink' => 7588332
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Chips. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',159587649 => 'random',149856411 => 'empty',173571093 => 'green',20043541 => 'red',152790968 => 'blue',266541332 => 'pink',152369935 => 'yellow',17824785 => 'cyan',34681458 => 'box',182865318 => 'plate',165835097 => 'key',17201424 => 'flower',103514130 => 'chain',168633071 => 'jelly',109310110 => 'ice',130928691 => 'gift',47307072 => 'candle',158564410 => 'rocket',203671966 => 'bbomb',144508370 => 'vbomb',108834744 => 'hbomb',4153540 => 'rainbow',150880180 => 'tray',223856470 => 'lyre',72057805 => 'lyre_note',220370293 => 'random_1',78621104 => 'random_2',64273907 => 'random_3',129355834 => 'random_4',107133260 => 'foam',99200788 => 'feather',264630631 => 'pillow',156014744 => 'soap',131958942 => 'soap_bubble',219800740 => 'phone',27253688 => 'paint_tray_3x1',20890305 => 'paint_tray_2x1',77815192 => 'mouse',203259574 => 'mouse_hole',156916201 => 'box_with_ring',268228540 => 'cuckoo_clock',99382431 => 'ring',169073011 => 'bowknot',204595850 => 'bowknot_violet',126361439 => 'bowknot_ribbon',7588332 => 'bowknot_unlink'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Chips. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Chips. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,159587649,149856411,173571093,20043541,152790968,266541332,152369935,17824785,34681458,182865318,165835097,17201424,103514130,168633071,109310110,130928691,47307072,158564410,203671966,144508370,108834744,4153540,150880180,223856470,72057805,220370293,78621104,64273907,129355834,107133260,99200788,264630631,156014744,131958942,219800740,27253688,20890305,77815192,203259574,156916201,268228540,99382431,169073011,204595850,126361439,7588332
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','random','empty','green','red','blue','pink','yellow','cyan','box','plate','key','flower','chain','jelly','ice','gift','candle','rocket','bbomb','vbomb','hbomb','rainbow','tray','lyre','lyre_note','random_1','random_2','random_3','random_4','foam','feather','pillow','soap','soap_bubble','phone','paint_tray_3x1','paint_tray_2x1','mouse','mouse_hole','box_with_ring','cuckoo_clock','ring','bowknot','bowknot_violet','bowknot_ribbon','bowknot_unlink'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3RegularChips
{
  const CLASS_ID = 152834611;

  const clear = 0;
  const empty = 149856411;
  const random = 159587649;
  const green = 173571093;
  const red = 20043541;
  const blue = 152790968;
  const cyan = 17824785;
  const pink = 266541332;
  const yellow = 152369935;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'empty' => 149856411,'random' => 159587649,'green' => 173571093,'red' => 20043541,'blue' => 152790968,'cyan' => 17824785,'pink' => 266541332,'yellow' => 152369935
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3RegularChips. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',149856411 => 'empty',159587649 => 'random',173571093 => 'green',20043541 => 'red',152790968 => 'blue',17824785 => 'cyan',266541332 => 'pink',152369935 => 'yellow'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3RegularChips. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3RegularChips. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,149856411,159587649,173571093,20043541,152790968,17824785,266541332,152369935
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','empty','random','green','red','blue','cyan','pink','yellow'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3TargetChips
{
  const CLASS_ID = 227715205;

  const clear = 0;
  const key = 165835097;
  const flower = 17201424;
  const lyre = 223856470;
  const phone = 219800740;
  const mouse = 77815192;
  const cuckoo_clock = 268228540;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'key' => 165835097,'flower' => 17201424,'lyre' => 223856470,'phone' => 219800740,'mouse' => 77815192,'cuckoo_clock' => 268228540
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3TargetChips. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',165835097 => 'key',17201424 => 'flower',223856470 => 'lyre',219800740 => 'phone',77815192 => 'mouse',268228540 => 'cuckoo_clock'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3TargetChips. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3TargetChips. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,165835097,17201424,223856470,219800740,77815192,268228540
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','key','flower','lyre','phone','mouse','cuckoo_clock'
          );
    } 
    return self::$names_list_;
  } 
}

class M3Covers
{
  const CLASS_ID = 148038333;

  const clear = 0;
  const web = 58458845;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'web' => 58458845
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Covers. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',58458845 => 'web'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Covers. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Covers. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,58458845
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','web'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3Walls
{
  const CLASS_ID = 41814800;

  const clear = 0;
  const generic = 165432907;
  const armored = 196666317;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'generic' => 165432907,'armored' => 196666317
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Walls. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',165432907 => 'generic',196666317 => 'armored'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Walls. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Walls. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,165432907,196666317
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','generic','armored'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3Belts
{
  const CLASS_ID = 117906668;

  const clear = 0;
  const generic = 39232813;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'generic' => 39232813
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Belts. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',39232813 => 'generic'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Belts. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Belts. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,39232813
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','generic'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3Barriers
{
  const CLASS_ID = 12848997;

  const clear = 0;
  const barrier = 150329024;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'barrier' => 150329024
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Barriers. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',150329024 => 'barrier'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Barriers. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Barriers. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,150329024
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','barrier'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3Portals
{
  const CLASS_ID = 117428486;

  const clear = 0;
  const for_keys = 210966518;
  const entrance = 83030634;
  const exit = 80246951;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'for_keys' => 210966518,'entrance' => 83030634,'exit' => 80246951
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Portals. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',210966518 => 'for_keys',83030634 => 'entrance',80246951 => 'exit'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Portals. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Portals. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,210966518,83030634,80246951
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','for_keys','entrance','exit'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3Spawners
{
  const CLASS_ID = 17395286;

  const clear = 0;
  const generic = 1;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'generic' => 1
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Spawners. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',1 => 'generic'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Spawners. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Spawners. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','generic'
          );
    } 
    return self::$names_list_;
  } 
}

class M3GoalType
{
  const CLASS_ID = 51809841;

  const REMOVE = 0;
  const FILL_MAT = 1;
  const REMOVE_ALL = 2;
  const REMOVE_TAG = 3;

  const DEFAULT_VALUE = 0; // REMOVE;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'REMOVE' => 0,'FILL_MAT' => 1,'REMOVE_ALL' => 2,'REMOVE_TAG' => 3
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3GoalType. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'REMOVE',1 => 'FILL_MAT',2 => 'REMOVE_ALL',3 => 'REMOVE_TAG'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3GoalType. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3GoalType. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1,2,3
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'REMOVE','FILL_MAT','REMOVE_ALL','REMOVE_TAG'
          );
    } 
    return self::$names_list_;
  } 
}

class M3ConfPos
{
  const CLASS_ID = 21851814;

  /** @var int32 */
public $x;
/** @var int32 */
public $y;


  static function CLASS_PROPS()
  {
    static $props = array (  'POD' => NULL,  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('x','y',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('x' => 'int32','y' => 'int32',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('x' => array (),'y' => array (),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->x = 0;
    $this->y = 0;


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'x', null);
          $tmp_val__ = $tmp_val__;
          $this->x = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'y', null);
          $tmp_val__ = $tmp_val__;
          $this->y = mtg_php_val_int32($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfPos':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'x';$__last_val = $this->x;      mtg_php_array_set_value($message, $assoc, 'x', 1*$this->x);
$__last_var = 'y';$__last_val = $this->y;      mtg_php_array_set_value($message, $assoc, 'y', 1*$this->y);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfPos'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfPortalLink
{
  const CLASS_ID = 253759482;

  /** @var M3ConfPos */
public $pos = null;
/** @var M3Dir */
public $side;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos','side',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => 'M3ConfPos','side' => 'M3Dir',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => array (),'side' => array (),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->pos = new M3ConfPos();
    $this->side = M3Dir::DEFAULT_VALUE;


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'pos', null);
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->pos = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'side', null);
          $tmp_val__ = $tmp_val__;
          $this->side = mtg_php_val_enum('M3Dir', $tmp_val__, true);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfPortalLink':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'pos';$__last_val = $this->pos;      mtg_php_array_set_value($message, $assoc, 'pos', is_array($this->pos) ? $this->pos : $this->pos->export($assoc));
$__last_var = 'side';$__last_val = $this->side;      mtg_php_array_set_value($message, $assoc, 'side', 1*$this->side);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfPortalLink'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelPortal
{
  const CLASS_ID = 227193599;

  /** @var M3ConfPos */
public $pos = null;
/** @var M3Dir */
public $side;
/** @var M3Portals */
public $type;
/** @var M3ConfPortalLink[] */
public $link = array();


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos','side','type','link',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => 'M3ConfPos','side' => 'M3Dir','type' => 'M3Portals','link' => 'M3ConfPortalLink[]',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => array (),'side' => array (),'type' => array (),'link' => array (  'default' => '[]',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->pos = new M3ConfPos();
    $this->side = M3Dir::DEFAULT_VALUE;
    $this->type = M3Portals::DEFAULT_VALUE;
    $this->link = array();


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'pos', null);
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->pos = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'side', null);
          $tmp_val__ = $tmp_val__;
          $this->side = mtg_php_val_enum('M3Dir', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'type', null);
          $tmp_val__ = $tmp_val__;
          $this->type = mtg_php_val_enum('M3Portals', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'link', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfPortalLink($tmp_sub_arr__, $assoc);
              $this->link[] = $tmp__;

          }
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelPortal':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'pos';$__last_val = $this->pos;      mtg_php_array_set_value($message, $assoc, 'pos', is_array($this->pos) ? $this->pos : $this->pos->export($assoc));
$__last_var = 'side';$__last_val = $this->side;      mtg_php_array_set_value($message, $assoc, 'side', 1*$this->side);
$__last_var = 'type';$__last_val = $this->type;      mtg_php_array_set_value($message, $assoc, 'type', 1*$this->type);
$__last_var = 'link';$__last_val = $this->link;      $arr_tmp__ = array();
      if(!$assoc && $this->link && is_array(current($this->link)))
      {
        $arr_tmp__ = $this->link;
      }
      else
        foreach($this->link as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'link', $arr_tmp__);


    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelPortal'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelWall
{
  const CLASS_ID = 187075648;

  /** @var M3ConfPos */
public $pos = null;
/** @var M3Dir */
public $side;
/** @var M3Walls */
public $type;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos','side','type',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => 'M3ConfPos','side' => 'M3Dir','type' => 'M3Walls',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => array (),'side' => array (),'type' => array (),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->pos = new M3ConfPos();
    $this->side = M3Dir::DEFAULT_VALUE;
    $this->type = M3Walls::DEFAULT_VALUE;


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'pos', null);
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->pos = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'side', null);
          $tmp_val__ = $tmp_val__;
          $this->side = mtg_php_val_enum('M3Dir', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'type', null);
          $tmp_val__ = $tmp_val__;
          $this->type = mtg_php_val_enum('M3Walls', $tmp_val__, true);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelWall':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'pos';$__last_val = $this->pos;      mtg_php_array_set_value($message, $assoc, 'pos', is_array($this->pos) ? $this->pos : $this->pos->export($assoc));
$__last_var = 'side';$__last_val = $this->side;      mtg_php_array_set_value($message, $assoc, 'side', 1*$this->side);
$__last_var = 'type';$__last_val = $this->type;      mtg_php_array_set_value($message, $assoc, 'type', 1*$this->type);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelWall'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelBarrier
{
  const CLASS_ID = 26723096;

  /** @var M3ConfPos */
public $pos = null;
/** @var M3Barriers */
public $type;
/** @var int32 */
public $width;
/** @var int32 */
public $height;
/** @var uint32 */
public $goal_id;
/** @var int32 */
public $goal_amount;
/** @var M3GoalType */
public $goal_type;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos','type','width','height','goal_id','goal_amount','goal_type',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => 'M3ConfPos','type' => 'M3Barriers','width' => 'int32','height' => 'int32','goal_id' => 'uint32','goal_amount' => 'int32','goal_type' => 'M3GoalType',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('pos' => array (),'type' => array (),'width' => array (),'height' => array (),'goal_id' => array (),'goal_amount' => array (),'goal_type' => array (  'default' => '"REMOVE"',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->pos = new M3ConfPos();
    $this->type = M3Barriers::DEFAULT_VALUE;
    $this->width = 0;
    $this->height = 0;
    $this->goal_id = 0;
    $this->goal_amount = 0;
    $this->goal_type = M3GoalType::REMOVE;


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'pos', null);
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->pos = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'type', null);
          $tmp_val__ = $tmp_val__;
          $this->type = mtg_php_val_enum('M3Barriers', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'width', null);
          $tmp_val__ = $tmp_val__;
          $this->width = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'height', null);
          $tmp_val__ = $tmp_val__;
          $this->height = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'goal_id', null);
          $tmp_val__ = $tmp_val__;
          $this->goal_id = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'goal_amount', null);
          $tmp_val__ = $tmp_val__;
          $this->goal_amount = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'goal_type', "REMOVE");
          $tmp_val__ = $tmp_val__;
          $this->goal_type = mtg_php_val_enum('M3GoalType', $tmp_val__, true);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelBarrier':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'pos';$__last_val = $this->pos;      mtg_php_array_set_value($message, $assoc, 'pos', is_array($this->pos) ? $this->pos : $this->pos->export($assoc));
$__last_var = 'type';$__last_val = $this->type;      mtg_php_array_set_value($message, $assoc, 'type', 1*$this->type);
$__last_var = 'width';$__last_val = $this->width;      mtg_php_array_set_value($message, $assoc, 'width', 1*$this->width);
$__last_var = 'height';$__last_val = $this->height;      mtg_php_array_set_value($message, $assoc, 'height', 1*$this->height);
$__last_var = 'goal_id';$__last_val = $this->goal_id;      mtg_php_array_set_value($message, $assoc, 'goal_id', 1*$this->goal_id);
$__last_var = 'goal_amount';$__last_val = $this->goal_amount;      mtg_php_array_set_value($message, $assoc, 'goal_amount', 1*$this->goal_amount);
$__last_var = 'goal_type';$__last_val = $this->goal_type;      mtg_php_array_set_value($message, $assoc, 'goal_type', 1*$this->goal_type);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelBarrier'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelSpawnObj
{
  const CLASS_ID = 44947466;

  /** @var uint32 */
public $chip;
/** @var uint32 */
public $chip_health;
/** @var uint32 */
public $layer0;
/** @var uint32 */
public $layer0_health;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('chip','chip_health','layer0','layer0_health',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('chip' => 'uint32','chip_health' => 'uint32','layer0' => 'uint32','layer0_health' => 'uint32',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('chip' => array (  'default' => '0',),'chip_health' => array (  'default' => '1',),'layer0' => array (  'default' => '0',),'layer0_health' => array (  'default' => '1',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->chip = mtg_php_val_uint32(0);
    $this->chip_health = mtg_php_val_uint32(1);
    $this->layer0 = mtg_php_val_uint32(0);
    $this->layer0_health = mtg_php_val_uint32(1);


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip', 0);
          $tmp_val__ = $tmp_val__;
          $this->chip = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chip_health', 1);
          $tmp_val__ = $tmp_val__;
          $this->chip_health = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'layer0', 0);
          $tmp_val__ = $tmp_val__;
          $this->layer0 = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'layer0_health', 1);
          $tmp_val__ = $tmp_val__;
          $this->layer0_health = mtg_php_val_uint32($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelSpawnObj':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'chip';$__last_val = $this->chip;      mtg_php_array_set_value($message, $assoc, 'chip', 1*$this->chip);
$__last_var = 'chip_health';$__last_val = $this->chip_health;      mtg_php_array_set_value($message, $assoc, 'chip_health', 1*$this->chip_health);
$__last_var = 'layer0';$__last_val = $this->layer0;      mtg_php_array_set_value($message, $assoc, 'layer0', 1*$this->layer0);
$__last_var = 'layer0_health';$__last_val = $this->layer0_health;      mtg_php_array_set_value($message, $assoc, 'layer0_health', 1*$this->layer0_health);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelSpawnObj'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelSpawnChance
{
  const CLASS_ID = 140695670;

  /** @var uint32 */
public $spawner_id;
/** @var M3ConfLevelSpawnObj */
public $obj = null;
/** @var int32 */
public $chance;
/** @var bool */
public $skip_for_init;
/** @var uint32 */
public $max_on_screen;
/** @var uint32 */
public $max_to_spawn;
/** @var uint32 */
public $force_period;
/** @var uint32 */
public $min_period;
/** @var uint32 */
public $max_period;
/** @var M3SpawnIcon */
public $icon;
/** @var M3ConfLevelSpawnObj[] */
public $initial_sequence_chips = array();
/** @var bool */
public $initial_chips_for_instance;
/** @var bool */
public $min_on_screen;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('spawner_id','obj','chance','skip_for_init','max_on_screen','max_to_spawn','force_period','min_period','max_period','icon','initial_sequence_chips','initial_chips_for_instance','min_on_screen',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('spawner_id' => 'uint32','obj' => 'M3ConfLevelSpawnObj','chance' => 'int32','skip_for_init' => 'bool','max_on_screen' => 'uint32','max_to_spawn' => 'uint32','force_period' => 'uint32','min_period' => 'uint32','max_period' => 'uint32','icon' => 'M3SpawnIcon','initial_sequence_chips' => 'M3ConfLevelSpawnObj[]','initial_chips_for_instance' => 'bool','min_on_screen' => 'bool',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('spawner_id' => array (  'default' => '1',),'obj' => array (  'default' => '{}',),'chance' => array (),'skip_for_init' => array (  'default' => 'false',),'max_on_screen' => array (  'default' => '0',),'max_to_spawn' => array (  'default' => '0',),'force_period' => array (  'default' => '0',),'min_period' => array (  'default' => '0',),'max_period' => array (  'default' => '0',),'icon' => array (  'default' => '"clear"',),'initial_sequence_chips' => array (  'default' => '[]',),'initial_chips_for_instance' => array (  'default' => 'false',),'min_on_screen' => array (  'default' => 'false',  'optional' => NULL,),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->spawner_id = mtg_php_val_uint32(1);
    $this->obj = new M3ConfLevelSpawnObj();
    $this->chance = 0;
    $this->skip_for_init = mtg_php_val_bool(false);
    $this->max_on_screen = mtg_php_val_uint32(0);
    $this->max_to_spawn = mtg_php_val_uint32(0);
    $this->force_period = mtg_php_val_uint32(0);
    $this->min_period = mtg_php_val_uint32(0);
    $this->max_period = mtg_php_val_uint32(0);
    $this->icon = M3SpawnIcon::clear;
    $this->initial_sequence_chips = array();
    $this->initial_chips_for_instance = mtg_php_val_bool(false);
    $this->min_on_screen = mtg_php_val_bool(false);


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'spawner_id', 1);
          $tmp_val__ = $tmp_val__;
          $this->spawner_id = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'obj', $assoc ? array () : array_values(array ()));
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->obj = new M3ConfLevelSpawnObj($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'chance', null);
          $tmp_val__ = $tmp_val__;
          $this->chance = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'skip_for_init', false);
          $tmp_val__ = $tmp_val__;
          $this->skip_for_init = mtg_php_val_bool($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'max_on_screen', 0);
          $tmp_val__ = $tmp_val__;
          $this->max_on_screen = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'max_to_spawn', 0);
          $tmp_val__ = $tmp_val__;
          $this->max_to_spawn = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'force_period', 0);
          $tmp_val__ = $tmp_val__;
          $this->force_period = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'min_period', 0);
          $tmp_val__ = $tmp_val__;
          $this->min_period = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'max_period', 0);
          $tmp_val__ = $tmp_val__;
          $this->max_period = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'icon', "clear");
          $tmp_val__ = $tmp_val__;
          $this->icon = mtg_php_val_enum('M3SpawnIcon', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'initial_sequence_chips', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelSpawnObj($tmp_sub_arr__, $assoc);
              $this->initial_sequence_chips[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'initial_chips_for_instance', false);
          $tmp_val__ = $tmp_val__;
          $this->initial_chips_for_instance = mtg_php_val_bool($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'min_on_screen', false);
          $tmp_val__ = $tmp_val__;
          $this->min_on_screen = mtg_php_val_bool($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelSpawnChance':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'spawner_id';$__last_val = $this->spawner_id;      mtg_php_array_set_value($message, $assoc, 'spawner_id', 1*$this->spawner_id);
$__last_var = 'obj';$__last_val = $this->obj;      mtg_php_array_set_value($message, $assoc, 'obj', is_array($this->obj) ? $this->obj : $this->obj->export($assoc));
$__last_var = 'chance';$__last_val = $this->chance;      mtg_php_array_set_value($message, $assoc, 'chance', 1*$this->chance);
$__last_var = 'skip_for_init';$__last_val = $this->skip_for_init;      mtg_php_array_set_value($message, $assoc, 'skip_for_init', (bool)$this->skip_for_init);
$__last_var = 'max_on_screen';$__last_val = $this->max_on_screen;      mtg_php_array_set_value($message, $assoc, 'max_on_screen', 1*$this->max_on_screen);
$__last_var = 'max_to_spawn';$__last_val = $this->max_to_spawn;      mtg_php_array_set_value($message, $assoc, 'max_to_spawn', 1*$this->max_to_spawn);
$__last_var = 'force_period';$__last_val = $this->force_period;      mtg_php_array_set_value($message, $assoc, 'force_period', 1*$this->force_period);
$__last_var = 'min_period';$__last_val = $this->min_period;      mtg_php_array_set_value($message, $assoc, 'min_period', 1*$this->min_period);
$__last_var = 'max_period';$__last_val = $this->max_period;      mtg_php_array_set_value($message, $assoc, 'max_period', 1*$this->max_period);
$__last_var = 'icon';$__last_val = $this->icon;      mtg_php_array_set_value($message, $assoc, 'icon', 1*$this->icon);
$__last_var = 'initial_sequence_chips';$__last_val = $this->initial_sequence_chips;      $arr_tmp__ = array();
      if(!$assoc && $this->initial_sequence_chips && is_array(current($this->initial_sequence_chips)))
      {
        $arr_tmp__ = $this->initial_sequence_chips;
      }
      else
        foreach($this->initial_sequence_chips as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'initial_sequence_chips', $arr_tmp__);

$__last_var = 'initial_chips_for_instance';$__last_val = $this->initial_chips_for_instance;      mtg_php_array_set_value($message, $assoc, 'initial_chips_for_instance', (bool)$this->initial_chips_for_instance);
$__last_var = 'min_on_screen';$__last_val = $this->min_on_screen;      mtg_php_array_set_value($message, $assoc, 'min_on_screen', (bool)$this->min_on_screen);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelSpawnChance'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelGoal
{
  const CLASS_ID = 68126872;

  /** @var uint32 */
public $id;
/** @var int32 */
public $amount;
/** @var M3GoalType */
public $type;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('id','amount','type',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('id' => 'uint32','amount' => 'int32','type' => 'M3GoalType',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('id' => array (),'amount' => array (),'type' => array (  'default' => '"REMOVE"',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->id = 0;
    $this->amount = 0;
    $this->type = M3GoalType::REMOVE;


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'id', null);
          $tmp_val__ = $tmp_val__;
          $this->id = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'amount', null);
          $tmp_val__ = $tmp_val__;
          $this->amount = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'type', "REMOVE");
          $tmp_val__ = $tmp_val__;
          $this->type = mtg_php_val_enum('M3GoalType', $tmp_val__, true);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelGoal':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'id';$__last_val = $this->id;      mtg_php_array_set_value($message, $assoc, 'id', 1*$this->id);
$__last_var = 'amount';$__last_val = $this->amount;      mtg_php_array_set_value($message, $assoc, 'amount', 1*$this->amount);
$__last_var = 'type';$__last_val = $this->type;      mtg_php_array_set_value($message, $assoc, 'type', 1*$this->type);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelGoal'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelFieldZone
{
  const CLASS_ID = 109259954;

  /** @var M3ConfLevelGoal[] */
public $goals = array();
/** @var M3ConfPos */
public $pos = null;
/** @var int32 */
public $width;
/** @var int32 */
public $height;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('goals','pos','width','height',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('goals' => 'M3ConfLevelGoal[]','pos' => 'M3ConfPos','width' => 'int32','height' => 'int32',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('goals' => array (),'pos' => array (  'default' => '{"x":0,"y":0}',),'width' => array (  'default' => '0',),'height' => array (  'default' => '0',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->goals = array();
    $this->pos = new M3ConfPos();
    $this->width = mtg_php_val_int32(0);
    $this->height = mtg_php_val_int32(0);


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'goals', null);
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelGoal($tmp_sub_arr__, $assoc);
              $this->goals[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'pos', $assoc ? array (  'x' => 0,  'y' => 0,) : array_values(array (  'x' => 0,  'y' => 0,)));
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->pos = new M3ConfPos($tmp_sub_arr__, $assoc);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'width', 0);
          $tmp_val__ = $tmp_val__;
          $this->width = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'height', 0);
          $tmp_val__ = $tmp_val__;
          $this->height = mtg_php_val_int32($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelFieldZone':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'goals';$__last_val = $this->goals;      $arr_tmp__ = array();
      if(!$assoc && $this->goals && is_array(current($this->goals)))
      {
        $arr_tmp__ = $this->goals;
      }
      else
        foreach($this->goals as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'goals', $arr_tmp__);

$__last_var = 'pos';$__last_val = $this->pos;      mtg_php_array_set_value($message, $assoc, 'pos', is_array($this->pos) ? $this->pos : $this->pos->export($assoc));
$__last_var = 'width';$__last_val = $this->width;      mtg_php_array_set_value($message, $assoc, 'width', 1*$this->width);
$__last_var = 'height';$__last_val = $this->height;      mtg_php_array_set_value($message, $assoc, 'height', 1*$this->height);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelFieldZone'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3LevelSpecificsType
{
  const CLASS_ID = 238842799;

  const None = 0;
  const SetDefaultRocketTargets = 1;
  const SetSpawnerForGift = 2;
  const SetSettingsActivePhones = 3;
  const SetSettingsPaintTray = 4;
  const SetSettingsBoxWithRing = 5;
  const BowknotRibbonLink = 6;
  const SetSpawnChipForLayer = 7;
  const SetSettingsMarker = 8;
  const RandomRingInBox = 9;
  const AddBurningBoosters = 10;

  const DEFAULT_VALUE = 0; // None;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'None' => 0,'SetDefaultRocketTargets' => 1,'SetSpawnerForGift' => 2,'SetSettingsActivePhones' => 3,'SetSettingsPaintTray' => 4,'SetSettingsBoxWithRing' => 5,'BowknotRibbonLink' => 6,'SetSpawnChipForLayer' => 7,'SetSettingsMarker' => 8,'RandomRingInBox' => 9,'AddBurningBoosters' => 10
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3LevelSpecificsType. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'None',1 => 'SetDefaultRocketTargets',2 => 'SetSpawnerForGift',3 => 'SetSettingsActivePhones',4 => 'SetSettingsPaintTray',5 => 'SetSettingsBoxWithRing',6 => 'BowknotRibbonLink',7 => 'SetSpawnChipForLayer',8 => 'SetSettingsMarker',9 => 'RandomRingInBox',10 => 'AddBurningBoosters'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3LevelSpecificsType. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3LevelSpecificsType. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1,2,3,4,5,6,7,8,9,10
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'None','SetDefaultRocketTargets','SetSpawnerForGift','SetSettingsActivePhones','SetSettingsPaintTray','SetSettingsBoxWithRing','BowknotRibbonLink','SetSpawnChipForLayer','SetSettingsMarker','RandomRingInBox','AddBurningBoosters'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelSpecifics
{
  const CLASS_ID = 99387231;

  /** @var M3LevelSpecificsType */
public $type;
/** @var int32[] */
public $sparams = array();


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',  'cloneable' => NULL,);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('type','sparams',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('type' => 'M3LevelSpecificsType','sparams' => 'int32[]',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('type' => array (),'sparams' => array (),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->type = M3LevelSpecificsType::DEFAULT_VALUE;
    $this->sparams = array();


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'type', null);
          $tmp_val__ = $tmp_val__;
          $this->type = mtg_php_val_enum('M3LevelSpecificsType', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'sparams', null);
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp__ = mtg_php_val_int32($tmp_arr_item__);
              $this->sparams[] = $tmp__;

          }
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelSpecifics':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'type';$__last_val = $this->type;      mtg_php_array_set_value($message, $assoc, 'type', 1*$this->type);
$__last_var = 'sparams';$__last_val = $this->sparams;      $arr_tmp__ = array();
      if(!$assoc && $this->sparams && is_array(current($this->sparams)))
      {
        $arr_tmp__ = $this->sparams;
      }
      else
        foreach($this->sparams as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', 1*$arr_tmp_item__);
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'sparams', $arr_tmp__);


    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelSpecifics'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevelField
{
  const CLASS_ID = 166690946;

  /** @var int32 */
public $width;
/** @var int32 */
public $height;
/** @var M3ConfLevelFieldZone[] */
public $zones = array();
/** @var M3Dir */
public $next_transition;
/** @var M3ConfLevelCell[] */
public $cells = array();
/** @var M3ConfLevelWall[] */
public $walls = array();
/** @var M3ConfLevelPortal[] */
public $portals = array();
/** @var M3ConfLevelBarrier[] */
public $barriers = array();
/** @var M3ConfLevelGoal[] */
public $goals = array();


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('width','height','zones','next_transition','cells','walls','portals','barriers','goals',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('width' => 'int32','height' => 'int32','zones' => 'M3ConfLevelFieldZone[]','next_transition' => 'M3Dir','cells' => 'M3ConfLevelCell[]','walls' => 'M3ConfLevelWall[]','portals' => 'M3ConfLevelPortal[]','barriers' => 'M3ConfLevelBarrier[]','goals' => 'M3ConfLevelGoal[]',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('width' => array (),'height' => array (),'zones' => array (  'default' => '[]',),'next_transition' => array (  'default' => '"DOWN"',),'cells' => array (  'default' => '[]',),'walls' => array (  'default' => '[]',),'portals' => array (  'default' => '[]',),'barriers' => array (  'default' => '[]',),'goals' => array (  'default' => '[]',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->width = 0;
    $this->height = 0;
    $this->zones = array();
    $this->next_transition = M3Dir::DOWN;
    $this->cells = array();
    $this->walls = array();
    $this->portals = array();
    $this->barriers = array();
    $this->goals = array();


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'width', null);
          $tmp_val__ = $tmp_val__;
          $this->width = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'height', null);
          $tmp_val__ = $tmp_val__;
          $this->height = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'zones', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelFieldZone($tmp_sub_arr__, $assoc);
              $this->zones[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'next_transition', "DOWN");
          $tmp_val__ = $tmp_val__;
          $this->next_transition = mtg_php_val_enum('M3Dir', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'cells', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelCell($tmp_sub_arr__, $assoc);
              $this->cells[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'walls', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelWall($tmp_sub_arr__, $assoc);
              $this->walls[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'portals', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelPortal($tmp_sub_arr__, $assoc);
              $this->portals[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'barriers', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelBarrier($tmp_sub_arr__, $assoc);
              $this->barriers[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'goals', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelGoal($tmp_sub_arr__, $assoc);
              $this->goals[] = $tmp__;

          }
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevelField':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'width';$__last_val = $this->width;      mtg_php_array_set_value($message, $assoc, 'width', 1*$this->width);
$__last_var = 'height';$__last_val = $this->height;      mtg_php_array_set_value($message, $assoc, 'height', 1*$this->height);
$__last_var = 'zones';$__last_val = $this->zones;      $arr_tmp__ = array();
      if(!$assoc && $this->zones && is_array(current($this->zones)))
      {
        $arr_tmp__ = $this->zones;
      }
      else
        foreach($this->zones as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'zones', $arr_tmp__);

$__last_var = 'next_transition';$__last_val = $this->next_transition;      mtg_php_array_set_value($message, $assoc, 'next_transition', 1*$this->next_transition);
$__last_var = 'cells';$__last_val = $this->cells;      $arr_tmp__ = array();
      if(!$assoc && $this->cells && is_array(current($this->cells)))
      {
        $arr_tmp__ = $this->cells;
      }
      else
        foreach($this->cells as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'cells', $arr_tmp__);

$__last_var = 'walls';$__last_val = $this->walls;      $arr_tmp__ = array();
      if(!$assoc && $this->walls && is_array(current($this->walls)))
      {
        $arr_tmp__ = $this->walls;
      }
      else
        foreach($this->walls as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'walls', $arr_tmp__);

$__last_var = 'portals';$__last_val = $this->portals;      $arr_tmp__ = array();
      if(!$assoc && $this->portals && is_array(current($this->portals)))
      {
        $arr_tmp__ = $this->portals;
      }
      else
        foreach($this->portals as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'portals', $arr_tmp__);

$__last_var = 'barriers';$__last_val = $this->barriers;      $arr_tmp__ = array();
      if(!$assoc && $this->barriers && is_array(current($this->barriers)))
      {
        $arr_tmp__ = $this->barriers;
      }
      else
        foreach($this->barriers as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'barriers', $arr_tmp__);

$__last_var = 'goals';$__last_val = $this->goals;      $arr_tmp__ = array();
      if(!$assoc && $this->goals && is_array(current($this->goals)))
      {
        $arr_tmp__ = $this->goals;
      }
      else
        foreach($this->goals as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'goals', $arr_tmp__);


    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevelField'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfTutorialPreset
{
  const CLASS_ID = 58794568;

  /** @var uint32 */
public $proto_id;
/** @var int32 */
public $rseed;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('proto_id','rseed',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('proto_id' => 'uint32','rseed' => 'int32',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('proto_id' => array (  'flt_str2num' => NULL,),'rseed' => array (  'default' => '0',  'cs_attributes' => 'System.NonSerialized',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->proto_id = 0;
    $this->rseed = mtg_php_val_int32(0);


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'proto_id', null);
          $tmp_val__ = $assoc ? mtg_flt_str2num($tmp_val__, 'proto_id', $this, array ()) : $tmp_val__;
          $this->proto_id = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'rseed', 0);
          $tmp_val__ = $tmp_val__;
          $this->rseed = mtg_php_val_int32($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfTutorialPreset':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'proto_id';$__last_val = $this->proto_id;      mtg_php_array_set_value($message, $assoc, 'proto_id', 1*$this->proto_id);
$__last_var = 'rseed';$__last_val = $this->rseed;      mtg_php_array_set_value($message, $assoc, 'rseed', 1*$this->rseed);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfTutorialPreset'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3LevelOfDifficulty
{
  const CLASS_ID = 129314058;

  const Normal = 0;
  const Hard = 1;
  const VeryHard = 2;

  const DEFAULT_VALUE = 0; // Normal;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'Normal' => 0,'Hard' => 1,'VeryHard' => 2
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3LevelOfDifficulty. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'Normal',1 => 'Hard',2 => 'VeryHard'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3LevelOfDifficulty. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3LevelOfDifficulty. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1,2
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'Normal','Hard','VeryHard'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3ConfLevel
{
  const CLASS_ID = 57056799;

  /** @var int32 */
public $turns_limit;
/** @var M3ConfLevelSpawnChance[] */
public $spawn_chances = array();
/** @var string */
public $tileset_back = '';
/** @var int32[] */
public $rseeds = array();
/** @var M3ConfLevelSpecifics[] */
public $specifics = array();
/** @var M3ConfLevelField[] */
public $fields = array();
/** @var M3ConfTutorialPreset[] */
public $tutorial = array();
/** @var M3LevelOfDifficulty */
public $difficulty;
/** @var string */
public $comment = '';
/** @var uint32 */
public $revision;
/** @var int32 */
public $bg_image_index;


  static function CLASS_PROPS()
  {
    static $props = array (  'cs_attributes' => 'System.Serializable',);
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array_merge(array(), array('turns_limit','spawn_chances','tileset_back','rseeds','specifics','fields','tutorial','difficulty','comment','revision','bg_image_index',)); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array_merge(array(), array('turns_limit' => 'int32','spawn_chances' => 'M3ConfLevelSpawnChance[]','tileset_back' => 'string','rseeds' => 'int32[]','specifics' => 'M3ConfLevelSpecifics[]','fields' => 'M3ConfLevelField[]','tutorial' => 'M3ConfTutorialPreset[]','difficulty' => 'M3LevelOfDifficulty','comment' => 'string','revision' => 'uint32','bg_image_index' => 'int32',)); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array_merge(array(), array('turns_limit' => array (  'default' => '-1',),'spawn_chances' => array (),'tileset_back' => array (  'default' => '""',),'rseeds' => array (  'default' => '[]',),'specifics' => array (  'default' => '[]',),'fields' => array (),'tutorial' => array (  'default' => '[]',  'arrmax' => '1',),'difficulty' => array (  'default' => '"Normal"',),'comment' => array (  'default' => '""',  'optional' => NULL,),'revision' => array (  'default' => '0',  'optional' => NULL,),'bg_image_index' => array (  'optional' => NULL,  'default' => '0',),)); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->turns_limit = mtg_php_val_int32(-1);
    $this->spawn_chances = array();
    $this->tileset_back = mtg_php_val_string("");
    $this->rseeds = array();
    $this->specifics = array();
    $this->fields = array();
    $this->tutorial = array();
    $this->difficulty = M3LevelOfDifficulty::Normal;
    $this->comment = mtg_php_val_string("");
    $this->revision = mtg_php_val_uint32(0);
    $this->bg_image_index = mtg_php_val_int32(0);


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'turns_limit', -1);
          $tmp_val__ = $tmp_val__;
          $this->turns_limit = mtg_php_val_int32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'spawn_chances', null);
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelSpawnChance($tmp_sub_arr__, $assoc);
              $this->spawn_chances[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'tileset_back', "");
          $tmp_val__ = $tmp_val__;
          $this->tileset_back = mtg_php_val_string($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'rseeds', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp__ = mtg_php_val_int32($tmp_arr_item__);
              $this->rseeds[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'specifics', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelSpecifics($tmp_sub_arr__, $assoc);
              $this->specifics[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'fields', null);
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfLevelField($tmp_sub_arr__, $assoc);
              $this->fields[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'tutorial', array());
          $tmp_arr__ = mtg_php_val_arr($tmp_val__);
          foreach($tmp_arr__ as $tmp_arr_item__)
          {

              $tmp_arr_item__ = $tmp_arr_item__;
              $tmp_sub_arr__ = mtg_php_val_arr($tmp_arr_item__);
              $tmp__ = new M3ConfTutorialPreset($tmp_sub_arr__, $assoc);
              $this->tutorial[] = $tmp__;

          }
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'difficulty', "Normal");
          $tmp_val__ = $tmp_val__;
          $this->difficulty = mtg_php_val_enum('M3LevelOfDifficulty', $tmp_val__, true);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'comment', "");
          $tmp_val__ = $tmp_val__;
          $this->comment = mtg_php_val_string($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'revision', 0);
          $tmp_val__ = $tmp_val__;
          $this->revision = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'bg_image_index', 0);
          $tmp_val__ = $tmp_val__;
          $this->bg_image_index = mtg_php_val_int32($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfLevel':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'parent';$__last_val='<<skipped>>';
$__last_var = 'turns_limit';$__last_val = $this->turns_limit;      mtg_php_array_set_value($message, $assoc, 'turns_limit', 1*$this->turns_limit);
$__last_var = 'spawn_chances';$__last_val = $this->spawn_chances;      $arr_tmp__ = array();
      if(!$assoc && $this->spawn_chances && is_array(current($this->spawn_chances)))
      {
        $arr_tmp__ = $this->spawn_chances;
      }
      else
        foreach($this->spawn_chances as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'spawn_chances', $arr_tmp__);

$__last_var = 'tileset_back';$__last_val = $this->tileset_back;      mtg_php_array_set_value($message, $assoc, 'tileset_back', ''.$this->tileset_back);
$__last_var = 'rseeds';$__last_val = $this->rseeds;      $arr_tmp__ = array();
      if(!$assoc && $this->rseeds && is_array(current($this->rseeds)))
      {
        $arr_tmp__ = $this->rseeds;
      }
      else
        foreach($this->rseeds as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', 1*$arr_tmp_item__);
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'rseeds', $arr_tmp__);

$__last_var = 'specifics';$__last_val = $this->specifics;      $arr_tmp__ = array();
      if(!$assoc && $this->specifics && is_array(current($this->specifics)))
      {
        $arr_tmp__ = $this->specifics;
      }
      else
        foreach($this->specifics as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'specifics', $arr_tmp__);

$__last_var = 'fields';$__last_val = $this->fields;      $arr_tmp__ = array();
      if(!$assoc && $this->fields && is_array(current($this->fields)))
      {
        $arr_tmp__ = $this->fields;
      }
      else
        foreach($this->fields as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'fields', $arr_tmp__);

$__last_var = 'tutorial';$__last_val = $this->tutorial;      $arr_tmp__ = array();
      if(!$assoc && $this->tutorial && is_array(current($this->tutorial)))
      {
        $arr_tmp__ = $this->tutorial;
      }
      else
        foreach($this->tutorial as $idx__ => $arr_tmp_item__)
        {
$__last_var = '$arr_tmp_item__';$__last_val = $arr_tmp_item__;          mtg_php_array_set_value($arr_tmp__, $assoc, '$arr_tmp_item__', is_array($arr_tmp_item__) ? $arr_tmp_item__ : $arr_tmp_item__->export($assoc));
          if($assoc)
          {
            $arr_tmp__[] =  $arr_tmp__['$arr_tmp_item__'];
            unset($arr_tmp__['$arr_tmp_item__']);
          }
        }
      mtg_php_array_set_value($message, $assoc, 'tutorial', $arr_tmp__);

$__last_var = 'difficulty';$__last_val = $this->difficulty;      mtg_php_array_set_value($message, $assoc, 'difficulty', 1*$this->difficulty);
$__last_var = 'comment';$__last_val = $this->comment;      mtg_php_array_set_value($message, $assoc, 'comment', ''.$this->comment);
$__last_var = 'revision';$__last_val = $this->revision;      mtg_php_array_set_value($message, $assoc, 'revision', 1*$this->revision);
$__last_var = 'bg_image_index';$__last_val = $this->bg_image_index;      mtg_php_array_set_value($message, $assoc, 'bg_image_index', 1*$this->bg_image_index);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfLevel'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}
//THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3BoosterType
{
  const CLASS_ID = 118196739;

  const ExtraMoves = 1;
  const Rainbow = 2;
  const HBBomb = 3;
  const Rocket = 4;
  const DoubleRocket = 5;
  const Hammer = 6;
  const Sledgehammer = 7;
  const Glove = 8;
  const BonusMoves = 9;

  const DEFAULT_VALUE = 1; // ExtraMoves;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'ExtraMoves' => 1,'Rainbow' => 2,'HBBomb' => 3,'Rocket' => 4,'DoubleRocket' => 5,'Hammer' => 6,'Sledgehammer' => 7,'Glove' => 8,'BonusMoves' => 9
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3BoosterType. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       1 => 'ExtraMoves',2 => 'Rainbow',3 => 'HBBomb',4 => 'Rocket',5 => 'DoubleRocket',6 => 'Hammer',7 => 'Sledgehammer',8 => 'Glove',9 => 'BonusMoves'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3BoosterType. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3BoosterType. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          1,2,3,4,5,6,7,8,9
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'ExtraMoves','Rainbow','HBBomb','Rocket','DoubleRocket','Hammer','Sledgehammer','Glove','BonusMoves'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3BoosterUseType
{
  const CLASS_ID = 84270671;

  const None = 0;
  const Starting = 1;
  const Instant = 2;

  const DEFAULT_VALUE = 0; // None;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'None' => 0,'Starting' => 1,'Instant' => 2
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3BoosterUseType. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'None',1 => 'Starting',2 => 'Instant'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3BoosterUseType. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3BoosterUseType. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1,2
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'None','Starting','Instant'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT!

class M3SpawnIcon
{
  const CLASS_ID = 186047201;

  const clear = 0;
  const def = 1;
  const rainbow = 2;
  const rocket = 3;
  const bbomb = 4;
  const hbomb = 5;
  const vbomb = 6;
  const some_bombs = 7;
  const hbomb_rocket = 8;
  const vbomb_rocket = 9;
  const plate = 10;
  const bbomb_hbomb = 11;
  const bbomb_plate = 12;
  const bbomb_rocket = 13;
  const bbomb_vbomb = 14;
  const hbomb_plate = 15;
  const hbomb_vbomb_plate = 16;
  const hbomb_vbomb_rocket = 17;
  const rocket_plate = 18;
  const vbomb_hbomb = 19;
  const vbomb_plate = 20;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'def' => 1,'rainbow' => 2,'rocket' => 3,'bbomb' => 4,'hbomb' => 5,'vbomb' => 6,'some_bombs' => 7,'hbomb_rocket' => 8,'vbomb_rocket' => 9,'plate' => 10,'bbomb_hbomb' => 11,'bbomb_plate' => 12,'bbomb_rocket' => 13,'bbomb_vbomb' => 14,'hbomb_plate' => 15,'hbomb_vbomb_plate' => 16,'hbomb_vbomb_rocket' => 17,'rocket_plate' => 18,'vbomb_hbomb' => 19,'vbomb_plate' => 20
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3SpawnIcon. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',1 => 'def',2 => 'rainbow',3 => 'rocket',4 => 'bbomb',5 => 'hbomb',6 => 'vbomb',7 => 'some_bombs',8 => 'hbomb_rocket',9 => 'vbomb_rocket',10 => 'plate',11 => 'bbomb_hbomb',12 => 'bbomb_plate',13 => 'bbomb_rocket',14 => 'bbomb_vbomb',15 => 'hbomb_plate',16 => 'hbomb_vbomb_plate',17 => 'hbomb_vbomb_rocket',18 => 'rocket_plate',19 => 'vbomb_hbomb',20 => 'vbomb_plate'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3SpawnIcon. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3SpawnIcon. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','def','rainbow','rocket','bbomb','hbomb','vbomb','some_bombs','hbomb_rocket','vbomb_rocket','plate','bbomb_hbomb','bbomb_plate','bbomb_rocket','bbomb_vbomb','hbomb_plate','hbomb_vbomb_plate','hbomb_vbomb_rocket','rocket_plate','vbomb_hbomb','vbomb_plate'
          );
    } 
    return self::$names_list_;
  } 
}

class ConfBase
{
  const CLASS_ID = 176178415;

  /** @var uint32 */
public $id;
/** @var string */
public $strid = '';


  static function CLASS_PROPS()
  {
    static $props = array ();
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('id','strid',); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('id' => 'uint32','strid' => 'string',); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array('id' => array (  'default' => '0',),'strid' => array (  'default' => '""',),); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
    $this->id = mtg_php_val_uint32(0);
    $this->strid = mtg_php_val_string("");


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
          
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'id', 0);
          $tmp_val__ = $tmp_val__;
          $this->id = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'strid', "");
          $tmp_val__ = $tmp_val__;
          $this->strid = mtg_php_val_string($tmp_val__);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'ConfBase':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'id';$__last_val = $this->id;      mtg_php_array_set_value($message, $assoc, 'id', 1*$this->id);
$__last_var = 'strid';$__last_val = $this->strid;      mtg_php_array_set_value($message, $assoc, 'strid', ''.$this->strid);

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'ConfBase'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}

class M3ConfPortal extends ConfBase
{
  const CLASS_ID = 33641353;

  /** @var string */
public $prefab = '';
/** @var uint32 */
public $accepted_chip;
/** @var float */
public $ui_offset;
/** @var ConfBHL */
public $sink_script = null;


  static function CLASS_PROPS()
  {
    static $props = array ();
    return $props;
  }

  static function CLASS_FIELDS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array_merge(parent::CLASS_FIELDS(), array('prefab','accepted_chip','ui_offset','sink_script',)); 
    return $flds;
  }

  static function CLASS_FIELDS_TYPES()
  {
    static $flds = null;
    if($flds === null)
      $flds = array_merge(parent::CLASS_FIELDS_TYPES(), array('prefab' => 'string','accepted_chip' => 'uint32','ui_offset' => 'float','sink_script' => 'ConfBHL',)); 
    return $flds;
  }

  function CLASS_FIELDS_PROPS()
  {
    static $flds = null;
    if($flds === null)
      $flds = array_merge(parent::CLASS_FIELDS_PROPS(), array('prefab' => array (  'flt_prefab' => NULL,),'accepted_chip' => array (  'flt_str2num' => NULL,  'default' => '0',),'ui_offset' => array (  'default' => '0',),'sink_script' => array (),)); 
    return $flds;
  }

  function __construct(&$message = null, $assoc = false)
  {
parent::__construct();
    $this->prefab = '';
    $this->accepted_chip = mtg_php_val_uint32(mtg_flt_str2num(0, 'accepted_chip', $this, array ()));
    $this->ui_offset = mtg_php_val_float(0);
    $this->sink_script = new ConfBHL();


    if(!is_null($message))
      $this->import($message, $assoc);
  }

  function getClassId()
  {
    return self::CLASS_ID;
  }
  
  

  function import(&$message, $assoc = false, $root = true)
  {
    $IDX = 0;
    try
    {
      if(!is_array($message))
        throw new Exception("Bad message: $message");

      try
      {
        $IDX = parent::import($message, $assoc, false);
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'prefab', null);
          $tmp_val__ = $assoc ? mtg_flt_prefab($tmp_val__, 'prefab', $this, array ()) : $tmp_val__;
          $this->prefab = mtg_php_val_string($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'accepted_chip', 0);
          $tmp_val__ = $assoc ? mtg_flt_str2num($tmp_val__, 'accepted_chip', $this, array ()) : $tmp_val__;
          $this->accepted_chip = mtg_php_val_uint32($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'ui_offset', 0);
          $tmp_val__ = $tmp_val__;
          $this->ui_offset = mtg_php_val_float($tmp_val__);
        ++$IDX;
  
        $tmp_val__ = mtg_php_array_extract_val($message, $assoc, 'sink_script', null);
          $tmp_val__ = $tmp_val__;
          $tmp_sub_arr__ = mtg_php_val_arr($tmp_val__);
          $this->sink_script = new ConfBHL($tmp_sub_arr__, $assoc);
        ++$IDX;

      }
      catch(Exception $e)
      {
        $FIELDS = self::CLASS_FIELDS();
        throw new Exception("Error while filling field '{$FIELDS[$IDX]}': " . $e->getMessage());
      }

      if($root && $assoc && sizeof($message) > 0)
        throw new Exception("Junk fields: " . implode(',', array_keys($message)));
    }
    catch(Exception $e)
    {
      throw new Exception("Error while filling fields of 'M3ConfPortal':" . $e->getMessage());
    }
    return $IDX;
  }

  function export($assoc = false, $virtual = false)
  {
    $message = array();
    $this->fill($message, $assoc, $virtual);
    return $message;
  }

  function fill(&$message, $assoc = false, $virtual = false)
  {
    if($virtual)
      mtg_php_array_set_value($message, $assoc, 'vclass__', $this->getClassId());

    try
    {
      $__last_var = null;
      $__last_val = null;
      $__last_var = 'parent';$__last_val='<<skipped>>';parent::fill($message, $assoc, false);
$__last_var = 'prefab';$__last_val = $this->prefab;      mtg_php_array_set_value($message, $assoc, 'prefab', ''.$this->prefab);
$__last_var = 'accepted_chip';$__last_val = $this->accepted_chip;      mtg_php_array_set_value($message, $assoc, 'accepted_chip', 1*$this->accepted_chip);
$__last_var = 'ui_offset';$__last_val = $this->ui_offset;      mtg_php_array_set_value($message, $assoc, 'ui_offset', 1*$this->ui_offset);
$__last_var = 'sink_script';$__last_val = $this->sink_script;      mtg_php_array_set_value($message, $assoc, 'sink_script', is_array($this->sink_script) ? $this->sink_script : $this->sink_script->export($assoc));

    }
    catch(Exception $e)
    {
      throw new Exception("Error while dumping fields of 'M3ConfPortal'->$__last_var: ". PHP_EOL . serialize($__last_val) . PHP_EOL."	" . $e->getMessage());
    }
  }
}

class M3Dir
{
  const CLASS_ID = 158914888;

  const DOWN = 0;
  const UP = 1;
  const LEFT = 2;
  const RIGHT = 3;
  const NONE = 4;

  const DEFAULT_VALUE = 0; // DOWN;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'DOWN' => 0,'UP' => 1,'LEFT' => 2,'RIGHT' => 3,'NONE' => 4
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3Dir. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'DOWN',1 => 'UP',2 => 'LEFT',3 => 'RIGHT',4 => 'NONE'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3Dir. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3Dir. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,1,2,3,4
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'DOWN','UP','LEFT','RIGHT','NONE'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3ChipsLayered
{
  const CLASS_ID = 37148429;

  const clear = 0;
  const chain = 103514130;
  const jelly = 168633071;
  const ice = 109310110;
  const box = 34681458;
  const mouse_hole = 203259574;
  const soap_bubble = 131958942;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'chain' => 103514130,'jelly' => 168633071,'ice' => 109310110,'box' => 34681458,'mouse_hole' => 203259574,'soap_bubble' => 131958942
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3ChipsLayered. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',103514130 => 'chain',168633071 => 'jelly',109310110 => 'ice',34681458 => 'box',203259574 => 'mouse_hole',131958942 => 'soap_bubble'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3ChipsLayered. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3ChipsLayered. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,103514130,168633071,109310110,34681458,203259574,131958942
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','chain','jelly','ice','box','mouse_hole','soap_bubble'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3ChipsBlocker
{
  const CLASS_ID = 58699742;

  const clear = 0;
  const glass = 144139772;
  const tile = 13773252;
  const honey = 69201358;
  const converter_yellow = 252274553;
  const converter_cyan = 265756;
  const converter_blue = 135304629;
  const converter_green = 77073242;
  const converter_pink = 250145561;
  const converter_red = 189158225;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'glass' => 144139772,'tile' => 13773252,'honey' => 69201358,'converter_yellow' => 252274553,'converter_cyan' => 265756,'converter_blue' => 135304629,'converter_green' => 77073242,'converter_pink' => 250145561,'converter_red' => 189158225
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3ChipsBlocker. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',144139772 => 'glass',13773252 => 'tile',69201358 => 'honey',252274553 => 'converter_yellow',265756 => 'converter_cyan',135304629 => 'converter_blue',77073242 => 'converter_green',250145561 => 'converter_pink',189158225 => 'converter_red'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3ChipsBlocker. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3ChipsBlocker. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,144139772,13773252,69201358,252274553,265756,135304629,77073242,250145561,189158225
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','glass','tile','honey','converter_yellow','converter_cyan','converter_blue','converter_green','converter_pink','converter_red'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3ChipsBlocked
{
  const CLASS_ID = 128653967;

  const clear = 0;
  const letter_2x1 = 37280846;
  const letter_3x1 = 62260110;
  const letter_3x2 = 171654987;
  const letter_4x2 = 50342600;
  const letter_4x3 = 75185803;
  const letter_5x3 = 100000075;
  const letter_5x4 = 26512514;
  const letter_2x1v = 267755988;
  const letter_3x1v = 72412603;
  const letter_3x2v = 258430648;
  const letter_4x2v = 201310007;
  const letter_4x3v = 71160841;
  const letter_5x3v = 261658726;
  const letter_5x4v = 222593823;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'letter_2x1' => 37280846,'letter_3x1' => 62260110,'letter_3x2' => 171654987,'letter_4x2' => 50342600,'letter_4x3' => 75185803,'letter_5x3' => 100000075,'letter_5x4' => 26512514,'letter_2x1v' => 267755988,'letter_3x1v' => 72412603,'letter_3x2v' => 258430648,'letter_4x2v' => 201310007,'letter_4x3v' => 71160841,'letter_5x3v' => 261658726,'letter_5x4v' => 222593823
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3ChipsBlocked. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',37280846 => 'letter_2x1',62260110 => 'letter_3x1',171654987 => 'letter_3x2',50342600 => 'letter_4x2',75185803 => 'letter_4x3',100000075 => 'letter_5x3',26512514 => 'letter_5x4',267755988 => 'letter_2x1v',72412603 => 'letter_3x1v',258430648 => 'letter_3x2v',201310007 => 'letter_4x2v',71160841 => 'letter_4x3v',261658726 => 'letter_5x3v',222593823 => 'letter_5x4v'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3ChipsBlocked. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3ChipsBlocked. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,37280846,62260110,171654987,50342600,75185803,100000075,26512514,267755988,72412603,258430648,201310007,71160841,261658726,222593823
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','letter_2x1','letter_3x1','letter_3x2','letter_4x2','letter_4x3','letter_5x3','letter_5x4','letter_2x1v','letter_3x1v','letter_3x2v','letter_4x2v','letter_4x3v','letter_5x3v','letter_5x4v'
          );
    } 
    return self::$names_list_;
  } 
}
 //THIS FILE IS GENERATED AUTOMATICALLY, DON'T TOUCH IT! 

class M3ChipsRider
{
  const CLASS_ID = 92216971;

  const clear = 0;
  const bubble = 146540445;

  const DEFAULT_VALUE = 0; // clear;

  function getClassId()
  {
    return self::CLASS_ID;
  }

  static function isValueValid($value)
  {
    $values_list = self::getValuesList();
    return in_array($value, self::$values_list_);
  }

  static private $values_map_;
  static private $names_map_;

  static function getValueByName($name)
  {
    if(!self::$values_map_)
    {
      self::$values_map_ = array(
       'clear' => 0,'bubble' => 146540445
      );
    }
    if(!isset(self::$values_map_[$name]))
      throw new Exception("Value with name $name isn't defined in enum M3ChipsRider. Accepted: " . implode(',', self::getNamesList()));
    return self::$values_map_[$name];
  }
  
  static function getNameByValue($value)
  {
    if(!self::$names_map_)
    {
      self::$names_map_ = array(
       0 => 'clear',146540445 => 'bubble'
      );
    }
    if(!isset(self::$names_map_[$value]))
      throw new Exception("Value $value isn't defined in enum M3ChipsRider. Accepted: " . implode(',', self::getValuesList()));
    return self::$names_map_[$value];
  }

  static function checkValidity($value)
  {// throws exception if $value is not valid numeric enum value
    if(!is_numeric($value))
      throw new Exception("Numeric expected but got $value");
    if(!self::isValueValid($value))
      throw new Exception("Numeric value $value isn't value from enum M3ChipsRider. Accepted numerics are " . implode(',', self::getValuesList()) . " but better to use one of names instead: " . implode(',', self::getNamesList()));
  }

  static private $values_list_;
  static function getValuesList()
  {
    if(!self::$values_list_)
    {
      self::$values_list_ = array(
          0,146540445
          );
    } 
    return self::$values_list_;
  }

  static private $names_list_;
  static function getNamesList()
  {
    if(!self::$names_list_)
    {
      self::$names_list_ = array(
          'clear','bubble'
          );
    } 
    return self::$names_list_;
  } 
}

function mtg_flt_str2num($val, $name, $struct, $args)
{
  if(is_string($val))
  {
    if(strlen($val) === 0)
      throw new Exception("Bad value, string empty, crc28 can't be generated");

    //special case
    if(strpos($val, '@') !== 0)
      throw new Exception("@ expected");

    $val = substr($val, 1) . '.conf.js';

    //if(!defined('MTG_CONF_BASE_PATH'))
    //  throw new Exception("MTG_CONF_BASE_PATH is not defined, str2num validation not possible");

    //$path = MTG_CONF_BASE_PATH . '/' . $val; 
    //if(!file_exists($path))
    //  throw new Exception("No such file '$path'");

    //if(config_get_header($path, $proto_id, $alias))
    //  $val = $proto_id;
    //else
      $val = crc32($val);
  }

  if(!is_numeric($val))
    throw new Exception("Bad value, not a number(" . serialize($val) . ")");

  return 1*$val;
}

function mtg_php_array_extract_val(&$arr, $assoc, $name, $default = null)
{
  if(!is_array($arr))
    throw new Exception("$name: Not an array");

  if(!$assoc)
  {
    if(sizeof($arr) == 0)
    {
      if($default !== null)
        return $default;

      throw new Exception("$name: No next array item");
    }
    return array_shift($arr);
  }

  if(!isset($arr[$name]))
  {
    if($default !== null)
      return $default;

    throw new Exception("$name: No array item");
  }

  $val = $arr[$name];
  unset($arr[$name]);
  return $val;
}

function mtg_php_array_set_value(&$arr, $assoc, $name, $value)
{
  if($assoc)
    $arr[$name] = $value;
  else
    $arr[] = $value;
}

function mtg_php_val_string($val)
{
  //special case for empty strings
  if(is_bool($val) && $val === false)
    return '';
  if(!is_string($val))
    throw new Exception("Bad item, not a string(" . serialize($val) . ")");
  return $val;
}

function mtg_php_val_bool($val)
{
  if(!is_bool($val))
    throw new Exception("Bad item, not a bool(" . serialize($val) . ")");
  return $val;
}

function mtg_php_val_float($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  return $val;
}

function mtg_php_val_double($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  return 1*$val;
}

function mtg_php_val_uint64($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if(is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_int64($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if(is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_uint32($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if(($val < 0 && $val < -2147483648) || ($val > 0 && $val > 0xFFFFFFFF) || is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_int32($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if($val > 2147483647 || $val < -2147483648 || is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_uint16($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if($val > 0xFFFF || $val < 0 || is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_int16($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if($val > 32767 || $val < -32768 || is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_uint8($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if($val > 0xFF || $val < 0 || is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_int8($val)
{
  if(!is_numeric($val))
    throw new Exception("Bad item, not a number(" . serialize($val) . ")");
  $val = 1*$val;
  if($val > 127 || $val < -128 || is_float($val))
    throw new Exception("Value not in range: $val");
  return $val;
}

function mtg_php_val_arr($val)
{
  if(!is_array($val))
    throw new Exception("Bad item, not an array(" . serialize($val) . ")");
  return $val;
}

function mtg_php_val_enum($enum, $val, $check_enum_validity = true)
{
  if(is_string($val))
    return call_user_func_array(array($enum, "getValueByName"), array($val));

  if(!is_numeric($val))
    throw new Exception("Bad enum value, not a numeric or string(" . serialize($val) . ")");

  if($check_enum_validity)
    call_user_func_array(array($enum, "checkValidity"), array($val));
  return $val;
}
