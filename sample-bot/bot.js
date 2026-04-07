#!/usr/bin/env node
/**
 * Sample bot for dev-cells. Uses only Node.js built-ins.
 * Usage: node bot.js [port]
 */

const http = require('http');

const DIRECTIONS = ['up', 'down', 'left', 'right'];

function manhattan(x1, y1, x2, y2) {
  return Math.abs(x1 - x2) + Math.abs(y1 - y2);
}

function directionToward(cx, cy, tx, ty) {
  const dx = tx - cx;
  const dy = ty - cy;
  if (Math.abs(dx) >= Math.abs(dy)) {
    return dx > 0 ? 'right' : 'left';
  }
  return dy > 0 ? 'down' : 'up';
}

function decide(cell) {
  const { x, y, energy } = cell;
  const vision = cell.vision || {};
  const food = vision.food || [];

  let nearest = null;
  let bestDist = Infinity;
  for (const f of food) {
    const d = manhattan(x, y, f.x, f.y);
    if (d < bestDist) {
      bestDist = d;
      nearest = f;
    }
  }

  if (energy > 30) {
    const dir = nearest
      ? directionToward(x, y, nearest.x, nearest.y)
      : DIRECTIONS[Math.floor(Math.random() * 4)];
    return { type: 'clone', direction: dir };
  }

  if (nearest) {
    return { type: 'move', direction: directionToward(x, y, nearest.x, nearest.y) };
  }

  return { type: 'move', direction: DIRECTIONS[Math.floor(Math.random() * 4)] };
}

const port = parseInt(process.argv[2], 10) || 3000;

const server = http.createServer((req, res) => {
  const cors = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type',
  };

  if (req.method === 'OPTIONS') {
    res.writeHead(204, cors);
    res.end();
    return;
  }

  if (req.method === 'POST') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      let data;
      try {
        data = JSON.parse(body);
      } catch {
        res.writeHead(400, { ...cors, 'Content-Type': 'application/json' });
        res.end(JSON.stringify({ error: 'invalid json' }));
        return;
      }

      const actions = {};
      for (const cell of data.cells || []) {
        actions[String(cell.id)] = decide(cell);
      }

      res.writeHead(200, { ...cors, 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ actions }));
    });
    return;
  }

  res.writeHead(405, cors);
  res.end();
});

server.listen(port, () => {
  console.log(`Bot running on http://localhost:${port}`);
});
