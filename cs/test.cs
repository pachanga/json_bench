using System;
using System.IO;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Text;
using System.Threading;

public static class BenchTest
{
  public static void Main(string[] args)
  {
    var dir = args[0];
    var sw = new Stopwatch();
    sw.Start();
    var files = Directory.GetFiles(dir, "*.js", SearchOption.AllDirectories);
    Console.WriteLine($"Scan: {Math.Round(sw.ElapsedMilliseconds/1000.0f,2)}");
    Console.WriteLine("Files: " + files.Length);

    sw.Start();

    int max_threads = 10;

    int files_per_worker = files.Length < max_threads ? files.Length : (int)Math.Ceiling((float)files.Length / (float)max_threads);
    int idx = 0;

    var threads = new List<Thread>();
    while(idx < files.Length)
    {
      int count = (idx + files_per_worker) > files.Length ? (files.Length - idx) : files_per_worker; 

      var th = new Thread(MakeWorker(idx, count, files));
      threads.Add(th);
      th.Start();

      idx += count;
    }

    foreach(var th in threads)
      th.Join();

    //foreach(var file in files)
    //{
    //  var json = File.ReadAllText(file);
    //  var obj = JsonConvert.DeserializeObject<Dictionary<string,object>>(json);
    //  if(obj == null)
    //    throw new Exception("Could not parse json");
    //}

    Console.WriteLine($"Parse: {Math.Round(sw.ElapsedMilliseconds/1000.0f,2)}");
  }

  static ThreadStart MakeWorker(int idx, int count, IList<string> files)
  {
    return () =>
    {
      Console.WriteLine($"Starting worker from {idx}, count {count}, files {files.Count}");
      for(int i=idx;i<(idx+count);++i)
      {
        var file = files[i];
        var json = File.ReadAllText(file);
        //var obj = ServiceStack.Text.JsonSerializer.DeserializeFromString<M3ConfLevel>(json);
        //var obj = ServiceStack.Text.JsonSerializer.Deserialize<Dictionary<string,object>>(json);
        //Newtonsoft
        var obj = Newtonsoft.Json.JsonConvert.DeserializeObject<M3ConfLevel>(json);
        //standard
        //var obj = System.Text.Json.JsonSerializer.Deserialize<M3ConfLevel>(json, new System.Text.Json.JsonSerializerOptions { ReadCommentHandling = System.Text.Json.JsonCommentHandling.Skip });
        if(obj == null)
          throw new Exception("Could not parse json");
        if(obj.turns_limit == 0)
          throw new Exception("Turns limit = 0");
        //Console.WriteLine($"File {file}, turns limit {obj.turns_limit}, fields: {obj.fields.Count}");
      }
      Console.WriteLine($"Done worker from {idx}, count {count}");
    };
  }
}

public enum M3LevelOfDifficulty {
    Normal = 0,
    Hard = 1,
    VeryHard = 2,
}

public enum M3SpawnIcon {
    clear = 0,
    def = 1,
    rainbow = 2,
    rocket = 3,
    bbomb = 4,
    hbomb = 5,
    vbomb = 6,
    some_bombs = 7,
    hbomb_rocket = 8,
    vbomb_rocket = 9,
    plate = 10,
    bbomb_hbomb = 11,
    bbomb_plate = 12,
    bbomb_rocket = 13,
    bbomb_vbomb = 14,
    hbomb_plate = 15,
    hbomb_vbomb_plate = 16,
    hbomb_vbomb_rocket = 17,
    rocket_plate = 18,
    vbomb_hbomb = 19,
    vbomb_plate = 20,
}

[System.Serializable] 
public class M3ConfLevelSpawnObj
{
  public uint chip;
  public uint chip_health;
  public uint layer0;
  public uint layer0_health;
}

[System.Serializable] 
public class M3ConfLevelSpawnChance
{
  public uint spawner_id;
  public M3ConfLevelSpawnObj obj = new M3ConfLevelSpawnObj();
  public int chance;
  public bool skip_for_init;
  public uint max_on_screen;
  public uint max_to_spawn;
  public uint force_period;
  public uint min_period;
  public uint max_period;
  public M3SpawnIcon icon = new M3SpawnIcon();
  public List<M3ConfLevelSpawnObj> initial_sequence_chips = new List<M3ConfLevelSpawnObj>();
  public bool initial_chips_for_instance;
  public bool min_on_screen;
}

