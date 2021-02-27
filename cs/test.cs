using System;
using System.IO;
using System.Collections.Generic;
using System.Diagnostics;
using System.Linq;
using System.Text;

public static class BenchTest
{
  public static void Main(string[] args)
  {
    var dir = Path.GetDirectoryName(System.Reflection.Assembly.GetExecutingAssembly().Location);
    var sw = new Stopwatch();
    sw.Start();
    var files = Directory.GetFiles(dir + "/../files", "*.js", SearchOption.AllDirectories);
    var elapsed = Math.Round(sw.ElapsedMilliseconds/1000.0f,2);
    Console.WriteLine($"Scan: {elapsed}");
    Console.WriteLine("Files: " + files.Length);
  }
}
