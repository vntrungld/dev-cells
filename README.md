# Dev Cells

A competitive 2D grid game where developers write bots that fight for survival. Each developer controls cells on a 100x100 grid through an HTTP endpoint. The game board runs entirely in the browser — no server needed.

## How It Works

1. The host opens `index.html` in a browser
2. Adds players by entering each developer's name and bot endpoint URL
3. Clicks Start — the browser calls every bot endpoint each tick
4. Bots respond with actions for their cells
5. Last developer standing wins

## Quick Start

**Start sample bots** (pick your language):

```bash
# Python
python sample-bot/bot.py 3000

# Node.js
node sample-bot/bot.js 3000

# PHP
php sample-bot/bot.php 3000
```

**Or start 20 bots at once for testing:**

```bash
bash sample-bot/run-bots.sh
```

Then open `index.html`, add players (or click "Seed 20 Bots"), and hit Start.

## Game Rules

### Your Cell

- Starts with **200 energy**
- Loses **1 energy per tick** (passive drain)
- Dies when energy reaches **0**

### Actions

Each tick, your bot tells each cell what to do:

| Action  | Effect                                                                              |
|---------|-------------------------------------------------------------------------------------|
| `move`  | Move one tile (up/down/left/right)                                                  |
| `clone` | Spawn a new cell in a direction. Energy is split **50/50** between parent and child |
| `idle`  | Do nothing                                                                          |

### What Happens When You Move

- **Empty tile** — you move there
- **Food** — you move there and gain **100 energy**
- **Your own cell** — you stay put
- **Enemy cell** — combat (see below)
- **Out of bounds** — you stay put

### Combat

You can't kill a cell alone. You need **numerical superiority**.

When your cell moves into an enemy cell:

1. Count your cells adjacent to the target (including the attacker)
2. Count defender's cells adjacent to the target (including the target)
3. **Your count > their count** — target dies, drops all its energy as food, your cell moves in
4. **Your count ⇐ their count** — attack fails, you stay put

Adjacent means up/down/left/right (not diagonal).

**Example:** You have 3 cells surrounding an enemy. You attack with one of them. Attacker count = 3 (the mover + 2 adjacent allies). Defender count = 1 (just the target). 3 > 1, target dies.

### Food

- Spawns randomly every **2 ticks**
- **50 food items** on the grid at game start
- Gives **100 energy** when eaten

### Win Condition

- **Last developer with cells alive wins**, or
- **Highest total energy when time limit is reached**

## Writing Your Bot

Your bot is an HTTP server that handles POST requests. The game sends your bot the state of all your cells each tick, and your bot responds with an action for each cell.

### Endpoint Contract

**Request** (POST to your endpoint):

```json
{
  "tick": 42,
  "cells": [
    {
      "id": 1,
      "x": 10,
      "y": 20,
      "energy": 150,
      "vision": {
        "cells": [
          { "x": 12, "y": 20, "energy": 80, "owner": "EnemyBot" },
          { "x": 9, "y": 20, "energy": 120, "owner": "self" }
        ],
        "food": [
          { "x": 11, "y": 21 },
          { "x": 13, "y": 19 }
        ]
      }
    },
    {
      "id": 5,
      "x": 30,
      "y": 40,
      "energy": 45,
      "vision": {
        "cells": [],
        "food": [{ "x": 32, "y": 40 }]
      }
    }
  ]
}
```

**Response:**

```json
{
  "actions": {
    "1": { "type": "move", "direction": "right" },
    "5": { "type": "clone", "direction": "down" }
  }
}
```

### Request Fields

| Field                  | Description                                                                                |
|------------------------|--------------------------------------------------------------------------------------------|
| `tick`                 | Current tick number                                                                        |
| `cells`                | Array of your living cells                                                                 |
| `cells[].id`           | Unique cell ID (use as key in response)                                                    |
| `cells[].x`            | X position (0-99, left to right)                                                           |
| `cells[].y`            | Y position (0-99, top to bottom)                                                           |
| `cells[].energy`       | Current energy                                                                             |
| `cells[].vision.cells` | Visible cells within 15x15 area. `owner` is `"self"` for yours, otherwise the enemy's name |
| `cells[].vision.food`  | Visible food within 15x15 area                                                             |

