# Dev Cells Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a browser-only 2D grid game where developers control cells via HTTP endpoints, competing to be the last one standing.

**Architecture:** Single `index.html` file with embedded CSS and JS. No dependencies, no build tools. Game engine runs in the browser, calls developer endpoints via fetch each tick. Canvas renders the 100x100 grid.

**Tech Stack:** Vanilla HTML/CSS/JS, Canvas API, fetch API.

---

### Task 1: HTML scaffold with lobby UI

**Files:**
- Create: `index.html`

**Step 1: Create the HTML file with layout and lobby**

Create `index.html` with:
- CSS grid layout: canvas area (left) + sidebar (right)
- Sidebar contains:
  - Lobby section: player name input, endpoint URL input, "Add Player" button, player list with remove buttons
  - Game controls: Start, Pause, Reset buttons. Tick speed slider (100ms-2000ms, default 500ms). Time limit input (ticks, default 1000).
  - Scoreboard section (empty, populated during game)
  - Game log section (scrollable, empty)
  - Tick counter display
- Canvas element sized to fill the left area
- Dark theme styling (good for presentation)
- All players shown in lobby list with color swatch, name, URL, and remove button
- Player colors assigned automatically from a preset palette

**Step 2: Verify in browser**

Open `index.html` in browser. Verify:
- Layout renders correctly (canvas left, sidebar right)
- Can type player name and URL, click Add Player, see player in list
- Can remove player from list
- Start/Pause/Reset buttons visible
- Tick speed slider works

**Step 3: Commit**

```bash
git add index.html
git commit -m "Update: add HTML scaffold with lobby UI"
```

---

### Task 2: Game state and grid engine

**Files:**
- Modify: `index.html` (add JS at bottom)

**Step 1: Implement game state module**

Add a `<script>` section to `index.html` with the game engine. Implement:

```javascript
// Game constants
const GRID_SIZE = 100;
const STARTING_ENERGY = 50;
const FOOD_ENERGY = 50;
const CLONE_COST = 10;
const CLONE_START_ENERGY = 10;
const TICK_DRAIN = 1;
const ATTACK_COST = 2;
const FOOD_SPAWN_INTERVAL = 2; // every 2 ticks
const VISION_RADIUS = 5;

// Game state
const state = {
  grid: [],           // 2D array [y][x] -> null | {type: 'cell', id, playerId, energy} | {type: 'food'}
  players: [],        // [{id, name, url, color, alive}]
  cells: [],          // [{id, playerId, x, y, energy}]
  tick: 0,
  running: false,
  nextCellId: 1,
  foodCount: 0,
  tickSpeed: 500,
  timeLimit: 1000,
  log: []
};
```

Implement these functions:
- `initGrid()` — create 100x100 grid filled with null
- `addPlayer(name, url)` — add player to state, return player object
- `removePlayer(id)` — remove player and their cells
- `spawnCell(playerId, x, y, energy)` — place a cell on the grid
- `spawnFood()` — place food at a random empty tile
- `getVision(cell)` — return cells and food within VISION_RADIUS of a cell
- `getCellAt(x, y)` — return cell at position or null
- `getFoodAt(x, y)` — return true if food at position
- `isInBounds(x, y)` — check if coordinates are within grid

**Step 2: Test manually in browser console**

Open browser console, run:
```javascript
initGrid();
addPlayer('test', 'http://localhost:3000');
spawnCell(0, 50, 50, 50);
spawnFood();
console.log(state);
```

Verify state is populated correctly.

**Step 3: Commit**

```bash
git add index.html
git commit -m "Update: add game state and grid engine"
```

---

### Task 3: Canvas renderer

**Files:**
- Modify: `index.html` (add rendering code)

**Step 1: Implement grid renderer**

Add rendering functions:

- `render()` — main render function, called each tick and on resize:
  - Clear canvas
  - Calculate cell pixel size based on canvas dimensions / GRID_SIZE
  - Draw grid background (subtle grid lines)
  - Draw food tiles (green squares)
  - Draw player cells (player color, with energy number if cell is large enough to show text)
  - Draw scoreboard in sidebar (player name, color, cell count, total energy)
  - Update tick counter display

- Handle canvas sizing:
  - Canvas should fill available space in the left panel
  - Handle window resize
  - Keep aspect ratio square (100x100 grid)

