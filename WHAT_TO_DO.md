## What to do to play this game
You need to write a bot for this game

### 1. Check out sample bots in `./sample-bot`

### 2. Start a Bot

```bash
# Python
python sample-bot/bot.py 3000

# Node.js
node sample-bot/bot.js 3000

# PHP
php sample-bot/bot.php 3000
```

### 3. Writing Your Own Bot

Pick your language and write your own bot:

Your bot just needs to be an HTTP server that accepts POST requests. The game sends your cells' positions/energy/vision each tick, and you respond with actions (move, clone, or idle).

The minimal templates in the README are the quickest starting point — copy one and replace the `// Your logic here` line with your strategy.

One thing to note: your bot must handle CORS headers since the browser makes cross-origin requests. The sample bots already do this.

### 4. Example strategies to implement

Here are some strategies to get you thinking:

**Greedy Forager** — Chase the nearest food, clone when energy is high.

**Hunter** — Accumulate energy by eating food first, then hunt down enemy cells when you're strong enough to win. Flee from enemies you can't beat.

**Lucky Rock** — Just standing there, wait for magic to happen ...

