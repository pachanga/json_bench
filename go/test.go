package main

import (
	"encoding/json"
	"io/ioutil"
	"log"
	"os"
	"path/filepath"
	"regexp"
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

	var re = regexp.MustCompile(`/\*[^\*]+\*/`)

	t = time.Now()
	for idx, file := range files {
		if idx > 0 && idx%1000 == 0 {
			log.Printf("Progress %f %%", float32(idx)/float32(len(files))*100)
		}
		bytes, err := ioutil.ReadFile(file)
		if err != nil {
			panic(err)
		}
		content := string(bytes[:])
		content = re.ReplaceAllString(content, ``)
		var lvl M3ConfLevel
		err = json.Unmarshal([]byte(content), &lvl)
		if err != nil {
			log.Fatalf("%s : %v", file, err)
		}
	}
	log.Printf("Parse: %s", time.Since(t))
}

type M3ConfLevel struct {
	Turns_limit    int32                     `json:"turns_limit"`
	Spawn_chances  []*M3ConfLevelSpawnChance `json:"spawn_chances"`
	Tileset_back   string                    `json:"tileset_back"`
	Rseeds         []int32                   `json:"rseeds"`
	Specifics      []*M3ConfLevelSpecifics   `json:"specifics"`
	Fields         []*M3ConfLevelField       `json:"fields"`
	Tutorial       []*M3ConfTutorialPreset   `json:"tutorial"`
	Difficulty     M3LevelOfDifficulty       `json:"difficulty"`
	Comment        string                    `json:"comment"`
	Revision       uint32                    `json:"revision"`
	Bg_image_index int32                     `json:"bg_image_index"`
}

type M3ConfLevelSpawnChance struct {
	Spawner_id                 uint32                 `json:"spawner_id"`
	Obj                        M3ConfLevelSpawnObj    `json:"obj"`
	Chance                     int32                  `json:"chance"`
	Skip_for_init              bool                   `json:"skip_for_init"`
	Max_on_screen              uint32                 `json:"max_on_screen"`
	Max_to_spawn               uint32                 `json:"max_to_spawn"`
	Force_period               uint32                 `json:"force_period"`
	Min_period                 uint32                 `json:"min_period"`
	Max_period                 uint32                 `json:"max_period"`
	Icon                       M3SpawnIcon            `json:"icon"`
	Initial_sequence_chips     []*M3ConfLevelSpawnObj `json:"initial_sequence_chips"`
	Initial_chips_for_instance bool                   `json:"initial_chips_for_instance"`
	Min_on_screen              bool                   `json:"min_on_screen"`
}

type M3ConfLevelSpawnObj struct {
	Chip          uint32 `json:"chip"`
	Chip_health   uint32 `json:"chip_health"`
	Layer0        uint32 `json:"layer0"`
	Layer0_health uint32 `json:"layer0_health"`
}

type M3SpawnIcon int32

type M3ConfLevelCell struct {
	Pos                              M3ConfPos      `json:"pos"`
	Gravity                          M3Dir          `json:"gravity"`
	Spawner                          uint32         `json:"spawner"`
	Chip_cover                       M3Covers       `json:"chip_cover"`
	Chip_cover_health                uint32         `json:"chip_cover_health"`
	Chip                             M3Chips        `json:"chip"`
	Chip_health                      uint32         `json:"chip_health"`
	Chip_layer0                      M3ChipsLayered `json:"chip_layer0"`
	Chip_layer1                      M3ChipsLayered `json:"chip_layer1"`
	Chip_mat                         M3Mats         `json:"chip_mat"`
	Chip_layer0_health               uint32         `json:"chip_layer0_health"`
	Chip_blocker                     M3ChipsBlocker `json:"chip_blocker"`
	Chip_blocker_health              uint32         `json:"chip_blocker_health"`
	Chip_blocked                     M3ChipsBlocked `json:"chip_blocked"`
	Chip_belt                        M3Belts        `json:"chip_belt"`
	Chip_belt_next                   M3ConfPos      `json:"chip_belt_next"`
	Protected_from_starting_boosters bool           `json:"protected_from_starting_boosters"`
	Chip_marker                      M3ChipsRider   `json:"chip_marker"`
}

type M3ConfLevelSpecifics struct {
	Type    M3LevelSpecificsType `json:"type"`
	Sparams []int32              `json:"sparams"`
}

type M3Dir int32
type M3Covers int32
type M3ChipsBlocker int32
type M3ChipsBlocked int32
type M3Belts int32
type M3ChipsRider int32
type M3Chips int32
type M3ChipsLayered int32
type M3Mats int32
type M3LevelSpecificsType int32
type M3GoalType int32
type M3Walls int32
type M3Portals int32
type M3Barriers int32
type M3LevelOfDifficulty int32

type M3ConfPos struct {
	X int32 `json:"x"`
	Y int32 `json:"y"`
}

type M3ConfLevelField struct {
	Width           int32                   `json:"width"`
	Height          int32                   `json:"height"`
	Zones           []*M3ConfLevelFieldZone `json:"zones"`
	Next_transition M3Dir                   `json:"next_transition"`
	Cells           []*M3ConfLevelCell      `json:"cells"`
	Walls           []*M3ConfLevelWall      `json:"walls"`
	Portals         []*M3ConfLevelPortal    `json:"portals"`
	Barriers        []*M3ConfLevelBarrier   `json:"barriers"`
	Goals           []*M3ConfLevelGoal      `json:"goals"`
}

type M3ConfLevelFieldZone struct {
	Goals  []*M3ConfLevelGoal `json:"goals"`
	Pos    M3ConfPos          `json:"pos"`
	Width  int32              `json:"width"`
	Height int32              `json:"height"`
}

type M3ConfLevelGoal struct {
	Id     uint32     `json:"id"`
	Amount int32      `json:"amount"`
	Type   M3GoalType `json:"type"`
}

type M3ConfLevelWall struct {
	Pos  M3ConfPos `json:"pos"`
	Side M3Dir     `json:"side"`
	Type M3Walls   `json:"type"`
}

type M3ConfLevelPortal struct {
	Pos  M3ConfPos           `json:"pos"`
	Side M3Dir               `json:"side"`
	Type M3Portals           `json:"type"`
	Link []*M3ConfPortalLink `json:"link"`
}

type M3ConfLevelBarrier struct {
	Pos         M3ConfPos  `json:"pos"`
	Type        M3Barriers `json:"type"`
	Width       int32      `json:"width"`
	Height      int32      `json:"height"`
	Goal_id     uint32     `json:"goal_id"`
	Goal_amount int32      `json:"goal_amount"`
	Goal_type   M3GoalType `json:"goal_type"`
}

type M3ConfPortalLink struct {
	Pos  M3ConfPos `json:"pos"`
	Side M3Dir     `json:"side"`
}

type M3ConfTutorialPreset struct {
	//Proto_id uint32 `json:"proto_id"`
	Rseed int32 `json:"rseed"`
}
