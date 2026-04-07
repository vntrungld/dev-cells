# Dev Cells — Game Design

## Core Concept

A browser-only 2D grid game (100x100). Host opens a single HTML file, adds player endpoint URLs, and starts the game. Each tick, the browser calls every developer's endpoint with their cells' local vision, and the developer returns actions for each cell.

## Game Rules

### Cell

- Has one resource: **energy** (starts at 50)
- Dies when energy reaches 0
- Loses 1 energy per tick (passive drain)

### Actions (per cell per tick)

- `move` — up/down/left/right
- `clone` — up/down/left/right (costs 10 energy, new cell spawns with 10 energy)
- `idle` — do nothing

### Move Resolution

All actions resolve simultaneously each tick.

- **Move to empty cell** — move there
- **Move to food** — move there, gain 50 energy
- **Move to own cell** — fail, stay put
- **Move to enemy cell** — check numerical superiority (see Combat below)
- **Move to boundary** — stay put

### Combat

When a cell moves into an enemy cell:

1. Count attacker's cells adjacent to the target (including the moving cell)
2. Count defender's cells adjacent to the target (including the target itself)
3. If attacker count > defender count — target is destroyed, drops ALL its energy as food on that tile
4. If attacker count <= defender count — attack fails, attacker loses 2 energy, stays put

There is no solo "eat smaller cell" mechanic. Killing requires outnumbering.

### Conflict Resolution

- **Two cells move to same empty tile:**
  - Same owner — first by cell ID gets the tile, other stays put
  - Different owners — bigger eats smaller (lose 2 energy). If equal, both stay put and lose 2 energy
- **Cell moves to a tile where another cell is leaving:** leaving cell moves out first, then moving-in cell takes the tile
- **Clone to an occupied tile:** clone fails, energy is still spent
- **Two cells eat same food:** first by cell ID gets it, other moves to tile but gets no food

### Energy Economy

| Event | Energy Change |
|-------|--------------|
| Passive drain (per tick) | -1 |
| Eat food | +50 |
| Clone | -10 (parent), new cell starts with 10 |
| Failed attack | -2 |
| Kill enemy cell | target drops all energy as food |

### Food

- Spawns randomly on empty tiles every 2 ticks

### Win Condition

- **Last developer with cells alive wins**, OR
- **Highest total energy when time limit is reached**

## Game Configuration

- Grid size: 100x100
- Players: set by host before game starts
- Tick speed: configurable (default ~500ms)
- Time limit: configurable
- Vision radius: fixed per cell (e.g. 5 tiles)
- Starting energy: 50

## Architecture

### Single HTML File

Everything runs in one `index.html` — HTML, CSS, JS. No build tools, no server.

### Game Loop (each tick)

1. Spawn food (every 2 ticks)
2. POST to each developer endpoint with all their cells and vision data
3. Collect responses (cell ID -> action mapping)
4. Resolve all actions simultaneously
5. Apply passive energy drain (-1 per cell)
6. Remove dead cells (energy <= 0)
7. Check win condition
8. Render grid on canvas

### Developer Endpoint Contract

**Request (POST):**

```json
{
  "tick": 42,
  "cells": [
    {
      "id": 1,
      "x": 10,
      "y": 20,
      "energy": 35,
      "vision": {
        "cells": [{"x": 12, "y": 20, "energy": 20, "owner": "other"}],
        "food": [{"x": 11, "y": 21}]
      }
    }
  ]
}
```

**Response:**

```json
{
  "actions": {
    "1": {"type": "move", "direction": "right"},
    "2": {"type": "clone", "direction": "up"}
  }
}
```

Directions: `up`, `down`, `left`, `right`
Action types: `move`, `clone`, `idle`

## UI Layout

### Canvas (left)

- 100x100 grid on HTML canvas
- Each player has a distinct color
- Food shown as distinct color/shape
- Cell energy displayed as number or size

### Sidebar (right)

- **Lobby:** add player (name + endpoint URL), Start/Pause/Reset, tick speed slider
- **Scoreboard:** player name, color, cell count, total energy (updated each tick)
- **Game log:** key events (kills, clones, deaths)
- **Tick counter** and game timer
