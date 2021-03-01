package main

import (
	"log"
	"os"
	"path/filepath"
	"strings"
	"time"
)

func main() {
	t := time.Now()
	files := make([]string, 0, 0)
	err := filepath.Walk(os.Args[1],
		func(path string, info os.FileInfo, err error) error {
			if err != nil {
				return err
			}
			if !info.IsDir() && strings.HasSuffix(path, ".js") {
				files = append(files, path)
			}
			return nil
		})
	if err != nil {
		panic(err)
	}
	log.Printf("Scan(%d): %s", len(files), time.Since(t))
}
