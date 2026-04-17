# Single Bot Server Design

**Date:** 2026-04-17

## Goal

Replace 20 separate bot processes with a single server using path-based routing.

## Changes

1. **`sample-bot/bot.py`** — Route on request path `/bot/{0-19}`, single port
2. **`index.html`** — Seed button registers `http://localhost:3000/bot/0` through `/bot/19`
3. **Delete `sample-bot/run-bots.sh`** — No longer needed

## Design

- Single Python process, one port (default 3000)
- All 20 bots share the same `decide` strategy
- Path format: `POST /bot/<id>` where id is 0-19
- CORS handling unchanged
