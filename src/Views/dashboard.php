<?php require_once __DIR__ . '/header.php'; ?>
<?php
    $periodConfig = $periodConfig ?? [
        'label' => 'Semana Atual',
        'interval' => 'DATE_SUB(CURDATE(), INTERVAL 1 WEEK)',
    ];
    $periodOptions = $periodOptions ?? [
        'week' => 'Semanal',
        'month' => 'Mensal',
        'year' => 'Anual',
    ];
    $period = $period ?? 'week';
    $dashboardStats = $dashboardStats ?? [
        'total_completed' => 0,
        'avg_time_spent' => 0,
        'genres' => [],
        'status_breakdown' => [],
        'trend' => [
            'labels' => [],
            'values' => [],
        ],
        'time_trend' => [
            'labels' => [],
            'values' => [],
        ],
    ];
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <h1 class="text-3xl font-black text-white tracking-tighter uppercase">Dashboard</h1>
                <p class="text-sm text-zinc-400 font-medium mt-1">Visão geral do progresso por período</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="index.php?action=home" class="bg-zinc-800 hover:bg-zinc-700 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors border-b-2 border-zinc-950 hover:border-zinc-900">Biblioteca</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6">
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 id="dashboardPeriodLabel" class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3"><?php echo htmlspecialchars($periodConfig['label']); ?></h2>
                <p class="text-zinc-400 mt-2 text-sm">Os jogos finalizados são contados usando o campo de data de conclusão.</p>
            </div>

            <div id="dashboardTabs" class="flex items-center gap-2 bg-zinc-900 border-2 border-zinc-800 rounded-sm p-1 w-full sm:w-auto">
                <?php foreach ($periodOptions as $key => $label): ?>
                    <a href="index.php?action=dashboard&period=<?php echo urlencode($key); ?>" data-period="<?php echo htmlspecialchars($key); ?>" class="dashboard-tab flex-1 sm:flex-none px-4 py-2 text-sm font-bold uppercase tracking-widest rounded-sm transition-colors <?php echo $key === $period ? 'bg-violet-600 text-white' : 'text-zinc-400 hover:text-white hover:bg-zinc-800'; ?>"><?php echo htmlspecialchars($label); ?></a>
                <?php endforeach; ?>
            </div>
        </div>

        <section class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
                <span class="text-xs font-black uppercase tracking-widest text-zinc-500">Total concluído</span>
                <div id="dashboardTotalCompleted" class="mt-3 text-4xl font-black text-white"><?php echo (int) $dashboardStats['total_completed']; ?></div>
            </article>

            <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
                <span class="text-xs font-black uppercase tracking-widest text-zinc-500">Tempo médio por jogo</span>
                <div id="dashboardAvgTime" class="mt-3 text-4xl font-black text-amber-400"><?php echo number_format((float) $dashboardStats['avg_time_spent'], 2, ',', '.'); ?>h</div>
            </article>

            <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
                <span class="text-xs font-black uppercase tracking-widest text-zinc-500">Período</span>
                <div id="dashboardPeriodBadge" class="mt-3 text-4xl font-black text-violet-400"><?php echo htmlspecialchars($periodConfig['label']); ?></div>
            </article>
        </section>

        <section class="mb-8 bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
            <div class="flex items-center justify-between gap-4 mb-5">
                <h3 class="text-xl font-black uppercase tracking-tight text-white">Distribuição por status</h3>
            </div>
            <?php if (!empty($dashboardStats['status_breakdown'])): ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <?php foreach ($dashboardStats['status_breakdown'] as $statusRow): ?>
                        <div class="bg-zinc-950 border border-zinc-800 rounded-sm p-4 flex items-center justify-between gap-4">
                            <span class="font-semibold text-zinc-200"><?php echo htmlspecialchars($statusRow['status'] ?? 'Indefinido'); ?></span>
                            <span class="text-sm font-black text-amber-400"><?php echo (int) ($statusRow['total'] ?? 0); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-zinc-500 italic">Nenhum dado de status encontrado.</p>
            <?php endif; ?>
        </section>

        <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
            <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
                <div class="flex items-center justify-between gap-4 mb-5">
                    <h3 class="text-xl font-black uppercase tracking-tight text-white">Distribuição por gênero</h3>
                </div>
                <div class="h-[320px] md:h-[360px]">
                    <canvas id="genreChart"></canvas>
                </div>
            </article>

            <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
                <div class="flex items-center justify-between gap-4 mb-5">
                    <h3 class="text-xl font-black uppercase tracking-tight text-white">Tendência de conclusão</h3>
                </div>
                <div class="h-[320px] md:h-[360px]">
                    <canvas id="trendChart"></canvas>
                </div>
            </article>

            <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
                <div class="flex items-center justify-between gap-4 mb-5">
                    <h3 class="text-xl font-black uppercase tracking-tight text-white">Tendência de tempo gasto</h3>
                </div>
                <div class="h-[320px] md:h-[360px]">
                    <canvas id="timeChart"></canvas>
                </div>
            </article>
        </section>

        <section class="mt-6 bg-zinc-900 border-2 border-zinc-800 rounded-sm p-6 shadow-xl">
            <div class="flex items-center justify-between gap-4 mb-5">
                <h3 class="text-xl font-black uppercase tracking-tight text-white">Detalhamento bruto</h3>
            </div>

            <div id="dashboardGenreList">
                <?php if (!empty($dashboardStats['genres'])): ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-3">
                        <?php foreach ($dashboardStats['genres'] as $genreRow): ?>
                            <div class="bg-zinc-950 border border-zinc-800 rounded-sm p-4 flex items-center justify-between gap-4">
                                <span class="font-semibold text-zinc-200"><?php echo htmlspecialchars($genreRow['genre']); ?></span>
                                <span class="text-sm font-black text-violet-400"><?php echo (int) $genreRow['total']; ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-zinc-500 italic">Nenhum jogo concluído encontrado para este período.</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script src="./assets/js/dashboard.js"></script>
</body>
</html>