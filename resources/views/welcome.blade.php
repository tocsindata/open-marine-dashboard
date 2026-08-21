<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark light">
    <title>{{ config('app.name', 'Tocsin Marine') }}</title>
    <style>
        :root {
            color-scheme: dark;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --ocean: #071d2b;
            --surface: #0d2a3a;
            --line: #2f6178;
            --text: #eef8fb;
            --muted: #b7d2dd;
            --accent: #67d4e8;
            --warning: #ffd28a;
        }
        * { box-sizing: border-box; }
        body {
            min-height: 100vh;
            margin: 0;
            color: var(--text);
            background: linear-gradient(160deg, #04131d 0%, var(--ocean) 60%, #0a3445 100%);
        }
        main {
            width: min(70rem, calc(100% - 2rem));
            margin: 0 auto;
            padding: clamp(3rem, 8vw, 7rem) 0;
        }
        .eyebrow { color: var(--accent); font-weight: 700; letter-spacing: .12em; text-transform: uppercase; }
        h1 { max-width: 14ch; margin: .5rem 0 1rem; font-size: clamp(2.5rem, 8vw, 5.5rem); line-height: .98; }
        .lede { max-width: 45rem; color: var(--muted); font-size: clamp(1.1rem, 2vw, 1.35rem); line-height: 1.65; }
        .status {
            display: inline-flex;
            gap: .6rem;
            align-items: center;
            margin: 2rem 0;
            padding: .7rem 1rem;
            border: 1px solid var(--line);
            border-radius: 999px;
            background: color-mix(in srgb, var(--surface) 85%, transparent);
        }
        .dot { width: .75rem; height: .75rem; border-radius: 50%; background: var(--warning); }
        .notice {
            max-width: 52rem;
            margin-top: 3rem;
            padding: 1rem 1.25rem;
            border-left: .25rem solid var(--warning);
            background: color-mix(in srgb, var(--surface) 88%, transparent);
            color: var(--muted);
            line-height: 1.55;
        }
        strong { color: var(--text); }
    </style>
</head>
<body>
<main>
    <p class="eyebrow">West Coast Canada</p>
    <h1>Marine operations, organized.</h1>
    <p class="lede">A private dashboard for vessels, maintenance, tasks, budgets, reminders, voyages, and carefully sourced marine conditions.</p>
    <p class="status"><span class="dot" aria-hidden="true"></span> Foundation under active development</p>
    <aside class="notice" aria-label="Safety notice">
        <strong>Safety notice:</strong> This dashboard is supplementary. It does not replace official nautical charts, Notices to Mariners, forecasts, emergency alerts, or prudent seamanship.
    </aside>
</main>
</body>
</html>
