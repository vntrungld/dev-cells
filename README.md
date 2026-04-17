# Dev Cells

A competitive programming game: write an HTTP bot, control cells on a grid, outlast everyone else.

```
  Your Code  ──HTTP POST──>  Game Engine  ──renders──>  Browser UI
  (bot.py)                   (index.html)               (100x100 grid)
```

No server, no dependencies — the entire game runs in a single `index.html` opened in a browser.

---

## Quick Start

**1. Start a bot** (pick your language):

```bash
python sample-bot/bot.py 3000        # Python
node sample-bot/bot.js 3000          # Node.js
php sample-bot/bot.php 3000          # PHP
```

**2. Open `index.html`** in a browser.

**3. Add your bot** — enter a name and `http://localhost:3000`, click **Add Player**.

**4. Click Start** and watch your cells compete!

> **Want opponents?** Click **"Seed 20 Bots"**, then run `bash sample-bot/run-bots.sh` to start 20 bots on ports 3000-3019.

---

## Game Rules

### The Basics

- Each player starts with **1 cell** on the 100x100 grid with **200 energy**
- Every tick, the game POSTs your cells' state to your bot
- Your bot responds with an action for each cell
- Cells lose **1 energy per tick** — reach 0 and they die
- Lose all your cells and you're **eliminated**

### Actions

```
             up
              ^
              |
    left  <---+--->  right        4 directions for move/clone/merge
              |
              v
            down
```

| Action  | What happens |
|---------|-------------|
| `move`  | Move 1 tile. Eat food (+100 energy) if present. Triggers combat if enemy is there. |
| `clone` | Split into 2 cells, energy divided 50/50. Target tile must be empty. Min energy: 2. |
| `merge` | Move into a friendly cell. That cell gains all your energy. You are removed. |
| `idle`  | Do nothing. Also the default if your bot doesn't respond in time. |

### Combat

Combat is **probabilistic** — your win chance scales with your energy share:

```
                       your energy
  Win chance  =  ─────────────────────────
                 your energy + their energy
```

Winner **absorbs the loser's entire energy**. Loser is removed from the grid.

```
  You: 300 energy  vs  Enemy: 100 energy

  ┌──────────────────────────────────────────────────┐
  │████████████████████████████████████░░░░░░░░░░░░░░│
  │◄──────── 75% you win ────────►◄── 25% they win ─►│
  └──────────────────────────────────────────────────┘
```

| Your energy | Their energy | Your win % | Their win % |
|:-----------:|:------------:|:----------:|:-----------:|
| 200         | 200          | 50%        | 50%         |
| 300         | 100          | 75%        | 25%         |
| 900         | 100          | 90%        | 10%         |
| 50          | 450          | 10%        | 90%         |

**Three ways combat triggers:**

**1) Move into an enemy** — You move onto their tile. Probabilistic. If you win, you take their spot. If they win, you're removed.

**2) Tile collision** — Multiple enemy cells move to the same tile on the same tick. **Deterministic**: highest energy wins and absorbs all others. Equal energy at the top = nobody moves.

**3) Adjacent combat (automatic)** — After all moves, every cell scans its **8 neighbors** (including diagonals). Enemy adjacent? Fight happens **automatically**. Each cell fights at most once per tick.

> **Being near enemies is always dangerous** — you don't need to move toward them for adjacent combat to trigger.

### Food & Energy

| Source/Drain     | Amount |
|------------------|--------|
| Starting energy  | 200    |
| Eat food         | +100   |
| Win combat       | +loser's full energy |
| Passive drain    | -1 per tick |
| Clone            | energy split 50/50 |
| Energy hits 0    | cell dies |

- **50 food** on the grid at game start
- **1 new food** spawns every **2 ticks** on a random empty tile

### Vision

Each cell sees a diamond-shaped area — all tiles within **Manhattan distance 7** (`|dx| + |dy| <= 7`):

```
            . . . * . . .
          . . . * * * . . .
        . . . * * * * * . . .
      . . * * * * * * * * * . .
    . . * * * * * * * * * * * . .
  . . * * * * * * * * * * * * * . .
  . * * * * * * * * * * * * * * * .
  * * * * * * * [C] * * * * * * * *   <-- your cell
  . * * * * * * * * * * * * * * * .
  . . * * * * * * * * * * * * * . .
    . . * * * * * * * * * * * . .
      . . * * * * * * * * * . .
        . . . * * * * * . . .
          . . . * * * . . .
            . . . * . . .
```

You can see: enemy cells (position, energy, owner name), friendly cells (`owner: "self"`), and food positions.

### Winning

| Condition | Result |
|-----------|--------|
| One player left alive | That player wins immediately |
| Time limit reached (default: 1000 ticks) | Highest **total energy** across all cells wins |
| All players die on the same tick | Draw |

---

## Writing Your Bot

