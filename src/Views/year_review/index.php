<?php
    $displayName = $profileUser['display_name'] ?: $profileUser['username'];
    $pageTitle = "Retrospectiva $year de $displayName - MyGameLibrary";
    $metaDescription = $review['completedCount'] > 0
        ? "$displayName zerou {$review['completedCount']} " . ($review['completedCount'] === 1 ? 'jogo' : 'jogos') . " em $year. Veja a retrospectiva completa no MyGameLibrary."
        : "Retrospectiva $year de $displayName no MyGameLibrary.";
    // Card de compartilhar (imagem gerada com o banner do perfil de fundo). "v" muda quando
    // o card muda, para o WhatsApp/Discord não mostrarem a versão antiga do cache deles.
    $cardSupported = \Victi\MyGameLibrary\Controllers\ProfileCardController::isSupported();
    $yearCardVersion = substr(md5(json_encode([
        $profileUser['banner'] ?? null, $displayName, $review['completedCount'], round((float) $review['hours']),
        $review['platinums'], $review['avgRating'], array_column($review['favorites'], 'id'),
    ])), 0, 8);
    $yearCardUrl = 'index.php?' . http_build_query(['action' => 'year_card', 'u' => $profileUser['username'], 'y' => $year, 'v' => $yearCardVersion]);
    $ogImage = $cardSupported
        ? $yearCardUrl
        : ($review['favorites'][0]['cover_image'] ?? ($review['completed'][0]['cover_image'] ?? null));
    require __DIR__ . '/../header.php';

    $maxMonth = max($review['byMonth']) ?: 1;
    $monthsShort = ['jan', 'fev', 'mar', 'abr', 'mai', 'jun', 'jul', 'ago', 'set', 'out', 'nov', 'dez'];
    $shareUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/') . '/index.php?' . http_build_query(['action' => 'year_review', 'u' => $profileUser['username'], 'y' => $year]);
    $fmtHours = fn($h) => number_format((float) $h, 0, ',', '.');
    $retroGameUrl = fn($gameId) => mgl_url('game', ['id' => (int) $gameId, 'from' => 'retro', 'fu' => $profileUser['username'], 'fy' => $year]);
    // Jogos zerados em cada mês (para o gráfico mostrar quais foram)
    $gamesByMonth = array_fill(1, 12, []);
    foreach ($review['completed'] as $g) {
        $gamesByMonth[(int) substr($g['completion_date'], 5, 2)][] = $g;
    }
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="max-w-screen-2xl mx-auto px-4 sm:px-6">
    <!-- Capa: texto e controles à esquerda, leque com as capas do ano à direita -->
    <?php
        // Capas do leque: favoritos primeiro, depois o resto do que foi zerado no ano
        $heroCovers = [];
        foreach (array_merge($review['favorites'], array_reverse($review['completed'])) as $g) {
            if (!empty($g['cover_image']) && !isset($heroCovers[$g['id']])) {
                $heroCovers[$g['id']] = $g;
            }
        }
        $heroCovers = array_slice(array_values($heroCovers), 0, 7);
        $tilts = [-6, 3, -2, 5, -4, 2, -3];
    ?>
    <section class="relative overflow-hidden rounded-sm border-2 border-violet-600 bg-gradient-to-br from-violet-950 via-zinc-900 to-zinc-950">
        <div class="grid lg:grid-cols-[minmax(0,26rem)_1fr] items-center gap-8 p-6 sm:p-10">
            <div class="relative z-10">
                <p class="text-xs font-black uppercase tracking-[0.3em] text-violet-300">Retrospectiva</p>
                <h1 class="mt-2 text-6xl sm:text-8xl font-black text-white tracking-tighter leading-none"><?php echo $year; ?></h1>
                <p class="mt-3 text-zinc-300">O ano em jogos de <a href="<?php echo htmlspecialchars(mgl_url('profile', ['u' => $profileUser['username']])); ?>" class="font-bold text-white hover:text-violet-300"><?php echo htmlspecialchars($displayName); ?></a></p>
                <div class="mt-6 flex flex-wrap items-center gap-2">
                    <?php if (count($availableYears) > 1): ?>
                        <form action="index.php" method="get">
                            <input type="hidden" name="action" value="year_review">
                            <input type="hidden" name="u" value="<?php echo htmlspecialchars($profileUser['username']); ?>">
                            <select name="y" data-custom-select onchange="this.form.submit()" aria-label="Escolher ano" class="bg-zinc-950 border-2 border-zinc-700 text-white font-bold rounded-sm px-4 py-2.5 min-w-[7rem] focus:outline-none focus:border-violet-500 cursor-pointer appearance-none">
                                <?php foreach ($availableYears as $y): ?>
                                    <option value="<?php echo $y; ?>" <?php echo $y === $year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    <?php endif; ?>
                    <button type="button" id="shareReview" data-url="<?php echo htmlspecialchars($shareUrl); ?>" data-title="<?php echo htmlspecialchars($pageTitle); ?>" class="btn btn-primary">Compartilhar</button>
                </div>
            </div>

            <?php if ($heroCovers): ?>
                <div class="hidden md:flex min-w-0 overflow-hidden justify-end items-center pr-2 lg:pr-6 py-4" aria-hidden="true"
                     style="-webkit-mask-image: linear-gradient(to right, transparent, #000 18%); mask-image: linear-gradient(to right, transparent, #000 18%);">
                    <?php foreach ($heroCovers as $i => $g): ?>
                        <div class="relative shrink-0 w-28 lg:w-36 xl:w-40 aspect-[3/4] <?php echo $i ? '-ml-8 lg:-ml-10' : ''; ?> rounded-sm overflow-hidden border-2 border-zinc-900 shadow-2xl shadow-black/70 bg-zinc-900 transition-transform duration-300 hover:-translate-y-2 hover:z-20"
                             style="transform: rotate(<?php echo $tilts[$i % count($tilts)]; ?>deg); z-index: <?php echo 10 - $i; ?>;">
                            <img src="<?php echo htmlspecialchars($g['cover_image']); ?>" alt="" class="w-full h-full object-cover">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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

    <div class="mt-6 grid gap-6 lg:grid-cols-12 items-start">
    <div class="lg:col-span-8 min-w-0">

    <!-- Números grandes -->
    <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
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
                <a href="<?php echo htmlspecialchars($retroGameUrl($review['longest']['id'])); ?>" class="mt-2 block text-xl font-black text-white hover:text-violet-300 line-clamp-2"><?php echo htmlspecialchars($review['longest']['title']); ?></a>
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
            <p class="mt-1 text-xs text-zinc-500">Passe o mouse para ver os jogos; clique num mês para filtrar a lista.</p>
            <div id="monthChart" class="mt-5 grid grid-cols-12 gap-1.5 sm:gap-3 items-end h-36">
                <?php foreach ($review['byMonth'] as $m => $n): $monthGames = $gamesByMonth[$m]; ?>
                    <button type="button" <?php echo $n ? '' : 'disabled'; ?> data-month="<?php echo $m; ?>" data-month-name="<?php echo mgl_month_pt($m); ?>"
                        class="month-bar group relative flex flex-col items-center justify-end h-full gap-1 rounded-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-violet-400 <?php echo $n ? 'cursor-pointer' : 'cursor-default'; ?>"
                        aria-label="<?php echo htmlspecialchars(mgl_month_pt($m) . ': ' . $n . ' ' . ($n === 1 ? 'jogo' : 'jogos') . ($n ? ' — ' . implode(', ', array_column($monthGames, 'title')) : '')); ?>">
                        <span class="text-[10px] font-bold <?php echo $n ? 'text-zinc-300' : 'text-transparent'; ?>"><?php echo $n; ?></span>
                        <span class="month-bar__fill w-full rounded-sm transition-colors <?php echo $n ? 'bg-emerald-500 group-hover:bg-emerald-400' : 'bg-zinc-800'; ?>" style="height: <?php echo $n ? max(8, round($n / $maxMonth * 100)) : 4; ?>%"></span>
                        <span class="month-bar__label text-[10px] uppercase text-zinc-500"><?php echo $monthsShort[$m - 1]; ?></span>
                        <?php if ($n): ?>
                            <!-- Lista do mês ao passar o mouse -->
                            <span class="pointer-events-none absolute bottom-full mb-2 <?php echo $m <= 2 ? 'left-0' : ($m >= 11 ? 'right-0' : 'left-1/2 -translate-x-1/2'); ?> z-30 hidden group-hover:block group-focus-visible:block w-56 text-left bg-zinc-950 border-2 border-zinc-700 rounded-sm shadow-2xl p-3">
                                <span class="block text-[10px] font-black uppercase tracking-widest text-emerald-400 mb-2"><?php echo mgl_month_pt($m); ?> · <?php echo $n; ?> <?php echo $n === 1 ? 'zerado' : 'zerados'; ?></span>
                                <?php foreach (array_slice($monthGames, 0, 6) as $mg): ?>
                                    <span class="flex items-center gap-2 py-1">
                                        <?php if (!empty($mg['cover_image'])): ?>
                                            <img src="<?php echo htmlspecialchars($mg['cover_image']); ?>" alt="" loading="lazy" class="w-6 h-8 object-cover rounded-sm shrink-0 bg-zinc-800">
                                        <?php endif; ?>
                                        <span class="text-xs font-bold text-white leading-tight line-clamp-2"><?php echo htmlspecialchars($mg['title']); ?></span>
                                    </span>
                                <?php endforeach; ?>
                                <?php if ($n > 6): ?><span class="block text-[11px] text-zinc-500 mt-1">e mais <?php echo $n - 6; ?></span><?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </button>
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
                    <a href="<?php echo htmlspecialchars($retroGameUrl($g['id'])); ?>" class="group block">
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

    </div>

    <aside class="lg:col-span-4 lg:sticky lg:top-6">
    <?php if ($review['completedCount'] > 0): ?>
        <!-- Lista completa -->
        <section>
            <div class="flex items-baseline justify-between gap-3 mb-4">
                <h2 id="completedTitle" class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3">Todos os zerados</h2>
                <button type="button" id="clearMonthFilter" class="hidden text-xs font-bold uppercase tracking-wider text-violet-400 hover:text-violet-300">Ver todos</button>
            </div>
            <!-- Colunas fixas (data | capa | jogo | platina | nota | horas): tudo alinha mesmo quando falta algum dado -->
            <ol class="bg-zinc-900 border-2 border-zinc-800 rounded-sm lg:max-h-[calc(100vh-8rem)] lg:overflow-y-auto">
                <?php foreach ($gamesByMonth as $m => $monthGames): if (!$monthGames) continue; ?>
                    <li data-month="<?php echo $m; ?>" class="sticky top-0 z-10 flex items-center justify-between px-4 py-2 bg-zinc-950/95 backdrop-blur border-b border-zinc-800">
                        <span class="text-[11px] font-black uppercase tracking-widest text-emerald-400"><?php echo mgl_month_pt($m); ?></span>
                        <span class="text-[11px] font-bold text-zinc-500"><?php echo count($monthGames); ?> <?php echo count($monthGames) === 1 ? 'jogo' : 'jogos'; ?></span>
                    </li>
                    <?php foreach ($monthGames as $g): $rating = (int) $g['rating']; ?>
                        <li data-month="<?php echo $m; ?>" class="grid grid-cols-[2.25rem_2rem_minmax(0,1fr)_2.75rem] sm:grid-cols-[2.5rem_2rem_minmax(0,1fr)_1.25rem_4.75rem_2.75rem] items-center gap-2.5 sm:gap-3 px-3 sm:px-4 py-2.5 text-sm border-b border-zinc-800/70 last:border-b-0 hover:bg-zinc-800/40 transition-colors">
                            <span class="text-xs tabular-nums text-zinc-500"><?php echo date('d/m', strtotime($g['completion_date'])); ?></span>
                            <span class="w-8 h-11 rounded-sm overflow-hidden bg-zinc-800 border border-zinc-700">
                                <?php if (!empty($g['cover_image'])): ?>
                                    <img src="<?php echo htmlspecialchars($g['cover_image']); ?>" alt="" loading="lazy" class="w-full h-full object-cover">
                                <?php endif; ?>
                            </span>
                            <span class="min-w-0">
                                <a href="<?php echo htmlspecialchars($retroGameUrl($g['id'])); ?>" class="block font-bold text-white hover:text-violet-300 truncate" title="<?php echo htmlspecialchars($g['title']); ?>"><?php echo htmlspecialchars($g['title']); ?></a>
                                <!-- Celular: troféu e nota numa linha embaixo do nome, para o nome ter espaço -->
                                <span class="sm:hidden flex items-center gap-1.5 mt-0.5 text-[11px]">
                                    <?php if (!empty($g['platinum_at'])): ?><span title="Platinado">🏆</span><?php endif; ?>
                                    <?php if ($rating): ?><span class="text-amber-400"><?php echo str_repeat('★', $rating); ?></span><span class="text-zinc-700 -ml-1.5"><?php echo str_repeat('★', 5 - $rating); ?></span><?php else: ?><span class="text-zinc-600">sem nota</span><?php endif; ?>
                                </span>
                            </span>
                            <span class="hidden sm:block text-center"><?php echo !empty($g['platinum_at']) ? '<span title="Platinado">🏆</span>' : ''; ?></span>
                            <span class="hidden sm:block text-xs tracking-tight text-right whitespace-nowrap" aria-label="<?php echo $rating ? "Nota $rating de 5" : 'Sem nota'; ?>">
                                <?php if ($rating): ?>
                                    <span class="text-amber-400"><?php echo str_repeat('★', $rating); ?></span><span class="text-zinc-700"><?php echo str_repeat('★', 5 - $rating); ?></span>
                                <?php else: ?>
                                    <span class="text-zinc-600">—</span>
                                <?php endif; ?>
                            </span>
                            <span class="text-xs tabular-nums text-right <?php echo $g['time_spent_hours'] ? 'text-zinc-400' : 'text-zinc-600'; ?>"><?php echo $g['time_spent_hours'] ? $fmtHours($g['time_spent_hours']) . ' h' : '—'; ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
    </aside>
    </div>

    <?php endif; ?>
