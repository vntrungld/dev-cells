# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Dev Cells is a competitive 2D grid game where developers write HTTP bot endpoints that control cells on a 100x100 grid. The entire game engine and UI runs in a single `index.html` file opened in a browser — no build tools, no server, no dependencies.

## Architecture

**Single-file browser app** (`index.html`): Contains all HTML, CSS, and JavaScript inline. The game loop runs in the browser, making HTTP POST requests directly to each player's bot endpoint every tick.

Key sections within `index.html`:
- **Game constants** (line ~307): `GRID_SIZE`, `STARTING_ENERGY`, `FOOD_ENERGY`, `CLONE_COST`, etc.
- **Grid/cell functions** (~341): `initGrid`, `spawnCell`, `removeCell`, `getVision`, `getAdjacentCells`
- **Canvas rendering** (~437): `resizeCanvas`, `render`, `renderScoreboard`
- **Tick engine** (~522): `buildRequest`, `callEndpoint`, `resolveActions`, `tick`
- **Game lifecycle** (~774): `startGame`, `pauseGame`, `resetGame`
- **Lobby/UI logic** (~851): player management, event handlers

**Action resolution** (`resolveActions`, ~558) is the most complex part — a two-pass algorithm:
1. Pass 1: Compute intended positions, resolve clones, then determine move outcomes (collisions, combat) without mutating the grid
2. Pass 2: Apply all outcomes (grid mutations) at once

Combat requires **numerical superiority** — count adjacent allied cells vs adjacent defender cells. No solo kills.

**Sample bots** (`sample-bot/`): Reference implementations in Python, Node.js, and PHP. All use only stdlib, handle CORS, and implement the same strategy (clone when energy > 30, otherwise chase nearest food).

## Running

```bash
# Open the game UI
open index.html  # or xdg-open on Linux

# Start a sample bot
python sample-bot/bot.py 3000
node sample-bot/bot.js 3000
php sample-bot/bot.php 3000

# Start 20 bots for testing (ports 3000-3019)
bash sample-bot/run-bots.sh
```

In the UI: add players (or click "Seed 20 Bots"), then click Start.

## Bot Endpoint Contract

**Request** (POST): `{ tick, cells: [{ id, x, y, energy, vision: { cells, food } }] }`
**Response**: `{ actions: { "<cellId>": { type: "move"|"clone"|"idle", direction: "up"|"down"|"left"|"right" } } }`

Bot has 1 second to respond or all cells idle. Vision radius is 7 (Manhattan distance).

## Game Constants

| Constant         | Value              |
|------------------|--------------------|
| Grid             | 100x100            |
| Starting energy  | 200                |
| Food energy      | +100               |
| Passive drain    | -1/tick            |
| Clone            | 50/50 energy split |
| Merge            | combines energy    |
| Combat           | adjacent (incl. diagonal) triggers fight; win chance = your energy / (your energy + enemy energy) |
| Vision radius    | 7                  |
| Food spawn       | every 2 ticks      |
| Initial food     | 50                 |
| Endpoint timeout | 1s                 |

## Development Notes

- No build step, linting, or tests — it's a single HTML file
- Bot endpoints must handle CORS (the browser makes cross-origin fetch calls)
- Design doc lives in `docs/plans/2026-04-07-dev-cells-design.md` (some values differ from implementation — implementation is authoritative)
