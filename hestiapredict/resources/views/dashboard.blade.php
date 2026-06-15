<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kamoro Hotel - Tableau de bord</title>
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: light;
            --sand-50: #fbf4ea;
            --sand-100: #f4eadc;
            --sand-200: #eadbc8;
            --ink: #231f1b;
            --muted: #75695e;
            --accent: #1f7665;
            --accent-soft: rgba(31, 118, 101, 0.12);
            --line: rgba(68, 52, 39, 0.10);
            --panel: rgba(251, 244, 234, 0.88);
            --panel-strong: rgba(247, 239, 227, 0.96);
        }

        body {
            font-family: "Manrope", sans-serif;
            letter-spacing: 0;
            background:
                radial-gradient(circle at top left, rgba(255, 255, 255, 0.9), transparent 34%),
                radial-gradient(circle at top right, rgba(226, 209, 184, 0.42), transparent 28%),
                linear-gradient(180deg, #f8f0e6 0%, #f5ecdf 48%, #f7f0e8 100%);
            color: var(--ink);
        }

        .display-serif {
            font-family: "Cormorant Garamond", serif;
        }

        .sand-panel {
            background: var(--panel);
            border: 1px solid var(--line);
            box-shadow: 0 18px 44px rgba(78, 62, 48, 0.08);
            backdrop-filter: blur(12px);
        }

        .sand-panel-strong {
            background: var(--panel-strong);
            border: 1px solid rgba(68, 52, 39, 0.12);
            box-shadow: 0 20px 50px rgba(78, 62, 48, 0.1);
        }

        .bento-card {
            border-radius: 28px;
            background: var(--sand-50);
            border: 1px solid var(--line);
            box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55), 0 20px 46px rgba(78, 62, 48, 0.08);
        }

        .kpi-value {
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(2.4rem, 3.6vw, 4rem);
            line-height: 0.95;
            font-weight: 600;
            letter-spacing: 0;
        }

        .pill-btn {
            min-height: 46px;
            border-radius: 999px;
            transition: 180ms ease;
        }

        .pill-btn.active {
            background: var(--ink);
            color: #f8f1e8;
            box-shadow: 0 12px 26px rgba(35, 31, 27, 0.16);
        }

        .floating-panel {
            pointer-events: none;
            opacity: 0;
            transform: translateY(12px);
            transition: opacity 180ms ease, transform 180ms ease;
        }

        .floating-panel.open {
            pointer-events: auto;
            opacity: 1;
            transform: translateY(0);
        }

        .table-shell {
            border: 1px solid var(--line);
            border-radius: 24px;
            overflow: hidden;
            background: rgba(255, 251, 247, 0.82);
        }

        .data-table th {
            background: rgba(239, 228, 212, 0.8);
            color: var(--muted);
            font-size: 0.72rem;
            font-weight: 800;
            letter-spacing: 0;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .data-table td {
            color: #3e352e;
            vertical-align: middle;
        }

        .data-table tbody tr:hover {
            background: rgba(255, 255, 255, 0.58);
        }

        .icon {
            width: 18px;
            height: 18px;
            stroke-width: 2;
        }

        .tab-content {
            animation: sectionFade 220ms ease;
        }

        @keyframes sectionFade {
            from {
                opacity: 0;
                transform: translateY(8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 760px) {
            .mobile-card-table table,
            .mobile-card-table thead,
            .mobile-card-table tbody,
            .mobile-card-table th,
            .mobile-card-table td,
            .mobile-card-table tr {
                display: block;
            }

            .mobile-card-table thead {
                display: none;
            }

            .mobile-card-table tr {
                border: 1px solid var(--line);
                border-radius: 24px;
                margin: 0 0 12px;
                background: rgba(255, 251, 247, 0.9);
                overflow: hidden;
            }

            .mobile-card-table td {
                display: flex;
                justify-content: space-between;
                gap: 16px;
                padding: 12px 16px;
                border-bottom: 1px solid rgba(68, 52, 39, 0.08);
            }

            .mobile-card-table td:last-child {
                border-bottom: 0;
            }

            .mobile-card-table td::before {
                content: attr(data-label);
                color: var(--muted);
                font-size: 0.75rem;
                font-weight: 700;
                text-transform: uppercase;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <div class="min-h-screen">
        <main class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <section class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                <article class="bento-card col-span-1 overflow-hidden p-6 sm:p-8 xl:col-span-8">
                    <div class="flex flex-col gap-8 xl:flex-row xl:items-start xl:justify-between">
                        <div class="max-w-3xl">
                            <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Analyse et management</p>
                            <h1 class="display-serif mt-3 text-5xl leading-none text-[var(--ink)] sm:text-6xl lg:text-7xl">Kamoro Hotel</h1>
                            <p class="mt-4 max-w-2xl text-sm leading-6 text-[var(--muted)] sm:text-base">
                                Vue éditoriale des revenus, de l'occupation et de la tarification dynamique. L'ensemble reste opérationnel, mais la lecture doit ressembler à un magazine de décoration haut de gamme.
                            </p>
                        </div>

                        <div class="flex flex-col gap-3 sm:flex-row xl:flex-col">
                            <a href="http://localhost:8080" target="_blank" class="pill-btn inline-flex items-center justify-center gap-2 bg-[var(--ink)] px-5 text-sm font-bold text-[#fbf4ea] transition hover:opacity-90">
                                <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M7 4h10a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M9 18h6"/></svg>
                                Réception
                            </a>
                            <div class="sand-panel rounded-full px-5 py-3" id="connection-status">
                                <span class="block text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Tarification dynamique</span>
                                <span class="mt-1 flex items-center gap-2 text-sm font-semibold text-emerald-700" id="connection-text-container">
                                    <span id="connection-dot" class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                                    <span id="connection-text">Active</span>
                                </span>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="bento-card col-span-1 p-6 xl:col-span-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Date de référence</p>
                    <div class="mt-4">
                        <input type="date" id="global-date" onchange="refreshAll()" class="h-12 w-full rounded-full border border-[rgba(68,52,39,0.12)] bg-white/70 px-4 text-sm font-semibold text-[var(--ink)] outline-none transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[rgba(31,118,101,0.12)]">
                    </div>
                    <p class="mt-4 text-sm leading-6 text-[var(--muted)]">Le tableau ajuste l'occupation, les réservations actives et les revenus sur la date sélectionnée.</p>
                </article>

                <article class="bento-card col-span-1 p-6 sm:p-7 xl:col-span-3">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA officiel</p>
                    <p class="kpi-value mt-4 text-[var(--ink)]" id="stat-ca-official">0 Ar</p>
                    <p class="mt-4 text-sm text-[var(--muted)]">Clients arrivés uniquement.</p>
                </article>

                <article class="bento-card col-span-1 p-6 sm:p-7 xl:col-span-3">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA en attente</p>
                    <p class="kpi-value mt-4 text-[var(--ink)]" id="stat-ca-pending">0 Ar</p>
                    <p class="mt-4 text-sm text-[var(--muted)]">Réservations non confirmées.</p>
                </article>

                <article class="bento-card col-span-1 p-6 sm:p-7 xl:col-span-3">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Occupation estimée</p>
                    <p class="kpi-value mt-4 text-[var(--ink)]"><span id="stat-rooms-estimated">0</span></p>
                    <p class="mt-4 text-sm text-[var(--muted)]"><span id="stat-rooms-confirmed">0</span> chambres confirmées.</p>
                </article>

                <article class="bento-card col-span-1 p-6 sm:p-7 xl:col-span-3">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA cumulé officiel</p>
                    <p class="kpi-value mt-4 text-[var(--ink)]" id="stat-ca-total">0 Ar</p>
                    <p class="mt-4 text-sm text-[var(--muted)]" id="ca-period">Période</p>
                </article>

                <aside class="col-span-1 space-y-4 xl:col-span-4">
                    <article class="bento-card p-6">
                        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">RevPAR</p>
                        <p class="display-serif mt-3 text-4xl font-semibold text-[var(--ink)]" id="finance-revpar">0 Ar</p>
                    </article>
                    <article class="bento-card p-6">
                        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Occupation réelle</p>
                        <p class="display-serif mt-3 text-4xl font-semibold text-[var(--ink)]" id="finance-occ-rate">0%</p>
                    </article>
                    <article class="bento-card p-6">
                        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Prix moyen</p>
                        <p class="display-serif mt-3 text-4xl font-semibold text-[var(--ink)]" id="finance-adr">0 Ar</p>
                    </article>
                </aside>

                <!-- Sélecteur d'onglets déplacé ici, après le Prix moyen -->
                <article class="bento-card col-span-1 p-4 sm:p-5 xl:col-span-8 xl:col-start-1">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <button onclick="switchTab('predictions')" id="btn-predictions" class="pill-btn active inline-flex items-center justify-center gap-2 px-4 text-sm font-bold">
                                <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m4 16 5-5 4 4 7-7"/><path stroke-linecap="round" stroke-linejoin="round" d="M14 8h6v6"/></svg>
                                Prévisions
                            </button>
                            <button onclick="switchTab('pricing')" id="btn-pricing" class="pill-btn inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-[var(--muted)] hover:bg-white/60 hover:text-[var(--ink)]">
                                <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7H4"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 12H4"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 17H4"/></svg>
                                Tarifs
                            </button>
                            <button onclick="switchTab('reservations')" id="btn-reservations" class="pill-btn inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-[var(--muted)] hover:bg-white/60 hover:text-[var(--ink)]">
                                <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 7V3"/><path stroke-linecap="round" stroke-linejoin="round" d="M4 11h16"/><path stroke-linecap="round" stroke-linejoin="round" d="M5 5h14a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Z"/></svg>
                                Réservations
                            </button>
                            <button onclick="switchTab('finance')" id="btn-finance" class="pill-btn inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-[var(--muted)] hover:bg-white/60 hover:text-[var(--ink)]">
                                <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 19h16"/><path stroke-linecap="round" stroke-linejoin="round" d="M7 16V9"/><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V5"/><path stroke-linecap="round" stroke-linejoin="round" d="M17 16v-4"/></svg>
                                Performance
                            </button>
                        </div>

                        <div class="relative self-start lg:self-auto">
                            <button type="button" id="settings-trigger" class="pill-btn sand-panel inline-flex items-center justify-center gap-2 px-4 text-sm font-bold text-[var(--ink)]">
                                <svg class="icon" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 6h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 6a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12h9"/><path stroke-linecap="round" stroke-linejoin="round" d="M17.5 12h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 12a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z"/><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 18h9"/><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 18h2"/><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 18a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Z"/></svg>
                                Réglages
                            </button>

                            <div id="settings-panel" class="floating-panel sand-panel-strong absolute bottom-[calc(100%+12px)] right-0 z-20 w-[320px] rounded-[28px] p-5">
                                <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Panneau flottant</p>
                                <div class="mt-4 space-y-4">
                                    <div>
                                        <label for="prediction-days" class="mb-2 block text-sm font-bold text-[var(--muted)]">Horizon de prévision</label>
                                        <select id="prediction-days" onchange="loadAILogic()" class="h-11 w-full rounded-full border border-[rgba(68,52,39,0.12)] bg-white/80 px-4 text-sm font-semibold text-[var(--ink)] outline-none transition focus:border-[var(--accent)] focus:ring-2 focus:ring-[rgba(31,118,101,0.12)]">
                                            <option value="1">Aujourd'hui</option>
                                            <option value="7">7 jours</option>
                                            <option value="15">15 jours</option>
                                            <option value="30" selected>30 jours</option>
                                            <option value="60">60 jours</option>
                                            <option value="90">90 jours</option>
                                            <option value="120">120 jours</option>
                                        </select>
                                    </div>
                                    <div class="sand-panel rounded-[24px] px-4 py-3">
                                        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA estimé par IA</p>
                                        <p class="display-serif mt-2 text-3xl font-semibold text-[var(--ink)]" id="stat-ca-ia">0 Ar</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>

                <article class="bento-card col-span-1 p-6 xl:col-span-4">
                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Narration financière</p>
                    <p class="display-serif mt-4 text-4xl font-semibold leading-none text-[var(--ink)]" id="finance-total-ai">0 Ar</p>
                    <p class="mt-4 text-sm leading-6 text-[var(--muted)]">Lecture synthétique du potentiel journalier issu de la tarification dynamique.</p>
                </article>

                <section id="tab-predictions" class="tab-content col-span-1 xl:col-span-8">
                    <article class="bento-card p-6 sm:p-7">
                        <div class="mb-6 flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
                            <div>
                                <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Prévision d'occupation</p>
                                <h2 class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]">Vagues d'occupation</h2>
                                <p class="mt-2 text-sm text-[var(--muted)]">Volume estimé par jour, toutes catégories confondues.</p>
                            </div>
                        </div>
                        <div class="h-[360px] w-full sm:h-[430px]">
                            <canvas id="predictionChart"></canvas>
                        </div>
                    </article>
                </section>

                <section id="tab-pricing" class="tab-content hidden col-span-1 xl:col-span-12">
                    <article class="bento-card p-6 sm:p-7">
                        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Tarifs par catégorie</p>
                        <h2 class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]">Édition tarifaire</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">Prix proposés pour la date sélectionnée.</p>
                        <div class="table-shell mobile-card-table mt-6">
                            <table class="data-table w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-5 py-3">Catégorie</th>
                                        <th class="px-5 py-3">Modèle</th>
                                        <th class="px-5 py-3">Prix fixe</th>
                                        <th class="px-5 py-3">Prix ajusté IA</th>
                                    </tr>
                                </thead>
                                <tbody id="pricing-table-body" class="divide-y divide-[rgba(68,52,39,0.08)]"></tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <section id="tab-reservations" class="tab-content hidden col-span-1 xl:col-span-12">
                    <article class="bento-card p-6 sm:p-7">
                        <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Réservations actives</p>
                        <h2 class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]">Salon des arrivées</h2>
                        <p class="mt-2 text-sm text-[var(--muted)]">Clients présents ou attendus à la date sélectionnée.</p>
                        <div class="table-shell mobile-card-table mt-6">
                            <table class="data-table w-full text-left text-sm">
                                <thead>
                                    <tr>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('reference')">Référence</th>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('client_name')">Client</th>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('contact')">Contact</th>
                                        <th class="px-5 py-3">N° chambres</th>
                                        <th class="px-5 py-3">Chambres</th>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('check_in')">Séjour</th>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('fixed_total_price')">Total fixe</th>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('paid_amount_ariary')">Total encaissé</th>
                                        <th class="px-5 py-3 cursor-pointer hover:text-[var(--accent)]" onclick="sortReservations('status')">Statut</th>
                                    </tr>
                                </thead>
                                <tbody id="reservations-table-body" class="divide-y divide-[rgba(68,52,39,0.08)]"></tbody>
                            </table>
                        </div>
                    </article>
                </section>

                <section id="tab-finance" class="tab-content hidden col-span-1 xl:col-span-12">
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-12">
                        <article class="bento-card p-6 sm:p-7 xl:col-span-8">
                            <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Indicateurs financiers</p>
                            <h2 class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]">Ondulation des revenus</h2>
                            <p class="mt-2 text-sm text-[var(--muted)]">Comparaison entre chiffre d'affaires officiel, en attente et estimé.</p>
                            <div class="mt-6 h-[320px]">
                                <canvas id="financeChart"></canvas>
                            </div>
                        </article>

                        <aside class="xl:col-span-4">
                            <article class="bento-card h-full p-6">
                                <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Synthèse du jour</p>
                                <div class="mt-6 space-y-4">
                                    <div class="sand-panel rounded-[24px] p-4">
                                        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA officiel</p>
                                        <p class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]" id="finance-total-official">0 Ar</p>
                                    </div>
                                    <div class="sand-panel rounded-[24px] p-4">
                                        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA en attente</p>
                                        <p class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]" id="finance-total-pending">0 Ar</p>
                                    </div>
                                    <div class="sand-panel rounded-[24px] p-4">
                                        <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">CA estimé</p>
                                        <p class="display-serif mt-2 text-4xl font-semibold text-[var(--ink)]" id="finance-total-ai-side">0 Ar</p>
                                    </div>
                                </div>
                            </article>
                        </aside>

                        <article class="bento-card p-6 sm:p-7 xl:col-span-12">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <p class="text-xs font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Simulation IA</p>
                                    <h3 class="display-serif mt-2 text-3xl font-semibold text-[var(--ink)]">Gain potentiel par journée</h3>
                                    <p class="mt-2 text-sm text-[var(--muted)]">Comparaison entre le prix fixe et les prix IA appliqués aux chambres réellement occupées ou attendues.</p>
                                </div>
                                <div class="sand-panel rounded-[18px] px-5 py-4">
                                    <p class="text-[11px] font-extrabold uppercase tracking-[0.18em] text-[#8f745b]">Écart total</p>
                                    <p class="display-serif mt-1 text-3xl font-semibold text-[var(--ink)]" id="ai-summary-delta">0 Ar</p>
                                </div>
                            </div>
                            <div class="table-shell mobile-card-table mt-6">
                                <table class="data-table w-full text-left text-sm">
                                    <thead>
                                        <tr>
                                            <th class="px-5 py-3">Date</th>
                                            <th class="px-5 py-3">Chambres</th>
                                            <th class="px-5 py-3">CA prix fixe</th>
                                            <th class="px-5 py-3">CA IA simulé</th>
                                            <th class="px-5 py-3">Gain / perte</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ai-summary-table-body" class="divide-y divide-[rgba(68,52,39,0.08)]"></tbody>
                                </table>
                            </div>
                        </article>
                    </div>
                </section>

            </section>
        </main>
    </div>

    <script>
        let currentChart = null;
        let financeChart = null;
        let globalAiDailyCa = 0;
        let allReservationsData = [];
        let sortDirection = 1;
        let lastSortKey = '';

        const chartGridColor = 'rgba(117, 105, 94, 0.16)';
        const chartTextColor = '#75695e';

        function formatMoney(value) {
            return `${Math.round(value || 0).toLocaleString()} Ar`;
        }

        function switchTab(tabId) {
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('.pill-btn').forEach(el => {
                if (!el.id.startsWith('btn-')) return;
                el.classList.remove('active');
                el.classList.add('text-[var(--muted)]');
            });

            document.getElementById('tab-' + tabId).classList.remove('hidden');
            const activeBtn = document.getElementById('btn-' + tabId);
            activeBtn.classList.add('active');
            activeBtn.classList.remove('text-[var(--muted)]');

            if (tabId === 'finance') {
                updateFinanceChart();
            }
        }

        function toggleSettingsPanel(forceState = null) {
            const panel = document.getElementById('settings-panel');
            const nextState = forceState === null ? !panel.classList.contains('open') : forceState;
            panel.classList.toggle('open', nextState);
        }

        function refreshAll() {
            loadAILogic();
            runAudit();
            loadReservations();
            loadAiRevenueSummary();
        }

        function updateConnectionState(isFallback) {
            const connectionDot = document.getElementById('connection-dot');
            const connectionText = document.getElementById('connection-text');
            const connectionContainer = document.getElementById('connection-text-container');

            if (isFallback) {
                connectionDot.className = 'h-2.5 w-2.5 rounded-full bg-slate-400';
                connectionContainer.className = 'mt-1 flex items-center gap-2 text-sm font-semibold text-slate-600';
                connectionText.innerText = 'Prix de base appliqués';
                return;
            }

            connectionDot.className = 'h-2.5 w-2.5 rounded-full bg-emerald-500';
            connectionContainer.className = 'mt-1 flex items-center gap-2 text-sm font-semibold text-emerald-700';
            connectionText.innerText = 'Active';
        }

        function loadAILogic() {
            const daysSelected = document.getElementById('prediction-days').value;
            const pricingDate = document.getElementById('global-date').value;

            fetch(`/api/dashboard/predictions?days=${daysSelected}&start_date=${pricingDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        updateConnectionState(true);
                        return;
                    }

                    updateConnectionState(Boolean(data.is_fallback));

                    const tableBody = document.getElementById('pricing-table-body');
                    tableBody.innerHTML = '';
                    globalAiDailyCa = 0;

                    if (!data.results || Object.keys(data.results).length === 0) {
                        tableBody.innerHTML = '<tr><td colspan="4" class="px-5 py-8 text-center text-[var(--muted)]">Aucune donnée tarifaire disponible.</td></tr>';
                        return;
                    }

                    Object.keys(data.results).forEach(categoryKey => {
                        const categoryData = data.results[categoryKey];
                        const specificDay = categoryData.find(d => d.date === pricingDate) || categoryData[0];
                        const parts = categoryKey.split(' - ');
                        const type = parts[0];
                        const model = parts.slice(1).join(' - ') || 'Standard';
                        const fixedPrice = specificDay.fixed_price_ariary || specificDay.base_price || 0;
                        const adjustedPrice = specificDay.adjusted_price_ariary || specificDay.suggested_price_ariary || fixedPrice;
                        const isSamePrice = adjustedPrice === fixedPrice;
                        const fixedLabel = specificDay.is_fixed_price ? 'Prix non ajustable' : 'Prix de référence';

                        const row = document.createElement('tr');
                        row.innerHTML = `
                            <td data-label="Catégorie" class="px-5 py-4 font-bold text-[var(--ink)]">${type}</td>
                            <td data-label="Modèle" class="px-5 py-4">${model}</td>
                            <td data-label="Prix fixe" class="px-5 py-4">
                                <div class="font-semibold">${formatMoney(fixedPrice)}</div>
                                <div class="text-xs text-[var(--muted)]">${fixedLabel}</div>
                            </td>
                            <td data-label="Prix ajusté IA" class="px-5 py-4">
                                <span class="inline-flex rounded-full ${isSamePrice ? 'bg-white/70 text-[var(--muted)]' : 'bg-[var(--accent-soft)] text-[var(--accent)]'} px-3 py-1 text-sm font-black">
                                    ${formatMoney(adjustedPrice)}
                                </span>
                            </td>
                        `;
                        tableBody.appendChild(row);

                        if (specificDay && specificDay.date === pricingDate) {
                            globalAiDailyCa += adjustedPrice * (specificDay.predicted_occupancy || 0);
                        }
                    });

                    document.getElementById('stat-ca-ia').innerText = formatMoney(globalAiDailyCa);
                    document.getElementById('finance-total-ai').innerText = formatMoney(globalAiDailyCa);
                    document.getElementById('finance-total-ai-side').innerText = formatMoney(globalAiDailyCa);
                    renderPredictionChart(data.results);
                    updateFinanceChart();
                })
                .catch(() => updateConnectionState(true));
        }

        function renderPredictionChart(results) {
            const firstCat = Object.keys(results)[0];
            const labels = results[firstCat].map(p => p.date);
            const values = labels.map((date, i) => {
                let sum = 0;
                Object.keys(results).forEach(cat => {
                    if (results[cat][i]) {
                        sum += results[cat][i].predicted_occupancy || 0;
                    }
                });
                return Math.min(40, sum);
            });

            const ctx = document.getElementById('predictionChart').getContext('2d');
            if (currentChart) {
                currentChart.destroy();
            }

            currentChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels,
                    datasets: [{
                        label: 'Chambres occupées estimées',
                        data: values,
                        borderColor: '#1f7665',
                        backgroundColor: 'rgba(31, 118, 101, 0.15)',
                        fill: true,
                        tension: 0.48,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: labels.length > 30 ? 0 : 2,
                        pointHoverRadius: 5,
                        pointBackgroundColor: '#1f7665',
                        borderWidth: 2.5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#4a3f37',
                                boxWidth: 14,
                                font: { weight: 'bold' }
                            }
                        }
                    },
                    scales: {
                        y: {
                            min: 0,
                            max: 40,
                            ticks: { stepSize: 5, color: chartTextColor },
                            grid: { color: chartGridColor, drawBorder: false },
                            title: {
                                display: true,
                                text: 'Nombre de chambres',
                                color: chartTextColor
                            }
                        },
                        x: {
                            ticks: { color: chartTextColor, maxRotation: 0, autoSkip: true },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        function loadReservations() {
            const date = document.getElementById('global-date').value;
            fetch(`/api/active-reservations?date=${date}`)
                .then(response => response.json())
                .then(data => {
                    allReservationsData = data;
                    renderReservationsTable(data);
                });
        }

        function sortReservations(key) {
            if (lastSortKey === key) {
                sortDirection *= -1;
            } else {
                sortDirection = 1;
                lastSortKey = key;
            }

            const sorted = [...allReservationsData].sort((a, b) => {
                let valA = a[key];
                let valB = b[key];

                if (typeof valA === 'string') valA = valA.toLowerCase();
                if (typeof valB === 'string') valB = valB.toLowerCase();

                if (valA < valB) return -1 * sortDirection;
                if (valA > valB) return 1 * sortDirection;
                return 0;
            });

            renderReservationsTable(sorted);
        }

        function statusBadge(status, paymentStatus = null, cancelledByName = null, processedByName = null) {
            const normalizedStatus = (status || '').toString();
            const normalizedPayment = (paymentStatus || '').toString();
            if (status === 'en_attente') {
                return '<span class="inline-flex rounded-full bg-white/75 px-3 py-1 text-xs font-black text-sky-700">En attente</span>';
            }
            if (normalizedStatus === 'arrive' || normalizedStatus === 'arrive_paid' || normalizedStatus === 'arrive_unpaid') {
                const effectivePayment = normalizedStatus === 'arrive_paid'
                    ? 'paid'
                    : (normalizedStatus === 'arrive_unpaid' ? 'unpaid' : normalizedPayment);
                const paymentLabel = effectivePayment === 'paid'
                    ? 'Arrivé payé'
                    : (effectivePayment === 'partial' || effectivePayment === 'unpaid' || effectivePayment === 'unbilled'
                        ? 'Arrivé non payé'
                        : 'Arrivé');
                const suffix = processedByName ? `<span class="block text-[10px] font-semibold text-[var(--muted)]">par ${processedByName}</span>` : '';
                return `<span class="inline-flex flex-col rounded-full bg-white/75 px-3 py-2 text-xs font-black leading-tight text-emerald-700">${paymentLabel}${suffix}</span>`;
            }
            if (normalizedStatus === 'annule') {
                const suffix = cancelledByName ? `<span class="block text-[10px] font-semibold text-[var(--muted)]">par ${cancelledByName}</span>` : '';
                return `<span class="inline-flex flex-col rounded-full bg-white/75 px-3 py-2 text-xs font-black leading-tight text-rose-700">Annulé${suffix}</span>`;
            }
            return `<span class="inline-flex rounded-full bg-white/75 px-3 py-1 text-xs font-black text-[var(--muted)]">${normalizedStatus || 'N/A'}</span>`;
        }

        function sourceBadge(source) {
            const classes = {
                Booking: 'bg-white/75 text-indigo-700',
                Appel: 'bg-white/75 text-teal-700',
                Mail: 'bg-white/75 text-violet-700',
            };
            return `<span class="inline-flex rounded-full ${(classes[source] || 'bg-white/75 text-[var(--muted)]')} px-3 py-1 text-xs font-bold">${source || 'N/A'}</span>`;
        }

        function renderReservationsTable(data) {
            const tableBody = document.getElementById('reservations-table-body');
            tableBody.innerHTML = '';

            if (data.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="9" class="px-5 py-8 text-center text-[var(--muted)]">Aucune réservation active pour cette date.</td></tr>';
                return;
            }

            data.forEach(res => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td data-label="Référence" class="px-5 py-4 font-mono text-xs font-black text-[var(--accent)]">${res.reference}</td>
                    <td data-label="Client" class="px-5 py-4 font-bold text-[var(--ink)]">${res.client_name}</td>
                    <td data-label="Contact" class="px-5 py-4 text-xs">${res.contact}</td>
                    <td data-label="N° chambres" class="px-5 py-4 font-mono text-xs font-black text-[var(--ink)]">${res.room_numbers || 'N/A'}</td>
                    <td data-label="Chambres" class="px-5 py-4 text-xs font-semibold">${res.rooms}</td>
                    <td data-label="Séjour" class="px-5 py-4 text-xs">${res.check_in} - ${res.check_out}</td>
                    <td data-label="Total fixe" class="px-5 py-4 font-bold text-[var(--muted)]">${formatMoney(res.fixed_total_price)}</td>
                    <td data-label="Total encaissé" class="px-5 py-4 font-black text-[var(--ink)]">${formatMoney(res.paid_amount_ariary)}</td>
                    <td data-label="Statut" class="px-5 py-4">${statusBadge(res.status, res.payment_status, res.cancelled_by_name, res.latest_payment_processed_by)}</td>
                `;
                tableBody.appendChild(row);
            });
        }

        function loadAiRevenueSummary() {
            const daysSelected = document.getElementById('prediction-days').value;
            const pricingDate = document.getElementById('global-date').value;
            fetch(`/api/dashboard/ai-revenue-summary?days=${daysSelected}&start_date=${pricingDate}`)
                .then(response => response.json())
                .then(renderAiRevenueSummary)
                .catch(() => {
                    const tableBody = document.getElementById('ai-summary-table-body');
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="5" class="px-5 py-8 text-center text-[var(--muted)]">Récapitulatif IA indisponible.</td></tr>';
                    }
                });
        }

        function renderAiRevenueSummary(data) {
            const tableBody = document.getElementById('ai-summary-table-body');
            if (!tableBody) {
                return;
            }

            const rows = data.rows || [];
            document.getElementById('ai-summary-delta').innerText = formatMoney(data.totals?.delta_ariary || 0);
            tableBody.innerHTML = '';

            if (rows.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="5" class="px-5 py-8 text-center text-[var(--muted)]">Aucune journée à comparer.</td></tr>';
                return;
            }

            rows.forEach(rowData => {
                const delta = Number(rowData.delta_ariary || 0);
                const deltaClass = delta > 0 ? 'text-emerald-700' : (delta < 0 ? 'text-rose-700' : 'text-[var(--muted)]');
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td data-label="Date" class="px-5 py-4 font-mono text-xs font-black text-[var(--accent)]">${rowData.date}</td>
                    <td data-label="Chambres" class="px-5 py-4 font-bold text-[var(--ink)]">${rowData.room_count}</td>
                    <td data-label="CA prix fixe" class="px-5 py-4 font-semibold text-[var(--muted)]">${formatMoney(rowData.fixed_revenue_ariary)}</td>
                    <td data-label="CA IA simulé" class="px-5 py-4 font-black text-[var(--ink)]">${formatMoney(rowData.ai_revenue_ariary)}</td>
                    <td data-label="Gain / perte" class="px-5 py-4 font-black ${deltaClass}">${delta > 0 ? '+' : ''}${formatMoney(delta)}</td>
                `;
                tableBody.appendChild(row);
            });
        }

        function runAudit() {
            const dateSelected = document.getElementById('global-date').value;
            fetch(`/api/dashboard/audit-date?date=${dateSelected}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        return;
                    }

                    document.getElementById('stat-ca-official').innerText = formatMoney(data.daily_ca_official);
                    document.getElementById('stat-ca-pending').innerText = formatMoney(data.daily_ca_pending);
                    document.getElementById('stat-ca-total').innerText = formatMoney(data.total_ca);
                    document.getElementById('stat-rooms-confirmed').innerText = data.rooms_confirmed || 0;
                    document.getElementById('stat-rooms-estimated').innerText = data.rooms_estimated || 0;
                    document.getElementById('ca-period').innerText = data.period;

                    document.getElementById('finance-total-official').innerText = formatMoney(data.daily_ca_official);
                    document.getElementById('finance-total-pending').innerText = formatMoney(data.daily_ca_pending);

                    const totalRooms = 37;
                    const roomsConfirmed = data.rooms_confirmed || 0;
                    const occRate = (roomsConfirmed / totalRooms) * 100;
                    const adr = roomsConfirmed > 0 ? (data.daily_ca_official / roomsConfirmed) : 0;
                    const revpar = data.daily_ca_official / totalRooms;

                    document.getElementById('finance-occ-rate').innerText = `${occRate.toFixed(1)}%`;
                    document.getElementById('finance-adr').innerText = formatMoney(adr);
                    document.getElementById('finance-revpar').innerText = formatMoney(revpar);
                    updateFinanceChart(data);
                });
        }

        function updateFinanceChart(data = null) {
            const officialText = document.getElementById('stat-ca-official').innerText || '0';
            const pendingText = document.getElementById('stat-ca-pending').innerText || '0';
            const aiText = document.getElementById('stat-ca-ia').innerText || '0';
            const official = data?.daily_ca_official ?? Number(officialText.replace(/\D/g, ''));
            const pending = data?.daily_ca_pending ?? Number(pendingText.replace(/\D/g, ''));
            const estimated = Number(aiText.replace(/\D/g, ''));

            const canvas = document.getElementById('financeChart');
            if (!canvas) {
                return;
            }

            const ctx = canvas.getContext('2d');
            if (financeChart) {
                financeChart.destroy();
            }

            financeChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Officiel', 'En attente', 'Estimé'],
                    datasets: [{
                        label: 'Chiffre d’affaires',
                        data: [official, pending, estimated],
                        borderColor: '#2a221b',
                        backgroundColor: 'rgba(42, 34, 27, 0.08)',
                        fill: true,
                        tension: 0.5,
                        cubicInterpolationMode: 'monotone',
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBackgroundColor: '#2a221b',
                        borderWidth: 2.5,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: '#4a3f37',
                                font: { weight: 'bold' }
                            }
                        },
                    },
                    scales: {
                        y: {
                            ticks: {
                                color: chartTextColor,
                                callback: value => `${Number(value).toLocaleString()} Ar`
                            },
                            grid: { color: chartGridColor, drawBorder: false }
                        },
                        x: {
                            ticks: { color: chartTextColor, font: { weight: 'bold' } },
                            grid: { display: false }
                        }
                    }
                }
            });
        }

        document.addEventListener('DOMContentLoaded', function() {
            const now = new Date();
            const today = now.toISOString().substring(0, 10);
            document.getElementById('global-date').value = today;

            document.getElementById('settings-trigger').addEventListener('click', function(event) {
                event.stopPropagation();
                toggleSettingsPanel();
            });

            document.addEventListener('click', function(event) {
                const panel = document.getElementById('settings-panel');
                const trigger = document.getElementById('settings-trigger');
                if (!panel.contains(event.target) && !trigger.contains(event.target)) {
                    toggleSettingsPanel(false);
                }
            });

            refreshAll();
        });
    </script>
</body>
</html>
