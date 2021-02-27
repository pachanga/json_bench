using System;
using System.IO;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Text;
using System.Threading;
using Newtonsoft.Json;

public static class BenchTest
{
  public static void Main(string[] args)
  {
    var dir = Path.GetDirectoryName(System.Reflection.Assembly.GetExecutingAssembly().Location);
    var sw = new Stopwatch();
    sw.Start();
    var files = Directory.GetFiles(dir + "/../files", "*.js", SearchOption.AllDirectories);
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
        var obj = JsonConvert.DeserializeObject<Dictionary<string,object>>(json);
        if(obj == null)
          throw new Exception("Could not parse json");
      }
      Console.WriteLine($"Done worker from {idx}, count {count}");
    };
  }
}