</main>


<script>
// Clique num mês do gráfico filtra a lista de zerados; clicar de novo (ou "Ver todos") limpa
(function () {
    const bars = document.querySelectorAll('.month-bar[data-month]:not([disabled])');
    const items = document.querySelectorAll('li[data-month]');
    const title = document.getElementById('completedTitle');
    const clear = document.getElementById('clearMonthFilter');
    if (!bars.length || !title) return;
    let current = null;

    function apply(month, name) {
        current = month;
        items.forEach((li) => { li.classList.toggle('hidden', month !== null && li.dataset.month !== month); });
        bars.forEach((b) => {
            const active = b.dataset.month === month;
            b.querySelector('.month-bar__fill').classList.toggle('!bg-violet-500', active);
            b.querySelector('.month-bar__label').classList.toggle('!text-white', active);
            b.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
        title.textContent = month === null ? 'Todos os zerados' : 'Zerados em ' + name;
        clear.classList.toggle('hidden', month === null);
        if (month !== null && window.matchMedia('(max-width: 1023px)').matches) {
            title.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    bars.forEach((b) => b.addEventListener('click', () => {
        apply(current === b.dataset.month ? null : b.dataset.month, b.dataset.monthName);
    }));
    clear.addEventListener('click', () => apply(null));
})();

</script>

<!-- Janela de compartilhar: prévia do card + copiar link / baixar imagem (igual à do perfil) -->
<div id="shareReviewModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="shareReviewTitle">
    <div class="w-full max-w-2xl bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl">
        <div class="flex items-center justify-between px-5 py-4 border-b-2 border-zinc-800">
            <h2 id="shareReviewTitle" class="text-white font-black uppercase tracking-tight">Compartilhar retrospectiva <?php echo $year; ?></h2>
            <button type="button" data-close-share class="w-9 h-9 flex items-center justify-center rounded-sm text-zinc-400 hover:text-white hover:bg-zinc-800" aria-label="Fechar">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            <?php if ($cardSupported): ?>
                <div class="relative aspect-[1200/630] bg-zinc-950 border-2 border-zinc-800 rounded-sm overflow-hidden">
                    <div id="shareReviewLoading" class="absolute inset-0 flex items-center justify-center text-zinc-600 text-sm font-bold uppercase tracking-widest">Gerando prévia…</div>
                    <img id="shareReviewImg" data-src="<?php echo htmlspecialchars($yearCardUrl); ?>" alt="Prévia do card da retrospectiva" class="relative w-full h-full object-cover opacity-0 transition-opacity duration-300">
                </div>
                <p class="text-xs text-zinc-500">É assim que o link aparece no WhatsApp, Discord e redes sociais.</p>
            <?php endif; ?>
            <div class="flex gap-2">
                <input id="shareReviewUrl" type="text" readonly value="<?php echo htmlspecialchars($shareUrl); ?>" aria-label="Link da retrospectiva" class="flex-1 min-w-0 bg-zinc-950 border-2 border-zinc-800 text-zinc-300 text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-violet-500">
                <button type="button" id="copyReviewUrl" class="btn btn-primary shrink-0 h-auto">Copiar link</button>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="nativeShareReview" class="btn btn-secondary hidden">Compartilhar…</button>
                <?php if ($cardSupported): ?>
                    <a href="<?php echo htmlspecialchars($yearCardUrl); ?>" download="retrospectiva-<?php echo $year; ?>-<?php echo htmlspecialchars($profileUser['username']); ?>.jpg" class="btn btn-secondary">Baixar imagem</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const btn = document.getElementById('shareReview');
    const modal = document.getElementById('shareReviewModal');
    if (!btn || !modal) return;
    const urlInput = document.getElementById('shareReviewUrl');
    const copyBtn = document.getElementById('copyReviewUrl');
    const nativeBtn = document.getElementById('nativeShareReview');
    const img = document.getElementById('shareReviewImg');
    // APP_URL vazio no .env: usa o endereço da própria página
    if (!/^https?:/.test(urlInput.value)) urlInput.value = location.href;

    function open() {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        // A imagem só é pedida (e gerada no servidor) quando a janela abre
        if (img && !img.src) {
            img.addEventListener('load', () => {
                img.classList.remove('opacity-0');
                document.getElementById('shareReviewLoading')?.remove();
            }, { once: true });
            img.src = img.dataset.src;
        }
        copyBtn.focus();
    }
    function close() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        btn.focus();
    }

    btn.addEventListener('click', open);
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close-share]')) close(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

    copyBtn.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(urlInput.value);
        } catch (e) {
            urlInput.select();
            document.execCommand('copy');
        }
        copyBtn.textContent = 'Copiado!';
        setTimeout(() => { copyBtn.textContent = 'Copiar link'; }, 2000);
    });

    if (navigator.share) {
        nativeBtn.classList.remove('hidden');
        nativeBtn.addEventListener('click', async () => {
            const data = { title: btn.dataset.title || document.title, url: urlInput.value };
            // No celular, manda junto a imagem do card quando o navegador aceita arquivos
            try {
                if (img && img.src && navigator.canShare) {
                    const blob = await (await fetch(img.src)).blob();
                    const file = new File([blob], 'retrospectiva.jpg', { type: 'image/jpeg' });
                    if (navigator.canShare({ files: [file] })) data.files = [file];
                }
                await navigator.share(data);
            } catch (e) { /* cancelado */ }
        });
    }
})();
</script>
</body>
</html>