public enum M3GoalType {
    REMOVE = 0,
    FILL_MAT = 1,
    REMOVE_ALL = 2,
    REMOVE_TAG = 3,
}

[System.Serializable] 
public class M3ConfLevelFieldZone
{
  public List<M3ConfLevelGoal> goals = new List<M3ConfLevelGoal>();
  public M3ConfPos pos = new M3ConfPos();
  public int width;
  public int height;
}

[System.Serializable] 
public struct M3ConfPos
{
  public int x;
  public int y;
}

[System.Serializable] 
public class M3ConfLevelField
{
  public int width;
  public int height;
  public List<M3ConfLevelFieldZone> zones = new List<M3ConfLevelFieldZone>();
  public M3Dir next_transition = new M3Dir();
  public List<M3ConfLevelCell> cells = new List<M3ConfLevelCell>();
  public List<M3ConfLevelWall> walls = new List<M3ConfLevelWall>();
  public List<M3ConfLevelPortal> portals = new List<M3ConfLevelPortal>();
  public List<M3ConfLevelBarrier> barriers = new List<M3ConfLevelBarrier>();
  public List<M3ConfLevelGoal> goals = new List<M3ConfLevelGoal>();
}

public enum M3Dir {
    DOWN = 0,
    UP = 1,
    LEFT = 2,
    RIGHT = 3,
    NONE = 4,
}

[System.Serializable] 
public class M3ConfTutorialPreset
{
  //public uint proto_id;
  public string proto_id;
  [System.NonSerialized] public int rseed;
}

[System.Serializable] 
public class M3ConfLevelGoal
{
  public uint id;
  public int amount;
  public M3GoalType type = new M3GoalType();
}

[System.Serializable] 
public class M3ConfLevelCell
{
  public M3ConfPos pos = new M3ConfPos();
  public M3Dir gravity = new M3Dir();
  public uint spawner;
  public M3Covers chip_cover = new M3Covers();
  public uint chip_cover_health;
  public M3Chips chip = new M3Chips();
  public uint chip_health;
  public M3ChipsLayered chip_layer0 = new M3ChipsLayered();
  public M3ChipsLayered chip_layer1 = new M3ChipsLayered();
  public M3Mats chip_mat = new M3Mats();
  public uint chip_layer0_health;
  public M3ChipsBlocker chip_blocker = new M3ChipsBlocker();
  public uint chip_blocker_health;
  public M3ChipsBlocked chip_blocked = new M3ChipsBlocked();
  public M3Belts chip_belt = new M3Belts();
  public M3ConfPos chip_belt_next = new M3ConfPos();
  public bool protected_from_starting_boosters;
  public M3ChipsRider chip_marker = new M3ChipsRider();
}

public enum M3Covers {
    clear = 0,
    web = 58458845,
}

public enum M3Belts {
    clear = 0,
    generic = 39232813,
}

public enum M3Barriers {
    clear = 0,
    barrier = 150329024,
}

public enum M3Mats {
  
    clear = 0,
    carpet = 37925772,
}

public enum M3ChipsLayered {
  
    clear = 0,
    chain = 103514130,
    jelly = 168633071,
    ice = 109310110,
    box = 34681458,
    mouse_hole = 203259574,
    soap_bubble = 131958942,
}
public enum M3Walls {
    clear = 0,
    generic = 165432907,
    armored = 196666317,
}

[System.Serializable] 
public class M3ConfLevelWall
{
  public M3ConfPos pos = new M3ConfPos();
  public M3Dir side = new M3Dir();
  public M3Walls type = new M3Walls();
}

public enum M3LevelSpecificsType {
    None = 0,
    SetDefaultRocketTargets = 1,
    SetSpawnerForGift = 2,
    SetSettingsActivePhones = 3,
    SetSettingsPaintTray = 4,
    SetSettingsBoxWithRing = 5,
    BowknotRibbonLink = 6,
    SetSpawnChipForLayer = 7,
    SetSettingsMarker = 8,
    RandomRingInBox = 9,
    AddBurningBoosters = 10,
}

