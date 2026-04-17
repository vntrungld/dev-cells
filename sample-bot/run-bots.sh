#!/bin/bash
# Start 20 bot instances on ports 3000-3019
PIDS=()
for i in $(seq 0 19); do
  PORT=$((3000 + i))
  python3 "$(dirname "$0")/bot.py" "$PORT" &
  PIDS+=($!)
done

echo "Started 20 bots on ports 3000-3019"
echo "Press Ctrl+C to stop all bots"

trap 'kill "${PIDS[@]}" 2>/dev/null; exit' INT TERM
wait