### Response Fields

| Field                   | Description                                                 |
|-------------------------|-------------------------------------------------------------|
| `actions`               | Object mapping cell ID (as string) to action                |
| `actions[id].type`      | `"move"`, `"clone"`, or `"idle"`                            |
| `actions[id].direction` | `"up"`, `"down"`, `"left"`, `"right"` (not needed for idle) |

### Direction Map

```
         up (y-1)
          ^
left (x-1) <  > right (x+1)
          v
        down (y+1)
```

### Tips for Writing a Good Bot

- **Eat food early** — you lose 1 energy per tick, so find food fast or die
- **Clone strategically** — clones cost 10 energy but give you numbers for combat
- **Surround to kill** — you need more adjacent cells than the defender to win
- **Scout with clones** — each cell only sees a 15x15 area, spread out to find food
- **Don't waste moves on failed attacks** — failed attacks just waste a tick
- **Coordinate your cells** — you get all your cells in one request, plan group tactics
- **Respond fast** — your bot has 1 second to respond, or all cells idle that tick

### Minimal Bot Template

**Python:**

```python
from http.server import HTTPServer, BaseHTTPRequestHandler
import json, random

class Bot(BaseHTTPRequestHandler):
    def do_POST(self):
        data = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        actions = {}
        for cell in data['cells']:
            # Your logic here
            actions[str(cell['id'])] = {'type': 'move', 'direction': random.choice(['up','down','left','right'])}
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps({'actions': actions}).encode())
    def log_message(self, *a): pass

HTTPServer(('', 3000), Bot).serve_forever()
```

**Node.js:**

```javascript
const http = require('http');
http.createServer((req, res) => {
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    const data = JSON.parse(body);
    const actions = {};
    for (const cell of data.cells) {
      // Your logic here
      actions[cell.id] = { type: 'move', direction: ['up','down','left','right'][Math.random()*4|0] };
    }
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ actions }));
  });
}).listen(3000, () => console.log('Bot on :3000'));
```

**PHP:**

```php
<?php
$port = $argv[1] ?? 3000;
$server = stream_socket_server("tcp://0.0.0.0:$port");
echo "Bot on :$port\n";
while ($conn = stream_socket_accept($server, -1)) {
    $req = '';
    while (($line = fgets($conn)) !== false) { $req .= $line; if (trim($line) === '') break; }
    $method = strtok($req, ' ');
    $len = preg_match('/Content-Length:\s*(\d+)/i', $req, $m) ? (int)$m[1] : 0;
    $body = $len > 0 ? fread($conn, $len) : '';
    if ($method === 'OPTIONS') { $status = 204; $out = ''; }
    elseif ($method === 'POST') {
        $data = json_decode($body, true);
        $actions = [];
        foreach ($data['cells'] ?? [] as $cell) {
            // Your logic here
            $actions[(string)$cell['id']] = ['type' => 'move', 'direction' => ['up','down','left','right'][array_rand([0,1,2,3])]];
        }
        $status = 200; $out = json_encode(['actions' => (object)$actions]);
    } else { $status = 405; $out = ''; }
    fwrite($conn, "HTTP/1.1 $status OK\r\nAccess-Control-Allow-Origin: *\r\nAccess-Control-Allow-Methods: POST, OPTIONS\r\nAccess-Control-Allow-Headers: Content-Type\r\nContent-Type: application/json\r\nContent-Length: " . strlen($out) . "\r\nConnection: close\r\n\r\n" . $out);
    fclose($conn);
}
```

## Game Constants

| Constant           | Value                 |
|--------------------|-----------------------|
| Grid size          | 100 x 100             |
| Starting energy    | 200                   |
| Food energy        | +100                  |
| Passive drain      | -1 per tick           |
| Clone              | 50/50 energy split    |
| Failed attack      | no cost               |
| Vision             | 15 x 15 (radius 7)    |
| Food spawn         | Every 2 ticks         |
| Initial food       | 50                    |
| Endpoint timeout   | 1 second              |
