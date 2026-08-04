<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Laravel') }} | Accounting dashboard</title>
        <meta
            name="description"
            content="Streamline invoicing, expenses, teams, and cash flow from one accounting workspace."
        >

        @fonts

        <style>
            :root {
                color-scheme: dark;
                --bg: #020617;
                --panel: rgba(15, 23, 42, 0.82);
                --panel-soft: rgba(255, 255, 255, 0.06);
                --border: rgba(255, 255, 255, 0.1);
                --text: #e2e8f0;
                --muted: #94a3b8;
                --accent: #fbbf24;
                --accent-strong: #f59e0b;
                --emerald: #34d399;
                --sky: #38bdf8;
            }

            * {
                box-sizing: border-box;
            }

            html,
            body {
                margin: 0;
                min-height: 100%;
                background: var(--bg);
                color: var(--text);
                font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            }

            body {
                min-height: 100vh;
            }

            a {
                color: inherit;
                text-decoration: none;
            }

            .shell {
                min-height: 100vh;
                background:
                    radial-gradient(circle at top left, rgba(245, 158, 11, 0.2), transparent 28%),
                    radial-gradient(circle at top right, rgba(56, 189, 248, 0.16), transparent 24%),
                    linear-gradient(180deg, #020617 0%, #0f172a 45%, #111827 100%);
            }

            .container {
                width: min(1200px, calc(100% - 48px));
                margin: 0 auto;
            }

            .header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 24px;
                padding: 28px 0;
            }

            .eyebrow {
                margin: 0;
                color: rgba(251, 191, 36, 0.85);
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.34em;
                text-transform: uppercase;
            }

            .brand {
                margin: 6px 0 0;
                font-size: 1.2rem;
                font-weight: 700;
            }

            .button {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 46px;
                padding: 0 20px;
                border-radius: 999px;
                border: 1px solid var(--border);
                background: rgba(255, 255, 255, 0.05);
                color: var(--text);
                font-size: 0.95rem;
                font-weight: 700;
                transition: transform 0.2s ease, background 0.2s ease, border-color 0.2s ease;
            }

            .button:hover {
                transform: translateY(-1px);
                background: rgba(255, 255, 255, 0.08);
                border-color: rgba(255, 255, 255, 0.18);
            }

            .button--accent {
                border-color: rgba(251, 191, 36, 0.35);
                background: linear-gradient(135deg, var(--accent) 0%, #fcd34d 100%);
                color: #0f172a;
            }

            .hero {
                display: grid;
                grid-template-columns: minmax(0, 1.1fr) minmax(320px, 0.9fr);
                gap: 48px;
                align-items: start;
                padding: 28px 0 56px;
            }

            .pill {
                display: inline-flex;
                align-items: center;
                padding: 8px 14px;
                border-radius: 999px;
                border: 1px solid rgba(52, 211, 153, 0.2);
                background: rgba(52, 211, 153, 0.08);
                color: #bbf7d0;
                font-size: 0.92rem;
                font-weight: 600;
            }

            h1 {
                margin: 18px 0 0;
                font-size: clamp(2.6rem, 5vw, 4.8rem);
                line-height: 1.02;
                letter-spacing: -0.04em;
            }

            .lead {
                margin: 20px 0 0;
                max-width: 60ch;
                color: var(--muted);
                font-size: 1.08rem;
                line-height: 1.8;
            }

            .actions {
                display: flex;
                flex-wrap: wrap;
                gap: 14px;
                margin-top: 30px;
            }

            .stats {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 14px;
                margin-top: 34px;
            }

            .card {
                border: 1px solid var(--border);
                background: var(--panel-soft);
                border-radius: 24px;
                backdrop-filter: blur(14px);
            }

            .stat {
                padding: 18px;
            }

            .stat h2 {
                margin: 0;
                font-size: 1.6rem;
                line-height: 1.1;
            }

            .stat p {
                margin: 8px 0 0;
                color: var(--muted);
                font-size: 0.94rem;
                line-height: 1.6;
            }

            .panel {
                overflow: hidden;
                background: var(--panel);
                box-shadow: 0 24px 80px rgba(0, 0, 0, 0.35);
            }

            .panel__header {
                padding: 18px 22px;
                border-bottom: 1px solid var(--border);
                background: rgba(255, 255, 255, 0.04);
            }

            .panel__header p {
                margin: 0;
                color: #cbd5e1;
                font-size: 0.78rem;
                font-weight: 700;
                letter-spacing: 0.28em;
                text-transform: uppercase;
            }

            .panel__body {
                display: grid;
                gap: 14px;
                padding: 22px;
            }

            .metric {
                padding: 18px;
                border-radius: 20px;
                border: 1px solid rgba(52, 211, 153, 0.18);
                background: rgba(52, 211, 153, 0.08);
            }

            .metric__label {
                margin: 0;
                color: #bbf7d0;
                font-size: 0.92rem;
            }

            .metric__row {
                display: flex;
                align-items: end;
                justify-content: space-between;
                gap: 16px;
                margin-top: 8px;
            }

            .metric__value {
                margin: 0;
                font-size: 2.35rem;
                font-weight: 800;
                letter-spacing: -0.04em;
            }

            .badge {
                display: inline-flex;
                align-items: center;
                padding: 6px 10px;
                border-radius: 999px;
                background: rgba(52, 211, 153, 0.18);
                color: #d1fae5;
                font-size: 0.76rem;
                font-weight: 700;
            }

            .mini-grid {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 14px;
            }

            .mini {
                padding: 16px;
                background: rgba(255, 255, 255, 0.04);
            }

            .mini__label {
                margin: 0;
                color: var(--muted);
                font-size: 0.88rem;
            }

            .mini__value {
                margin: 10px 0 0;
                font-size: 1.9rem;
                font-weight: 700;
            }

            .activity {
                padding: 16px;
                background: rgba(255, 255, 255, 0.04);
            }

            .activity h3,
            .feature h3 {
                margin: 0;
                font-size: 1rem;
            }

            .activity-list {
                display: grid;
                gap: 12px;
                margin-top: 14px;
            }

            .activity-row {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                font-size: 0.92rem;
            }

            .activity-row span:last-child {
                color: #64748b;
            }

            .section {
                padding: 0 0 64px;
            }

            .feature-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 18px;
            }

            .feature {
                padding: 24px;
            }

            .feature .tag {
                margin: 0;
                font-size: 0.78rem;
                font-weight: 800;
                letter-spacing: 0.28em;
                text-transform: uppercase;
            }

            .tag--amber {
                color: #fcd34d;
            }

            .tag--sky {
                color: #7dd3fc;
            }

            .tag--emerald {
                color: #6ee7b7;
            }

            .feature p:last-child {
                margin: 12px 0 0;
                color: var(--muted);
                line-height: 1.75;
            }

            @media (max-width: 960px) {
                .hero,
                .feature-grid {
                    grid-template-columns: 1fr;
                }

                .stats,
                .mini-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media (max-width: 640px) {
                .container {
                    width: min(100% - 32px, 1200px);
                }

                .header {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .actions {
                    flex-direction: column;
                }

                .button {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
        <div class="shell">
            <header class="container header">
                <div>
                    <p class="eyebrow">Finance OS</p>
                    <p class="brand">{{ config('app.name', 'Laravel Accounting') }}</p>
                </div>
                <a class="button button--accent" href="{{ route('login') }}">Open dashboard</a>
            </header>

            <main class="container hero">
                <section>
                    <span class="pill">Modern accounting for teams that move fast</span>
                    <h1>Manage invoices, vendors, and cash flow from one clean workspace.</h1>
                    <p class="lead">
                        Track customers, bills, teams, and reporting in a single Filament-powered dashboard built for
                        day-to-day finance operations.
                    </p>

                    <div class="actions">
                        <a class="button button--accent" href="{{ route('login') }}">Sign in</a>
                        <a class="button" href="#features">Explore features</a>
                    </div>

                    <div class="stats">
                        <article class="card stat">
                            <h2>Invoices</h2>
                            <p>Create and track billing without jumping between tools.</p>
                        </article>
                        <article class="card stat">
                            <h2>Teams</h2>
                            <p>Organize access and tenancy for every finance group.</p>
                        </article>
                        <article class="card stat">
                            <h2>Reports</h2>
                            <p>See what matters with fast, focused operational views.</p>
                        </article>
                    </div>
                </section>

                <aside class="card panel">
                    <div class="panel__header">
                        <p>Dashboard preview</p>
                    </div>
                    <div class="panel__body">
                        <div class="metric">
                            <p class="metric__label">This month</p>
                            <div class="metric__row">
                                <p class="metric__value">$128,450</p>
                                <span class="badge">+18%</span>
                            </div>
                        </div>

                        <div class="mini-grid">
                            <div class="card mini">
                                <p class="mini__label">Open invoices</p>
                                <p class="mini__value">24</p>
                            </div>
                            <div class="card mini">
                                <p class="mini__label">Pending bills</p>
                                <p class="mini__value">11</p>
                            </div>
                        </div>

                        <div class="card activity">
                            <h3>Activity</h3>
                            <div class="activity-list">
                                <div class="activity-row">
                                    <span>Invoice #1042 sent</span>
                                    <span>2m ago</span>
                                </div>
                                <div class="activity-row">
                                    <span>Vendor bill approved</span>
                                    <span>18m ago</span>
                                </div>
                                <div class="activity-row">
                                    <span>Team member invited</span>
                                    <span>1h ago</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </main>

            <section id="features" class="container section">
                <div class="feature-grid">
                    <article class="card feature">
                        <p class="tag tag--amber">Billing</p>
                        <h3>Invoice workflows that stay simple</h3>
                        <p>Create customer invoices, monitor balances, and keep collections visible at a glance.</p>
                    </article>
                    <article class="card feature">
                        <p class="tag tag--sky">Operations</p>
                        <h3>Vendor and bill tracking in one place</h3>
                        <p>Review expenses, approve bills, and manage obligations without losing context.</p>
                    </article>
                    <article class="card feature">
                        <p class="tag tag--emerald">Access</p>
                        <h3>Built for team-based tenancy</h3>
                        <p>Work across teams with role-aware access and a clean admin experience.</p>
                    </article>
                </div>
            </section>
        </div>
    </body>
</html>
