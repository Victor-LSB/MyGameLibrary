<?php
    $displayName = $profileUser['display_name'] ?: $profileUser['username'];
    $pageTitle = "Retrospectiva $year de $displayName - MyGameLibrary";
    $metaDescription = $review['completedCount'] > 0
        ? "$displayName zerou {$review['completedCount']} " . ($review['completedCount'] === 1 ? 'jogo' : 'jogos') . " em $year. Veja a retrospectiva completa no MyGameLibrary."
        : "Retrospectiva $year de $displayName no MyGameLibrary.";
    $ogImage = $review['favorites'][0]['cover_image'] ?? ($review['completed'][0]['cover_image'] ?? null);
    require __DIR__ . '/../header.php';

    $maxMonth = max($review['byMonth']) ?: 1;
    $monthsShort = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $shareUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/index.php?' . http_build_query(['action' => 'year_review', 'u' => $profileUser['username'], 'y' => $year]);
    $fmtHours = fn($h) => number_format((float) $h, 0, ',', '.');
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="max-w-4xl mx-auto px-4 sm:px-6">
    <!-- Capa -->
    <section class="relative overflow-hidden rounded-sm border-2 border-violet-600 bg-gradient-to-br from-violet-950 via-zinc-900 to-zinc-950 p-6 sm:p-10">
        <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-6">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.3em] text-violet-300">Retrospectiva</p>
                <h1 class="mt-2 text-6xl sm:text-8xl font-black text-white tracking-tighter leading-none"><?php echo $year; ?></h1>
                <p class="mt-3 text-zinc-300">O ano em jogos de <a href="<?php echo htmlspecialchars(mgl_url('profile', ['u' => $profileUser['username']])); ?>" class="font-bold text-white hover:text-violet-300"><?php echo htmlspecialchars($displayName); ?></a></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <?php if (count($availableYears) > 1): ?>
                    <form action="index.php" method="get">
                        <input type="hidden" name="action" value="year_review">
                        <input type="hidden" name="u" value="<?php echo htmlspecialchars($profileUser['username']); ?>">
                        <select name="y" onchange="this.form.submit()" aria-label="Escolher ano" class="bg-zinc-950 border-2 border-zinc-700 text-white font-bold rounded-sm px-3 py-2 focus:outline-none focus:border-violet-500">
                            <?php foreach ($availableYears as $y): ?>
                                <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
                <button type="button" id="shareReview" data-url="<?php echo htmlspecialchars($shareUrl); ?>" data-title="<?php echo htmlspecialchars($pageTitle); ?>" class="bg-violet-600 hover:bg-violet-500 text-white px-4 py-2.5 rounded-sm font-bold uppercase tracking-wide text-xs transition-colors">Compartilhar</button>
            </div>
        </div>
    </section>

    <?php if ($review['completedCount'] === 0 && $review['added'] === 0): ?>
        <div class="mt-8 bg-zinc-900 border-2 border-zinc-800 rounded-sm p-8 text-center">
            <p class="text-zinc-400"><?php echo $isOwner ? 'Você ainda não registrou' : htmlspecialchars($displayName) . ' ainda não registrou'; ?> nenhum jogo zerado em <?php echo $year; ?>.</p>
            <?php if ($isOwner): ?>
                <p class="mt-2 text-zinc-500 text-sm">Marque um jogo como Zerado com a data de conclusão e ele aparece aqui.</p>
            <?php endif; ?>
        </div>
    <?php else: ?>

    <!-- Números grandes -->
    <section class="mt-6 grid grid-cols-2 sm:grid-cols-4 gap-3">
        <?php
            $tiles = [
                ['Zerados', $review['completedCount'], 'text-emerald-400'],
                ['Horas jogadas', $fmtHours($review['hours']), 'text-white'],
                ['Platinas', $review['platinums'], 'text-amber-400'],
                ['Nota média', $review['avgRating'] !== null ? number_format($review['avgRating'], 1, ',', '') : '—', 'text-amber-400'],
            ];
        ?>
        <?php foreach ($tiles as [$label, $value, $color]): ?>
            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-4 text-center">
                <p class="text-4xl font-black <?php echo $color; ?>"><?php echo htmlspecialchars((string) $value); ?></p>
                <p class="mt-1 text-[11px] font-bold uppercase tracking-widest text-zinc-500"><?php echo $label; ?></p>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- Destaques em frase -->
    <section class="mt-6 grid sm:grid-cols-3 gap-3">
        <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-5">
            <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-500">Gênero do ano</p>
            <p class="mt-2 text-xl font-black text-white"><?php echo $review['topGenres'] ? htmlspecialchars(array_key_first($review['topGenres'])) : '—'; ?></p>
            <?php if (count($review['topGenres']) > 1): ?>
                <p class="mt-1 text-xs text-zinc-500">depois: <?php echo htmlspecialchars(implode(', ', array_slice(array_keys($review['topGenres']), 1))); ?></p>
            <?php endif; ?>
        </div>
        <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-5">
            <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-500">Maior maratona</p>
            <?php if ($review['longest']): ?>
                <a href="<?php echo htmlspecialchars(mgl_game_url($review['longest']['id'])); ?>" class="mt-2 block text-xl font-black text-white hover:text-violet-300 line-clamp-2"><?php echo htmlspecialchars($review['longest']['title']); ?></a>
                <p class="mt-1 text-xs text-zinc-500"><?php echo $fmtHours($review['longest']['time_spent_hours']); ?> horas</p>
            <?php else: ?>
                <p class="mt-2 text-xl font-black text-zinc-600">—</p>
            <?php endif; ?>
        </div>
        <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-5">
            <p class="text-[11px] font-bold uppercase tracking-widest text-zinc-500">Na biblioteca</p>
            <p class="mt-2 text-xl font-black text-white"><?php echo $review['added']; ?> <?php echo $review['added'] === 1 ? 'jogo novo' : 'jogos novos'; ?></p>
            <p class="mt-1 text-xs text-zinc-500"><?php echo $review['reviews']; ?> <?php echo $review['reviews'] === 1 ? 'análise publicada' : 'análises publicadas'; ?></p>
        </div>
    </section>

    <?php if ($review['completedCount'] > 0): ?>
        <!-- Zerados por mês -->
        <section class="mt-6 bg-zinc-900 border-2 border-zinc-800 rounded-sm p-5">
            <div class="flex items-baseline justify-between gap-4">
                <h2 class="text-sm font-black text-white uppercase tracking-widest">Zerados por mês</h2>
                <?php if ($review['busiestMonth']): ?>
                    <p class="text-xs text-zinc-500">Mês mais forte: <span class="text-white font-bold"><?php echo mgl_month_pt($review['busiestMonth']); ?></span></p>
                <?php endif; ?>
            </div>
            <div class="mt-5 grid grid-cols-12 gap-1.5 sm:gap-3 items-end h-32" role="img" aria-label="Jogos zerados em cada mês de <?php echo $year; ?>">
                <?php foreach ($review['byMonth'] as $m => $n): ?>
                    <div class="flex flex-col items-center justify-end h-full gap-1" title="<?php echo mgl_month_pt($m) . ': ' . $n; ?>">
                        <span class="text-[10px] font-bold <?php echo $n ? 'text-zinc-300' : 'text-transparent'; ?>"><?php echo $n; ?></span>
                        <span class="w-full rounded-sm <?php echo $n ? 'bg-emerald-500' : 'bg-zinc-800'; ?>" style="height: <?php echo $n ? max(8, round($n / $maxMonth * 100)) : 4; ?>%"></span>
                        <span class="text-[10px] uppercase text-zinc-500"><?php echo $monthsShort[$m - 1]; ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($review['favorites']): ?>
        <!-- Favoritos -->
        <section class="mt-8">
            <h2 class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-4">Favoritos do ano</h2>
            <div class="grid grid-cols-3 sm:grid-cols-5 gap-3">
                <?php foreach ($review['favorites'] as $i => $g): ?>
                    <a href="<?php echo htmlspecialchars(mgl_game_url($g['id'])); ?>" class="group block">
                        <div class="relative aspect-[3/4] bg-zinc-900 border-2 <?php echo $i === 0 ? 'border-amber-400' : 'border-zinc-800'; ?> rounded-sm overflow-hidden">
                            <?php if (!empty($g['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars($g['cover_image']); ?>" alt="<?php echo htmlspecialchars($g['title']); ?>" loading="lazy" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <?php else: ?>
                                <span class="w-full h-full flex items-center justify-center p-2 text-center text-xs font-bold text-zinc-500"><?php echo htmlspecialchars($g['title']); ?></span>
                            <?php endif; ?>
                            <span class="absolute top-1.5 left-1.5 bg-zinc-950/90 text-white text-[10px] font-black px-1.5 py-0.5 rounded-sm">#<?php echo $i + 1; ?></span>
                        </div>
                        <p class="mt-1.5 text-xs font-bold text-zinc-300 line-clamp-1 group-hover:text-white"><?php echo htmlspecialchars($g['title']); ?></p>
                        <p class="text-[11px] text-amber-400"><?php echo str_repeat('★', (int) $g['rating']); ?></p>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($review['completedCount'] > 0): ?>
        <!-- Lista completa -->
        <section class="mt-8">
            <h2 class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-4">Todos os zerados</h2>
            <ol class="bg-zinc-900 border-2 border-zinc-800 rounded-sm divide-y divide-zinc-800">
                <?php foreach ($review['completed'] as $g): ?>
                    <li class="flex items-center gap-3 px-4 py-3 text-sm">
                        <span class="w-14 shrink-0 text-xs text-zinc-500"><?php echo date('d/m', strtotime($g['completion_date'])); ?></span>
                        <a href="<?php echo htmlspecialchars(mgl_game_url($g['id'])); ?>" class="flex-1 min-w-0 font-bold text-white hover:text-violet-300 truncate"><?php echo htmlspecialchars($g['title']); ?></a>
                        <?php if (!empty($g['platinum_at'])): ?><span title="Platinado">🏆</span><?php endif; ?>
                        <?php if ($g['rating']): ?><span class="text-amber-400 text-xs shrink-0"><?php echo str_repeat('★', (int) $g['rating']); ?></span><?php endif; ?>
                        <?php if ($g['time_spent_hours']): ?><span class="w-12 text-right text-xs text-zinc-500 shrink-0"><?php echo $fmtHours($g['time_spent_hours']); ?> h</span><?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>

    <?php endif; ?>
</main>

<script>
(function () {
    const btn = document.getElementById('shareReview');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        const url = btn.dataset.url.startsWith('http') ? btn.dataset.url : location.href;
        if (navigator.share) {
            try { await navigator.share({ title: btn.dataset.title, url }); } catch (e) { /* cancelado */ }
            return;
        }
        try {
            await navigator.clipboard.writeText(url);
            btn.textContent = 'Link copiado!';
            setTimeout(() => { btn.textContent = 'Compartilhar'; }, 2000);
        } catch (e) {
            prompt('Copie o link:', url);
        }
    });
})();
</script>
</body>
</html>