- Color scheme:
  - Background: dark gray (#1a1a1a)
  - Grid lines: subtle (#2a2a2a)
  - Food: bright green (#4ade80)
  - Player colors: distinct preset palette (red, blue, yellow, purple, cyan, orange, pink, lime)

**Step 2: Test rendering**

Open browser, add a player, manually call:
```javascript
initGrid();
spawnCell(0, 50, 50, 50);
spawnCell(0, 51, 50, 30);
spawnFood();
render();
```

Verify cells and food render on canvas with correct colors and positions.

**Step 3: Commit**

```bash
git add index.html
git commit -m "Update: add canvas grid renderer"
```

---

### Task 4: Tick engine and endpoint communication

**Files:**
- Modify: `index.html` (add tick logic)

**Step 1: Implement the tick engine**

Add tick functions:

- `buildRequest(player)` — for a given player, build the POST body:
  - Find all cells belonging to player
  - For each cell, compute vision (cells and food within VISION_RADIUS)
  - In vision, mark other players' cells as `owner: "other"`, own cells as `owner: "self"`
  - Return `{tick, cells: [{id, x, y, energy, vision: {cells, food}}]}`

- `callEndpoint(player)` — POST to player.url with JSON body, return parsed response. Timeout after 1 second. On failure, return empty actions (all cells idle).

- `tick()` — one game tick:
  1. Increment tick counter
  2. Spawn food if `tick % FOOD_SPAWN_INTERVAL === 0`
  3. For each alive player, call `buildRequest` and `callEndpoint` (all in parallel via `Promise.all`)
  4. Collect all actions: `{cellId: {type, direction}}`
  5. Call `resolveActions(allActions)` (Task 5)
  6. Apply passive drain: each cell loses TICK_DRAIN energy
  7. Remove dead cells (energy <= 0), log deaths
  8. Check win condition
  9. Call `render()`
  10. If still running and not won, schedule next tick via `setTimeout(tick, state.tickSpeed)`

- `startGame()` — spawn one cell per player at random non-overlapping positions, set running=true, call tick()
- `pauseGame()` — set running=false
- `resetGame()` — clear all state, re-init grid

Wire up Start/Pause/Reset buttons to these functions.

**Step 2: Test with a mock endpoint**

Create a simple test: temporarily hardcode a player with a fake URL. Verify tick() calls are made (check network tab). Verify food spawns every 2 ticks. Verify passive drain reduces energy.

**Step 3: Commit**

```bash
git add index.html
git commit -m "Update: add tick engine and endpoint communication"
```

---

### Task 5: Action resolution

**Files:**
- Modify: `index.html` (add resolution logic)

**Step 1: Implement simultaneous action resolution**

This is the core game logic. Implement `resolveActions(allActions)`:

```
allActions = [{cellId, playerId, type, direction}]
```

Resolution algorithm:

1. **Phase 1: Compute intended positions**
   - For each action, compute the target (x, y) based on direction
   - For `clone` actions, target is where the new cell would spawn
   - For `idle`, no movement

2. **Phase 2: Resolve clones**
   - For each clone action:
     - If parent has >= CLONE_COST energy AND target tile is empty (no cell, no food):
       - Deduct CLONE_COST from parent
       - Create new cell at target with CLONE_START_ENERGY
     - Else: clone fails, energy still spent if parent had enough

3. **Phase 3: Resolve moves**
   - Group move actions by target tile
   - For each target tile with exactly one mover:
     - **Empty tile:** move cell there
     - **Food tile:** move cell there, add FOOD_ENERGY to cell, remove food
     - **Own cell at target:** fail, stay put
     - **Enemy cell at target:** resolve combat
   - For each target tile with multiple movers:
     - Apply conflict resolution rules (see design doc)

4. **Phase 4: Resolve combat**
   - For each cell attempting to move into an enemy cell:
     - Count attacker's allies adjacent to target (including the moving cell itself)
     - Count defender's allies adjacent to target (including the target itself)
     - Adjacent = up/down/left/right (4 directions)
     - If attacker count > defender count:
       - Target is destroyed
       - Drop target's energy as food on that tile
       - Attacker moves into the tile (and eats the food immediately)
       - Log: "{attacker.player} killed {defender.player}'s cell"
     - Else:
       - Attack fails
       - Attacker loses ATTACK_COST energy
       - Attacker stays put

**Step 2: Test resolution manually**

In browser console, set up scenarios:
- Two cells moving to same empty tile
- Cell moving to food
- Cell attacking enemy with more allies nearby
- Cell attacking enemy with fewer allies (should fail)
- Clone action

Verify each resolves correctly.

**Step 3: Commit**

```bash
git add index.html
git commit -m "Update: add simultaneous action resolution"
```

---

### Task 6: Game log, scoreboard, and win condition

**Files:**
- Modify: `index.html` (add UI updates)

**Step 1: Implement game log**

- `addLog(message)` — prepend timestamped message to log section, auto-scroll
- Log these events:
  - Game started with N players
  - Player cell killed by another player
  - Player eliminated (all cells dead)
  - Player cloned
  - Game over: winner announcement

**Step 2: Implement scoreboard**

- Update scoreboard each tick in `render()`:
  - Player name with color swatch
  - Cell count
  - Total energy
  - Status (alive/eliminated)
  - Sort by total energy descending

**Step 3: Implement win condition**

In `checkWinCondition()`:
- Count alive players (players with at least one cell)
- If exactly 1 alive player: they win, stop game, log winner
- If 0 alive players: draw, stop game
- If tick >= timeLimit: player with highest total energy wins, stop game

**Step 4: Commit**

```bash
git add index.html
git commit -m "Update: add game log, scoreboard, and win condition"
```

---

### Task 7: Sample bot endpoint

**Files:**
- Create: `sample-bot/bot.py`

**Step 1: Create a simple Python bot**

A minimal Flask/http.server bot that developers can use as a starting point:

```python
from http.server import HTTPServer, BaseHTTPRequestHandler
import json
import random

class BotHandler(BaseHTTPRequestHandler):
    def do_POST(self):
        content_length = int(self.headers['Content-Length'])
        body = json.loads(self.rfile.read(content_length))

        actions = {}
        for cell in body['cells']:
            # Simple strategy: move toward nearest food, or random direction
            food = cell['vision']['food']
            if food:
                nearest = min(food, key=lambda f: abs(f['x'] - cell['x']) + abs(f['y'] - cell['y']))
                dx = nearest['x'] - cell['x']
                dy = nearest['y'] - cell['y']
                if abs(dx) > abs(dy):
                    direction = 'right' if dx > 0 else 'left'
                else:
                    direction = 'down' if dy > 0 else 'up'

                # Clone if enough energy and no nearby food threat
                if cell['energy'] > 30:
                    actions[str(cell['id'])] = {'type': 'clone', 'direction': direction}
                else:
                    actions[str(cell['id'])] = {'type': 'move', 'direction': direction}
            else:
                direction = random.choice(['up', 'down', 'left', 'right'])
                actions[str(cell['id'])] = {'type': 'move', 'direction': direction}

        self.send_response(200)
        self.headers['Content-Type'] = 'application/json'
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps({'actions': actions}).encode())

    def log_message(self, format, *args):
        pass  # Suppress request logging

if __name__ == '__main__':
    import sys
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 3000
    server = HTTPServer(('localhost', port), BotHandler)
    print(f'Bot running on http://localhost:{port}')
    server.serve_forever()
```

No dependencies — uses only Python stdlib.

**Step 2: Test the bot**

```bash
python sample-bot/bot.py 3000 &
curl -X POST http://localhost:3000 -H "Content-Type: application/json" -d '{"tick":1,"cells":[{"id":1,"x":50,"y":50,"energy":50,"vision":{"cells":[],"food":[{"x":52,"y":50}]}}]}'
```

Verify it returns a valid action response.

**Step 3: Commit**

```bash
git add sample-bot/bot.py
git commit -m "Update: add sample Python bot endpoint"
```

---

### Task 8: Integration test — full game with 2 bots

**Step 1: End-to-end test**

1. Start two bot instances: `python sample-bot/bot.py 3000` and `python sample-bot/bot.py 3001`
2. Open `index.html` in browser
3. Add Player 1: "Bot A" / `http://localhost:3000`
4. Add Player 2: "Bot B" / `http://localhost:3001`
5. Click Start

Verify:
- Cells appear on grid with distinct colors
- Cells move each tick
- Food spawns every 2 ticks
- Energy drains over time
- Cells eat food and gain energy
- Cloning works (new cells appear)
- Combat works when cells collide
- Scoreboard updates
- Game log shows events
- Game ends when one player is eliminated or time limit reached

**Step 2: Fix any bugs found**

**Step 3: Commit any fixes**

```bash
git add index.html
git commit -m "Fix: resolve integration issues from end-to-end test"
```

---

## Task Summary

| Task | Description | Depends On |
|------|-------------|------------|
| 1 | HTML scaffold with lobby UI | — |
| 2 | Game state and grid engine | 1 |
| 3 | Canvas renderer | 2 |
| 4 | Tick engine and endpoint communication | 2 |
| 5 | Action resolution | 4 |
| 6 | Game log, scoreboard, win condition | 3, 5 |
| 7 | Sample bot endpoint | — |
| 8 | Integration test | 6, 7 |
