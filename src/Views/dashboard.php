<?php
    $extraStyles = ['assets/css/dashboard.css'];
    $pageTitle = 'Dashboard - MyGameLibrary';
    require_once __DIR__ . '/header.php';

    $period = $period ?? 'week';
    $periodOptions = $periodOptions ?? ['week' => 'Semanal', 'month' => 'Mensal', 'year' => 'Anual'];
    $dash = $dash ?? [];

    $range = $dash['range'] ?? ['start' => date('Y-m-d'), 'end' => date('Y-m-d'), 'prev' => null, 'next' => null];
    $entries = $dash['entries'] ?? [];
    $buckets = $dash['buckets'] ?? [];
    $totalCompleted = (int) ($dash['total_completed'] ?? 0);
    $avgRating = $dash['avg_rating'] ?? null;
    $library = $dash['library'] ?? [];
    $libraryTotal = (int) ($dash['library_total'] ?? 0);
    $genres = $dash['genres'] ?? [];
    $playing = $dash['playing'] ?? [];
    $previous = $dash['previous'] ?? ['completed' => 0, 'hours' => 0, 'platinums' => 0];
    $username = $_SESSION['username'] ?? '';
    $statuses = mgl_statuses();

    // 30 -> "30", 12.5 -> "12,5"
    $fmtHours = function ($hours): string {
        $text = number_format((float) $hours, 1, ',', '.');
        return preg_replace('/,0$/', '', $text);
    };

    $periodWord = ['week' => 'semana', 'month' => 'mês', 'year' => 'ano'][$period];
    $prevWord = ['week' => 'semana anterior', 'month' => 'mês anterior', 'year' => 'ano anterior'][$period];

    // Título do período: "16 a 22 de novembro", "novembro de 2026" ou "2026"
    $startTs = strtotime($range['start']);
    if ($period === 'year') {
        $rangeLabel = date('Y', $startTs);
        $rangeSub = null;
    } elseif ($period === 'month') {
        $rangeLabel = mgl_month_pt((int) date('n', $startTs)) . ' de ' . date('Y', $startTs);
        $rangeSub = null;
    } else {
        $rangeLabel = mgl_date_range_pt($range['start'], $range['end']);
        $rangeSub = 'semana ' . (int) date('W', $startTs);
    }

    $navUrl = fn(string $p, ?string $d = null): string => 'index.php?' . http_build_query(array_filter(['action' => 'dashboard', 'period' => $p, 'd' => $d]));

    // "+1 vs. semana anterior" / "−2 vs. ..." / "igual à semana anterior"
    $delta = function (float $now, float $before, bool $isHours = false) use ($prevWord, $fmtHours): string {
        $diff = $now - $before;
        if (abs($diff) < 0.05) {
            return '<span class="text-zinc-500">igual ' . ($prevWord === 'mês anterior' ? 'ao' : 'à') . ' ' . $prevWord . '</span>';
        }
        $text = ($diff > 0 ? '+' : '−') . ($isHours ? $fmtHours(abs($diff)) . ' h' : (string) abs((int) round($diff)));
        $class = $diff > 0 ? 'text-emerald-400' : 'text-red-400';
        return '<b class="' . $class . ' font-extrabold">' . $text . '</b> vs. ' . $prevWord;
    };

    // Ritmo: altura de cada barra pelas horas (ou pela quantidade, se não houver horas registradas)
    $maxHours = 0.0;
    $maxCount = 0;
    foreach ($buckets as $b) {
        $maxHours = max($maxHours, $b['hours']);
        $maxCount = max($maxCount, count($b['games']));
    }
    $barArea = 232;                         // px da área do gráfico
    $withCovers = $period !== 'month';     // no mensal são ~30 barras finas demais para capa
    $minBar = $withCovers ? 140 : 24;       // altura mínima de uma barra com jogo (cabe a capa)
    $barRatio = fn(array $b): float => $maxHours > 0 ? $b['hours'] / $maxHours : count($b['games']) / max(1, $maxCount);
    $barHeight = function (array $b) use ($barRatio, $barArea, $minBar): int {
        if (empty($b['games'])) return 4;
        return (int) max($minBar, round($barRatio($b) * $barArea));
    };
    // Com capa: a capa aparece inteira (3:4) e só a faixa listrada embaixo varia com as horas
    $stripeHeight = fn(array $b): int => (int) max(14, round($barRatio($b) * 130));
    $bucketCount = count($buckets);

    // Mini gráfico dos blocos de números (zerados e horas por balde)
    $sparkCount = array_map(fn($b) => count($b['games']), array_values($buckets));
    $sparkHours = array_map(fn($b) => $b['hours'], array_values($buckets));
    if ($period === 'month') {
        // ~30 barrinhas não cabem num espaço tão pequeno: no mensal, uma barra por semana do mês
        $sparkCount = array_map('array_sum', array_chunk($sparkCount, 7));
        $sparkHours = array_map('array_sum', array_chunk($sparkHours, 7));
    }
    $renderSpark = function (array $values, string $color) {
        $max = max($values ?: [0]);
        if ($max <= 0) return '';
        $out = '<div class="dash-spark' . (count($values) > 14 ? ' is-dense' : '') . '" aria-hidden="true">';
        foreach ($values as $v) {
            $h = $v > 0 ? max(14, round($v / $max * 100)) : 8;
            $out .= '<i style="height:' . $h . '%;' . ($v > 0 ? 'background:' . $color : '') . '"></i>';
        }
        return $out . '</div>';
    };

    // Dias sem nenhum jogo zerado só viram linha no diário quando o buraco for grande o bastante
    $gapThreshold = ['week' => 1, 'month' => 4][$period] ?? null;
    $dayAfter = fn(string $d): string => (new DateTime($d))->modify('+1 day')->format('Y-m-d');
    $dayBefore = fn(string $d): string => (new DateTime($d))->modify('-1 day')->format('Y-m-d');
    $daysBetween = fn(string $a, string $b): int => (int) (new DateTime($a))->diff(new DateTime($b))->days;
    $today = date('Y-m-d');
    $renderGap = function (string $from, string $to) use ($gapThreshold, $daysBetween, $today): void {
        if ($to > $today) $to = $today;     // não fala de dias que ainda não chegaram
        if ($gapThreshold === null || $from > $to) return;
        if ($daysBetween($from, $to) + 1 < $gapThreshold) return;
        echo '<div class="dash-gap">' . htmlspecialchars(mgl_date_range_pt($from, $to)) . ' · nada zerado</div>';
    };

    $gameUrl = fn($gameId) => mgl_url('details', ['id' => (int) $gameId, 'u' => $username]);
    $kpiLabel = ['week' => 'Zerados na semana', 'month' => 'Zerados no mês', 'year' => 'Zerados no ano'][$period];
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>

    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6">
        <!-- Topo: título, período e navegação -->
        <header class="flex flex-col sm:flex-row sm:items-end justify-between gap-5 mb-6">
            <div>
                <p class="text-[11px] font-black uppercase tracking-[0.2em] text-violet-400">Seu ritmo</p>
                <h1 class="text-4xl sm:text-5xl font-black text-white uppercase tracking-tighter leading-none">Dashboard</h1>
                <!-- Setas e calendário juntos à esquerda: não mudam de lugar com o tamanho do texto -->
                <div class="mt-3 flex items-center gap-3">
                    <div class="relative flex items-center gap-1">
                        <?php if (!empty($dash['has_prev'])): ?>
                            <a href="<?php echo htmlspecialchars($navUrl($period, $range['prev'])); ?>" class="dash-navbtn" aria-label="<?php echo ucfirst($prevWord); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-3.5 h-3.5"><path stroke-linecap="square" d="M15 18l-6-6 6-6"/></svg></a>
                        <?php else: ?>
                            <span class="dash-navbtn is-off" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-3.5 h-3.5"><path stroke-linecap="square" d="M15 18l-6-6 6-6"/></svg></span>
                        <?php endif; ?>
                        <?php if (!empty($dash['has_next'])): ?>
                            <a href="<?php echo htmlspecialchars($navUrl($period, $range['next'])); ?>" class="dash-navbtn" aria-label="Próximo período"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-3.5 h-3.5"><path stroke-linecap="square" d="M9 6l6 6-6 6"/></svg></a>
                        <?php else: ?>
                            <span class="dash-navbtn is-off" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-3.5 h-3.5"><path stroke-linecap="square" d="M9 6l6 6-6 6"/></svg></span>
                        <?php endif; ?>
                        <button type="button" id="dashCalBtn" class="dash-navbtn ml-1" aria-haspopup="dialog" aria-expanded="false" aria-controls="dashCal" aria-label="Escolher período no calendário">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" class="w-4 h-4"><rect x="3.5" y="5" width="17" height="15.5" stroke-linejoin="miter"/><path stroke-linecap="square" d="M3.5 10h17M8 3v4M16 3v4"/></svg>
                        </button>

                        <!-- Calendário (montado pelo script no fim da página) -->
                        <div id="dashCal" class="dash-cal" role="dialog" aria-label="Escolher período" hidden></div>
                    </div>
                    <span class="text-base font-extrabold text-white"><?php echo htmlspecialchars($rangeLabel); ?></span>
                    <?php if ($rangeSub): ?><span class="text-xs text-zinc-500">· <?php echo htmlspecialchars($rangeSub); ?></span><?php endif; ?>
                </div>
            </div>
            <nav class="seg w-full sm:w-auto self-start sm:self-auto" aria-label="Período">
                <?php foreach ($periodOptions as $key => $label): ?>
                    <a href="<?php echo htmlspecialchars($navUrl($key, $dash['ref'] ?? null)); ?>"
                       class="seg-item !px-2 sm:!px-5 !text-xs <?php echo $key === $period ? 'is-on' : ''; ?>"
                       <?php echo $key === $period ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </header>

        <!-- Números -->
        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4" aria-label="Resumo do período">
            <div class="dash-kpi" style="--c:#10b981">
                <div class="min-w-0">
                    <p class="dash-cap"><?php echo $kpiLabel; ?></p>
                    <p class="dash-num"><?php echo $totalCompleted; ?></p>
                    <p class="dash-sub"><?php echo $delta($totalCompleted, $previous['completed']); ?></p>
                </div>
                <?php echo $renderSpark($sparkCount, '#10b981'); ?>
            </div>
            <div class="dash-kpi" style="--c:#8b5cf6">
                <div class="min-w-0">
                    <p class="dash-cap">Horas jogadas</p>
                    <p class="dash-num"><?php echo $fmtHours($dash['total_hours'] ?? 0); ?><small>h</small></p>
                    <p class="dash-sub">
                        <?php if ($totalCompleted > 1): ?>média de <b class="text-white font-extrabold"><?php echo $fmtHours($dash['avg_hours'] ?? 0); ?> h</b> por jogo
                        <?php else: ?><?php echo $delta((float) ($dash['total_hours'] ?? 0), $previous['hours'], true); ?><?php endif; ?>
                    </p>
                </div>
                <?php echo $renderSpark($sparkHours, '#8b5cf6'); ?>
            </div>
            <div class="dash-kpi" style="--c:#fbbf24">
                <div class="min-w-0">
                    <p class="dash-cap">Nota média</p>
                    <?php if ($avgRating !== null): $stars = (int) round($avgRating); ?>
                        <p class="dash-num"><?php echo number_format((float) $avgRating, 1, ',', '.'); ?><small>/5</small></p>
                        <p class="dash-sub"><span class="text-amber-400"><?php echo str_repeat('★', $stars); ?></span><span class="text-zinc-700"><?php echo str_repeat('★', 5 - $stars); ?></span> em <?php echo (int) $dash['rating_count']; ?> <?php echo (int) $dash['rating_count'] === 1 ? 'nota' : 'notas'; ?></p>
                    <?php else: ?>
                        <p class="dash-num text-zinc-700">—</p>
                        <p class="dash-sub">nenhuma nota no período</p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dash-kpi" style="--c:#f59e0b">
                <div class="min-w-0">
                    <p class="dash-cap">Platinas</p>
                    <p class="dash-num"><?php echo (int) ($dash['platinums'] ?? 0); ?></p>
                    <p class="dash-sub"><?php echo $delta((int) ($dash['platinums'] ?? 0), $previous['platinums']); ?></p>
                </div>
            </div>
        </section>

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_24rem] items-start">
            <div class="grid gap-5 min-w-0">
                <!-- Ritmo -->
                <section class="dash-panel" aria-labelledby="rhythmTitle">
                    <div class="dash-ph">
                        <h2 id="rhythmTitle" class="dash-h2">Ritmo <?php echo $period === 'week' ? 'da semana' : ($period === 'month' ? 'do mês' : 'do ano'); ?></h2>
                        <div class="hidden sm:flex items-center gap-4 text-xs text-zinc-400">
                            <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 bg-emerald-500"></i>horas em jogos zerados</span>
                            <span class="flex items-center gap-1.5"><i class="w-2.5 h-2.5 bg-zinc-800"></i>nada zerado</span>
                        </div>
                    </div>
                    <div class="p-4 sm:p-5">
                        <?php if ($totalCompleted === 0): ?>
                            <p class="text-sm text-zinc-500 py-10 text-center">Nada zerado <?php echo $period === 'month' ? 'neste' : 'nesta'; ?> <?php echo $periodWord; ?>. Quando você marcar um jogo como Zerado com a data de conclusão, ele aparece aqui.</p>
                        <?php else: ?>
                            <div class="dash-chart dash-chart--<?php echo $period; ?>" style="--cols: <?php echo $bucketCount; ?>">
                                <?php $i = 0; foreach ($buckets as $key => $b): $i++;
                                    $ts = strtotime($b['date']);
                                    $has = !empty($b['games']);
                                    $tipSide = $i > $bucketCount * 0.6 ? 'right-0' : ($i <= $bucketCount * 0.4 ? 'left-0' : 'left-1/2 -translate-x-1/2');
                                    // Capas de todos os jogos do dia/mês, inteiras, em mosaico (mais horas primeiro)
                                    $coverGames = [];
                                    if ($has && $withCovers) {
                                        $coverGames = $b['games'];
                                        usort($coverGames, fn($x, $y) => (float) $y['time_spent_hours'] <=> (float) $x['time_spent_hours']);
                                    }
                                    $mosaicCols = count($coverGames) > 1 ? 2 : 1;
                                    if ($period === 'year') {
                                        $lb = mb_substr(mgl_month_pt((int) date('n', $ts)), 0, 3);
                                        $num = null;
                                    } else {
                                        $lb = $period === 'week' ? mgl_weekday_short_pt((int) date('w', $ts)) : null;
                                        $num = (int) date('j', $ts);
                                    }
                                    $showNum = $period !== 'month' || $has || $num === 1 || $num % 5 === 0;
                                ?>
                                    <div class="dash-col">
                                        <?php if ($has): ?>
                                            <span class="dash-val"><?php echo $b['hours'] > 0 ? $fmtHours($b['hours']) . ' h' : count($b['games']) . (count($b['games']) === 1 ? ' jogo' : ' jogos'); ?></span>
                                        <?php endif; ?>
                                        <!-- Só a barra ativa a caixinha (e ela aparece colada no topo da barra) -->
                                        <div class="dash-hit"<?php echo $has ? ' tabindex="0"' : ''; ?>>
                                            <div class="dash-bar<?php echo $has ? ' is-on' : ''; ?><?php echo $b['future'] ? ' is-future' : ''; ?>" style="<?php echo $coverGames ? '' : 'height: ' . $barHeight($b) . 'px'; ?>">
                                                <?php if ($coverGames): ?>
                                                    <div class="dash-bar__covers" style="grid-template-columns: repeat(<?php echo $mosaicCols; ?>, minmax(0, 1fr))">
                                                        <?php foreach ($coverGames as $cg): ?>
                                                            <?php if (!empty($cg['cover_image'])): ?>
                                                                <img src="<?php echo htmlspecialchars(mgl_cover_thumb($cg['cover_image'])); ?>" alt="" loading="lazy">
                                                            <?php else: ?>
                                                                <?php $initials = mb_strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/u', trim(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $cg['title']))), 0, 3)))); ?>
                                                                <span><?php echo htmlspecialchars($initials); ?></span>
                                                            <?php endif; ?>
                                                        <?php endforeach; ?>
                                                        <?php if ($mosaicCols === 2 && count($coverGames) % 2 === 1): ?><i class="dash-bar__fill"></i><?php endif; ?>
                                                    </div>
                                                    <div class="dash-bar__stripe" style="height: <?php echo $stripeHeight($b); ?>px"></div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($has): ?>
                                                <!-- Jogos do período ao passar o mouse -->
                                                <div class="dash-tip <?php echo $tipSide; ?>">
                                                    <p class="dash-tip__head">
                                                        <?php echo $period === 'year'
                                                            ? htmlspecialchars(mgl_month_pt((int) date('n', $ts)))
                                                            : (int) date('j', $ts) . ' de ' . htmlspecialchars(mgl_month_pt((int) date('n', $ts))); ?>
                                                        · <?php echo count($b['games']); ?> <?php echo count($b['games']) === 1 ? 'zerado' : 'zerados'; ?>
                                                    </p>
                                                    <?php foreach (array_slice($b['games'], 0, 6) as $tg): $r = (int) $tg['rating']; ?>
                                                        <p class="dash-tip__row">
                                                            <b><?php echo htmlspecialchars($tg['title']); ?></b>
                                                            <span><?php echo $r ? '<i class="text-amber-400 not-italic">' . str_repeat('★', $r) . '</i> · ' : ''; ?><?php echo $tg['time_spent_hours'] !== null ? $fmtHours($tg['time_spent_hours']) . ' h' : 'sem horas'; ?></span>
                                                        </p>
                                                    <?php endforeach; ?>
                                                    <?php if (count($b['games']) > 6): ?><p class="text-[11px] text-zinc-500 mt-1">e mais <?php echo count($b['games']) - 6; ?></p><?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="dash-lbl<?php echo $has ? ' is-on' : ''; ?>">
                                            <?php if ($lb): ?><span><?php echo htmlspecialchars($lb); ?></span><?php endif; ?>
                                            <?php if ($num !== null): ?><b class="<?php echo $showNum ? '' : 'max-sm:invisible'; ?>"><?php echo $num; ?></b><?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Diário -->
                <section class="dash-panel" aria-labelledby="diaryTitle">
                    <div class="dash-ph">
                        <h2 id="diaryTitle" class="dash-h2">Diário</h2>
                        <span class="dash-cap !mb-0"><?php echo $totalCompleted; ?> <?php echo $totalCompleted === 1 ? 'zerado' : 'zerados'; ?></span>
                    </div>
                    <?php if (empty($entries)): ?>
                        <p class="text-sm text-zinc-500 p-6 text-center">Nenhum jogo zerado neste período.</p>
                    <?php else: ?>
                        <?php
                            $prevDate = $dayAfter($range['end']);
                            $prevMonth = null;
                            foreach ($entries as $entry):
                                $date = substr($entry['completion_date'], 0, 10);
                                $ts = strtotime($date);
                                $renderGap($dayAfter($date), $dayBefore($prevDate));
                                $prevDate = $date;
                                if ($period === 'year') {
                                    $monthKey = date('Y-m', $ts);
                                    if ($monthKey !== $prevMonth) {
                                        echo '<p class="dash-month">' . htmlspecialchars(mgl_month_pt((int) date('n', $ts))) . '</p>';
                                        $prevMonth = $monthKey;
                                    }
                                }
                                $rating = ($entry['rating'] !== null && $entry['rating'] !== '') ? max(0, min(5, (int) round((float) $entry['rating']))) : null;
                                $genreNames = array_values(array_filter(array_map(
                                    fn($g) => mb_strtolower(trim($g)) === 'desconhecido' ? '' : mgl_genre_pt($g),
                                    explode(',', (string) $entry['genre'])
                                )));
                        ?>
                            <article class="dash-row">
                                <div class="dash-date"><b><?php echo (int) date('j', $ts); ?></b><span><?php echo mgl_weekday_short_pt((int) date('w', $ts)); ?></span></div>
                                <a href="<?php echo htmlspecialchars($gameUrl($entry['game_id'])); ?>" class="dash-cover" tabindex="-1" aria-hidden="true">
                                    <?php if (!empty($entry['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars(mgl_cover_thumb($entry['cover_image'])); ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <span><?php echo htmlspecialchars(mb_substr($entry['title'], 0, 1)); ?></span>
                                    <?php endif; ?>
                                </a>
                                <div class="min-w-0">
                                    <a href="<?php echo htmlspecialchars($gameUrl($entry['game_id'])); ?>" class="dash-game">
                                        <?php echo htmlspecialchars($entry['title']); ?><?php if (!empty($entry['platinum_at'])): ?> <span title="Platinado">🏆</span><?php endif; ?>
                                    </a>
                                    <?php if ($genreNames): ?>
                                        <div class="mt-1.5 flex flex-wrap gap-1.5">
                                            <?php foreach (array_slice($genreNames, 0, 3) as $gn): ?>
                                                <span class="dash-chip"><?php echo htmlspecialchars($gn); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="dash-stars" aria-label="<?php echo $rating !== null ? "Nota $rating de 5" : 'Sem nota'; ?>">
                                    <?php if ($rating !== null): ?><span class="text-amber-400"><?php echo str_repeat('★', $rating); ?></span><span class="text-zinc-700"><?php echo str_repeat('★', 5 - $rating); ?></span><?php else: ?><span class="text-zinc-700">—</span><?php endif; ?>
                                </div>
                                <div class="dash-hrs"><?php echo $entry['time_spent_hours'] !== null ? $fmtHours($entry['time_spent_hours']) . ' h' : '—'; ?><small>jogadas</small></div>
                            </article>
                        <?php endforeach; ?>
                        <?php $renderGap($range['start'], $dayBefore($prevDate)); ?>
                        <?php if (!empty($dash['entries_truncated'])): ?>
                            <p class="text-xs text-zinc-500 px-5 py-3">Mostrando os 100 jogos mais recentes do período. Os números acima consideram todos.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </section>
            </div>

            <aside class="grid gap-5">
                <!-- Biblioteca -->
                <section class="dash-panel" aria-labelledby="libTitle">
                    <div class="dash-ph">
                        <h2 id="libTitle" class="dash-h2">Sua biblioteca</h2>
                        <span class="dash-cap !mb-0"><?php echo $libraryTotal; ?> <?php echo $libraryTotal === 1 ? 'jogo' : 'jogos'; ?></span>
                    </div>
                    <div class="p-5">
                        <?php if ($libraryTotal > 0): ?>
                            <div class="flex h-4 border-2 border-zinc-800" role="img" aria-label="Biblioteca por status">
                                <?php foreach ($library as $row): ?>
                                    <i class="block h-full" style="width: <?php echo round($row['total'] / $libraryTotal * 100, 2); ?>%; background: <?php echo $statuses[$row['status']]['hex'] ?? '#a78bfa'; ?>"></i>
                                <?php endforeach; ?>
                            </div>
                            <ul class="mt-3">
                                <?php foreach ($library as $row): ?>
                                    <li class="grid grid-cols-[0.75rem_1fr_auto_2.5rem] items-center gap-2.5 py-2 border-b border-zinc-800 last:border-b-0 text-sm">
                                        <i class="w-3 h-3" style="background: <?php echo $statuses[$row['status']]['hex'] ?? '#a78bfa'; ?>"></i>
                                        <a href="index.php?action=home&amp;filter_status=<?php echo urlencode($row['status']); ?>" class="text-zinc-300 hover:text-white"><?php echo htmlspecialchars($row['status']); ?></a>
                                        <b class="text-white font-black"><?php echo (int) $row['total']; ?></b>
                                        <span class="text-xs text-zinc-500 text-right"><?php echo round($row['total'] / $libraryTotal * 100); ?>%</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                            <?php if (!empty($dash['backlog_hours'])): ?>
                                <p class="mt-4 border-2 border-dashed border-zinc-700 p-3 text-[13px] text-zinc-400 leading-relaxed">
                                    No seu ritmo médio (<b class="text-white"><?php echo $fmtHours(round($dash['avg_hours_all_time'])); ?> h por jogo</b>), o backlog tem uns <b class="text-white"><?php echo number_format(round($dash['backlog_hours'] / 5) * 5, 0, ',', '.'); ?> h</b> de jogo pela frente.
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="text-sm text-zinc-500">Sua biblioteca ainda está vazia.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <!-- Jogando agora -->
                <?php if ($playing): ?>
                    <section class="dash-panel" aria-labelledby="playingTitle">
                        <div class="dash-ph"><h2 id="playingTitle" class="dash-h2">Jogando agora</h2></div>
                        <ul class="p-5 grid gap-3">
                            <?php foreach ($playing as $pg): ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars($gameUrl($pg['game_id'])); ?>" class="flex items-center gap-3 group">
                                        <span class="w-11 h-[58px] shrink-0 border-2 border-zinc-800 bg-zinc-800 overflow-hidden">
                                            <?php if (!empty($pg['cover_image'])): ?><img src="<?php echo htmlspecialchars(mgl_cover_thumb($pg['cover_image'])); ?>" alt="" loading="lazy" class="w-full h-full object-cover"><?php endif; ?>
                                        </span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block text-sm font-extrabold text-white truncate group-hover:text-violet-300"><?php echo htmlspecialchars($pg['title']); ?></span>
                                            <?php if (!empty($pg['since'])): $days = (int) floor((time() - strtotime($pg['since'])) / 86400); ?>
                                                <span class="block text-xs text-zinc-500 mt-0.5"><?php echo $days <= 0 ? 'começou hoje' : 'jogando há ' . $days . ($days === 1 ? ' dia' : ' dias'); ?></span>
                                            <?php endif; ?>
                                        </span>
                                        <span class="text-[10px] font-black uppercase tracking-wider px-1.5 py-0.5 bg-blue-600 text-white">Jogando</span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>

                <!-- Gêneros -->
                <?php if ($genres): ?>
                    <section class="dash-panel" aria-labelledby="genTitle">
                        <div class="dash-ph"><h2 id="genTitle" class="dash-h2">Gêneros <?php echo $period === 'month' ? 'do mês' : ($period === 'year' ? 'do ano' : 'da semana'); ?></h2></div>
                        <ul class="p-5 grid gap-2">
                            <?php $maxGenre = max(array_column($genres, 'total')); ?>
                            <?php foreach ($genres as $genre): ?>
                                <li class="grid grid-cols-[7.5rem_1fr_1.5rem] items-center gap-2.5 text-[13px]">
                                    <span class="text-zinc-300 truncate"><?php echo htmlspecialchars($genre['name']); ?></span>
                                    <span class="h-2.5 bg-zinc-800"><i class="block h-full bg-violet-500" style="width: <?php echo max(6, round($genre['total'] / $maxGenre * 100)); ?>%"></i></span>
                                    <b class="text-white text-right"><?php echo (int) $genre['total']; ?></b>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </aside>
        </div>

        <?php require __DIR__ . '/partials/igdb_credit.php'; ?>
    </main>

    <script>
    // Calendário do dashboard: escolher semana, mês ou ano sem ir de seta em seta.
    // Períodos com jogos zerados ficam marcados; datas futuras e antes da primeira atividade ficam desligadas.
    (function () {
        const btn = document.getElementById('dashCalBtn');
        const box = document.getElementById('dashCal');
        if (!btn || !box) return;

        const period = <?php echo json_encode($period); ?>;
        const ref = <?php echo json_encode($dash['ref'] ?? date('Y-m-d')); ?>;
        const today = <?php echo json_encode(date('Y-m-d')); ?>;
        const firstDay = <?php echo json_encode($dash['first_day'] ?? date('Y-m-d')); ?>;
        const days = <?php echo json_encode((object) ($dash['completion_days'] ?? [])); ?>;
        const months = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
        const wk = ['seg','ter','qua','qui','sex','sáb','dom'];

        const pad = (n) => String(n).padStart(2, '0');
        const ymd = (d) => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
        const parse = (s) => { const [y, m, d] = s.split('-').map(Number); return new Date(y, m - 1, d); };
        const url = (d) => 'index.php?action=dashboard&period=' + period + '&d=' + d;
        const count = (prefix) => Object.keys(days).reduce((t, k) => t + (k.startsWith(prefix) ? days[k] : 0), 0);
        const firstYear = Number(firstDay.slice(0, 4));
        const thisYear = Number(today.slice(0, 4));

        let viewYear = Number(ref.slice(0, 4));
        let viewMonth = Number(ref.slice(5, 7)) - 1;

        function head(label, canPrev, canNext) {
            return '<div class="dash-cal__head">'
                + '<button type="button" data-step="-1" ' + (canPrev ? '' : 'disabled') + ' aria-label="Anterior">‹</button>'
                + '<b>' + label + '</b>'
                + '<button type="button" data-step="1" ' + (canNext ? '' : 'disabled') + ' aria-label="Próximo">›</button></div>';
        }

        function render() {
            let html = '';
            if (period === 'year') {
                html += '<p class="dash-cal__title">Escolha o ano</p><div class="dash-cal__grid dash-cal__grid--3">';
                for (let y = thisYear; y >= firstYear; y--) {
                    const n = count(y + '-');
                    const cur = Number(ref.slice(0, 4)) === y;
                    html += '<a href="' + url(y + '-01-01') + '" class="dash-cal__cell' + (cur ? ' is-cur' : '') + '">' + y
                        + (n ? '<small>' + n + ' zerado' + (n > 1 ? 's' : '') + '</small>' : '<small class="is-none">—</small>') + '</a>';
                }
                html += '</div>';
            } else if (period === 'month') {
                html += head(String(viewYear), viewYear > firstYear, viewYear < thisYear) + '<div class="dash-cal__grid dash-cal__grid--3">';
                for (let m = 0; m < 12; m++) {
                    const key = viewYear + '-' + pad(m + 1);
                    const off = key > today.slice(0, 7) || key < firstDay.slice(0, 7);
                    const cur = key === ref.slice(0, 7);
                    const n = count(key + '-');
                    const label = months[m].slice(0, 3);
                    html += off
                        ? '<span class="dash-cal__cell is-off">' + label + '</span>'
                        : '<a href="' + url(key + '-01') + '" class="dash-cal__cell' + (cur ? ' is-cur' : '') + '">' + label
                          + (n ? '<small>' + n + '</small>' : '') + '</a>';
                }
                html += '</div>';
            } else {
                // Semana: calendário do mês, uma linha por semana (segunda a domingo)
                const first = new Date(viewYear, viewMonth, 1);
                const last = new Date(viewYear, viewMonth + 1, 0);
                const canPrev = (viewYear * 12 + viewMonth) > (firstYear * 12 + Number(firstDay.slice(5, 7)) - 1);
                const canNext = (viewYear * 12 + viewMonth) < (thisYear * 12 + Number(today.slice(5, 7)) - 1);
                html += head(months[viewMonth] + ' ' + viewYear, canPrev, canNext);
                html += '<div class="dash-cal__week dash-cal__week--head">' + wk.map((d) => '<span>' + d + '</span>').join('') + '</div>';
                let start = new Date(first);
                start.setDate(start.getDate() - ((start.getDay() + 6) % 7));   // volta até a segunda-feira
                const refMonday = (() => { const r = parse(ref); r.setDate(r.getDate() - ((r.getDay() + 6) % 7)); return ymd(r); })();
                while (start <= last) {
                    const monday = ymd(start);
                    const sunday = new Date(start); sunday.setDate(sunday.getDate() + 6);
                    const off = monday > today || ymd(sunday) < firstDay;
                    const cur = monday === refMonday;
                    let cells = '';
                    for (let i = 0; i < 7; i++) {
                        const d = new Date(start); d.setDate(d.getDate() + i);
                        const key = ymd(d);
                        const other = d.getMonth() !== viewMonth;
                        cells += '<span class="' + (other ? 'is-other ' : '') + (days[key] ? 'has-game' : '') + '">' + d.getDate() + '</span>';
                    }
                    html += off
                        ? '<span class="dash-cal__week is-off">' + cells + '</span>'
                        : '<a href="' + url(monday) + '" class="dash-cal__week' + (cur ? ' is-cur' : '') + '">' + cells + '</a>';
                    start.setDate(start.getDate() + 7);
                }
                html += '<p class="dash-cal__hint"><i></i> dia com jogo zerado · clique numa semana</p>';
            }
            box.innerHTML = html;
        }

        function open() {
            viewYear = Number(ref.slice(0, 4));
            viewMonth = Number(ref.slice(5, 7)) - 1;
            render();
            box.hidden = false;
            btn.setAttribute('aria-expanded', 'true');
            btn.classList.add('is-active');
        }
        function close() {
            box.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
            btn.classList.remove('is-active');
        }

        btn.addEventListener('click', (e) => { e.stopPropagation(); box.hidden ? open() : close(); });
        box.addEventListener('click', (e) => {
            e.stopPropagation();
            const step = e.target.closest('[data-step]');
            if (!step || step.disabled) return;
            const dir = Number(step.dataset.step);
            if (period === 'month') {
                viewYear += dir;
            } else {
                viewMonth += dir;
                if (viewMonth < 0) { viewMonth = 11; viewYear--; }
                if (viewMonth > 11) { viewMonth = 0; viewYear++; }
            }
            render();
        });
        document.addEventListener('click', () => { if (!box.hidden) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !box.hidden) { close(); btn.focus(); } });
    })();
    </script>
    <script src="<?php echo mgl_asset('assets/js/notifications.js'); ?>"></script>
</body>
</html>
