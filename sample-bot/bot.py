#!/usr/bin/env python3
"""Sample bot for dev-cells. Uses only Python stdlib."""

import json
import random
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler

DIRECTIONS = ["up", "down", "left", "right"]

DIRECTION_DELTA = {
    "up": (0, -1),
    "down": (0, 1),
    "left": (-1, 0),
    "right": (1, 0),
}


def manhattan(x1, y1, x2, y2):
    return abs(x1 - x2) + abs(y1 - y2)


def direction_toward(cx, cy, tx, ty):
    dx = tx - cx
    dy = ty - cy
    if abs(dx) >= abs(dy):
        return "right" if dx > 0 else "left"
    return "down" if dy > 0 else "up"


def decide(cell):
    """Return an action dict for a single cell."""
    x = cell["x"]
    y = cell["y"]
    energy = cell["energy"]
    vision = cell.get("vision", {})
    food_list = vision.get("food", [])

    nearest_food = None
    best_dist = float("inf")
    for f in food_list:
        d = manhattan(x, y, f["x"], f["y"])
        if d < best_dist:
            best_dist = d
            nearest_food = f

    if energy > 30:
        if nearest_food:
            d = direction_toward(x, y, nearest_food["x"], nearest_food["y"])
        else:
            d = random.choice(DIRECTIONS)
        return {"type": "clone", "direction": d}

    if nearest_food:
        d = direction_toward(x, y, nearest_food["x"], nearest_food["y"])
        return {"type": "move", "direction": d}

    return {"type": "move", "direction": random.choice(DIRECTIONS)}


CORS_HEADERS = {
    "Access-Control-Allow-Origin": "*",
    "Access-Control-Allow-Methods": "POST, OPTIONS",
    "Access-Control-Allow-Headers": "Content-Type",
}


class BotHandler(BaseHTTPRequestHandler):
    def log_message(self, format, *args):
        pass  # suppress request logging

    def _set_cors(self):
        for k, v in CORS_HEADERS.items():
            self.send_header(k, v)

    def do_OPTIONS(self):
        self.send_response(204)
        self._set_cors()
        self.end_headers()

    def do_POST(self):
        length = int(self.headers.get("Content-Length", 0))
        body = self.rfile.read(length)
        try:
            data = json.loads(body)
        except json.JSONDecodeError:
            self.send_response(400)
            self._set_cors()
            self.send_header("Content-Type", "application/json")
            self.end_headers()
            self.wfile.write(json.dumps({"error": "invalid json"}).encode())
            return

        actions = {}
        for cell in data.get("cells", []):
            actions[str(cell["id"])] = decide(cell)

        response = json.dumps({"actions": actions})
        self.send_response(200)
        self._set_cors()
        self.send_header("Content-Type", "application/json")
        self.end_headers()
        self.wfile.write(response.encode())


def main():
    port = int(sys.argv[1]) if len(sys.argv) > 1 else 3000
    server = HTTPServer(("", port), BotHandler)
    print(f"Bot running on http://localhost:{port}")
    server.serve_forever()


if __name__ == "__main__":
    main()