Your bot is an HTTP server that receives a POST each tick and responds with actions.

### Request (game sends to your bot)

```json
{
  "tick": 42,
  "cells": [
    {
      "id": 1,
      "x": 10, "y": 20,
      "energy": 150,
      "vision": {
        "cells": [
          { "x": 12, "y": 20, "energy": 80, "owner": "EnemyBot" },
          { "x": 9, "y": 20, "energy": 120, "owner": "self" }
        ],
        "food": [
          { "x": 11, "y": 21 }
        ]
      }
    }
  ]
}
```

### Response (your bot sends back)

```json
{
  "actions": {
    "1": { "type": "move", "direction": "right" }
  }
}
```

Keys are cell IDs as strings. Valid types: `move`, `clone`, `merge`, `idle`. Valid directions: `up`, `down`, `left`, `right`.

**Timeout: 1 second.** If your bot doesn't respond in time, all cells idle.

**CORS required** — your bot must handle preflight `OPTIONS` requests and return:
```
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: POST, OPTIONS
Access-Control-Allow-Headers: Content-Type
```

### Minimal Bot Templates

<details>
<summary><strong>Python</strong></summary>

```python
from http.server import HTTPServer, BaseHTTPRequestHandler
import json, random

class Bot(BaseHTTPRequestHandler):
    def do_OPTIONS(self):
        self.send_response(204)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Access-Control-Allow-Methods', 'POST, OPTIONS')
        self.send_header('Access-Control-Allow-Headers', 'Content-Type')
        self.end_headers()

    def do_POST(self):
        data = json.loads(self.rfile.read(int(self.headers['Content-Length'])))
        actions = {}
        for cell in data['cells']:
            # Your logic here
            actions[str(cell['id'])] = {
                'type': 'move',
                'direction': random.choice(['up', 'down', 'left', 'right'])
            }
        self.send_response(200)
        self.send_header('Access-Control-Allow-Origin', '*')
        self.send_header('Content-Type', 'application/json')
        self.end_headers()
        self.wfile.write(json.dumps({'actions': actions}).encode())

    def log_message(self, *a): pass

HTTPServer(('', 3000), Bot).serve_forever()
```

</details>

<details>
<summary><strong>Node.js</strong></summary>

```javascript
const http = require('http');
http.createServer((req, res) => {
  const cors = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
  };
  if (req.method === 'OPTIONS') {
    res.writeHead(204, cors);
    return res.end();
  }
  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => {
    const data = JSON.parse(body);
    const actions = {};
    for (const cell of data.cells) {
      // Your logic here
      actions[cell.id] = {
        type: 'move',
        direction: ['up', 'down', 'left', 'right'][Math.random() * 4 | 0]
      };
    }
    res.writeHead(200, { ...cors, 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ actions }));
  });
}).listen(3000, () => console.log('Bot on :3000'));
```

</details>

<details>
<summary><strong>PHP</strong></summary>

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
            $dirs = ['up', 'down', 'left', 'right'];
            $actions[(string)$cell['id']] = ['type' => 'move', 'direction' => $dirs[array_rand($dirs)]];
        }
        $status = 200; $out = json_encode(['actions' => (object)$actions]);
    } else { $status = 405; $out = ''; }
    $headers = "HTTP/1.1 $status OK\r\n"
        . "Access-Control-Allow-Origin: *\r\n"
        . "Access-Control-Allow-Methods: POST, OPTIONS\r\n"
        . "Access-Control-Allow-Headers: Content-Type\r\n"
        . "Content-Type: application/json\r\n"
        . "Content-Length: " . strlen($out) . "\r\n"
        . "Connection: close\r\n\r\n";
    fwrite($conn, $headers . $out);
    fclose($conn);
}
```

</details>

---

## Strategy Tips

- **Clone early** — more cells = more food collected = faster growth
- **Pick your fights** — 300 vs 100 = 75% win; 100 vs 300 = 25% win. Only attack with an energy advantage.
- **Watch your flanks** — adjacent combat is automatic. Don't end your turn next to an enemy you can't beat.
- **Merge to consolidate** — combine weak cells into one strong cell before engaging enemies
- **Don't over-clone** — 10 cells with 5 energy each all die in 5 ticks without food
- **Spread to scout** — each cell only sees radius 7, cover more ground to find food

---

## Game Constants

| Constant           | Value                         |
|--------------------|-------------------------------|
| Grid size          | 100 x 100                    |
| Starting energy    | 200                          |
| Food energy        | +100                         |
| Passive drain      | -1 per tick                  |
| Clone min energy   | 2                            |
| Clone split        | 50/50                        |
| Vision radius      | 7 (Manhattan distance)       |
| Food spawn         | 1 food every 2 ticks         |
| Initial food       | 50                           |
| Endpoint timeout   | 1 second                     |
| Default time limit | 1000 ticks                   |