[System.Serializable] 
public class M3ConfLevelSpecifics
{
  public M3LevelSpecificsType type = new M3LevelSpecificsType();
  public List<int> sparams = new List<int>();
}

[System.Serializable] 
public class M3ConfLevel
{
  public string @class;
  public int turns_limit;
  public List<M3ConfLevelSpawnChance> spawn_chances = new List<M3ConfLevelSpawnChance>();
  public string tileset_back = "";
  public List<int> rseeds = new List<int>();
  public List<M3ConfLevelSpecifics> specifics = new List<M3ConfLevelSpecifics>();
  public List<M3ConfLevelField> fields = new List<M3ConfLevelField>();
  public List<M3ConfTutorialPreset> tutorial = new List<M3ConfTutorialPreset>();
  public M3LevelOfDifficulty difficulty = new M3LevelOfDifficulty();
  public string comment = "";
  public uint revision;
  public int bg_image_index;
}

public enum M3Chips {
  
    clear = 0,
    random = 159587649,
    empty = 149856411,
    green = 173571093,
    red = 20043541,
    blue = 152790968,
    pink = 266541332,
    yellow = 152369935,
    cyan = 17824785,
    box = 34681458,
    plate = 182865318,
    key = 165835097,
    flower = 17201424,
    chain = 103514130,
    jelly = 168633071,
    ice = 109310110,
    gift = 130928691,
    candle = 47307072,
    rocket = 158564410,
    bbomb = 203671966,
    vbomb = 144508370,
    hbomb = 108834744,
    rainbow = 4153540,
    tray = 150880180,
    lyre = 223856470,
    lyre_note = 72057805,
    random_1 = 220370293,
    random_2 = 78621104,
    random_3 = 64273907,
    random_4 = 129355834,
    foam = 107133260,
    feather = 99200788,
    pillow = 264630631,
    soap = 156014744,
    soap_bubble = 131958942,
    phone = 219800740,
    paint_tray_3x1 = 27253688,
    paint_tray_2x1 = 20890305,
    mouse = 77815192,
    mouse_hole = 203259574,
    box_with_ring = 156916201,
    cuckoo_clock = 268228540,
    ring = 99382431,
    bowknot = 169073011,
    bowknot_violet = 204595850,
    bowknot_ribbon = 126361439,
    bowknot_unlink = 7588332,
}

public enum M3ChipsRider {
  
    clear = 0,
    bubble = 146540445,
}

public enum M3ChipsBlocker {
  
    clear = 0,
    glass = 144139772,
    tile = 13773252,
    honey = 69201358,
    converter_yellow = 252274553,
    converter_cyan = 265756,
    converter_blue = 135304629,
    converter_green = 77073242,
    converter_pink = 250145561,
    converter_red = 189158225,
}

public enum M3ChipsBlocked {
  
    clear = 0,
    letter_2x1 = 37280846,
    letter_3x1 = 62260110,
    letter_3x2 = 171654987,
    letter_4x2 = 50342600,
    letter_4x3 = 75185803,
    letter_5x3 = 100000075,
    letter_5x4 = 26512514,
    letter_2x1v = 267755988,
    letter_3x1v = 72412603,
    letter_3x2v = 258430648,
    letter_4x2v = 201310007,
    letter_4x3v = 71160841,
    letter_5x3v = 261658726,
    letter_5x4v = 222593823,
}

[System.Serializable] 
public class M3ConfLevelPortal
{
  public M3ConfPos pos = new M3ConfPos();
  public M3Dir side = new M3Dir();
  public M3Portals type = new M3Portals();
  public List<M3ConfPortalLink> link = new List<M3ConfPortalLink>();
}

[System.Serializable] 
public class M3ConfLevelBarrier
{
  public M3ConfPos pos = new M3ConfPos();
  public M3Barriers type = new M3Barriers();
  public int width;
  public int height;
  public uint goal_id;
  public int goal_amount;
  public M3GoalType goal_type = new M3GoalType();
}

public enum M3Portals {
  
    clear = 0,
    for_keys = 210966518,
    entrance = 83030634,
    exit = 80246951,
}

[System.Serializable] 
public class M3ConfPortalLink
{
  public M3ConfPos pos = new M3ConfPos();
  public M3Dir side = new M3Dir();
}
