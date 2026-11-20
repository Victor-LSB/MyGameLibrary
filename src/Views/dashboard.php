<?php
    $extraStyles = ['assets/css/dashboard.css'];
    require_once __DIR__ . '/header.php';

    $period = $period ?? 'week';
    $periodOptions = $periodOptions ?? ['week' => 'Semanal', 'month' => 'Mensal', 'year' => 'Anual'];
    $dash = $dash ?? [];

    $range = $dash['range'] ?? ['start' => date('Y-m-d'), 'end' => date('Y-m-d')];
    $entries = $dash['entries'] ?? [];
    $totalCompleted = (int) ($dash['total_completed'] ?? 0);
    $avgRating = $dash['avg_rating'] ?? null;
    $library = $dash['library'] ?? [];
    $libraryTotal = (int) ($dash['library_total'] ?? 0);
    $genres = $dash['genres'] ?? [];
    $username = $_SESSION['username'] ?? '';

    $completedLabel = [
        'week'  => 'Zerados na semana',
        'month' => 'Zerados no mês',
        'year'  => 'Zerados no ano',
    ][$period] ?? 'Zerados no período';

    // 30 -> "30", 12.5 -> "12,5"
    $fmtHours = function ($hours): string {
        $text = number_format((float) $hours, 1, ',', '.');
        return preg_replace('/,0$/', '', $text);
    };

    $statusColors = ['Zerado' => '#34d399', 'Jogando' => '#60a5fa', 'Dropado' => '#f87171', 'Backlog' => '#71717a'];

    // Dias sem nenhum jogo zerado só viram linha no diário quando o buraco for grande o bastante
    $gapThreshold = ['week' => 1, 'month' => 7][$period] ?? null;

    $dayAfter = fn(string $d): string => (new DateTime($d))->modify('+1 day')->format('Y-m-d');
    $dayBefore = fn(string $d): string => (new DateTime($d))->modify('-1 day')->format('Y-m-d');
    $daysBetween = fn(string $a, string $b): int => (int) (new DateTime($a))->diff(new DateTime($b))->days;

    $renderGap = function (string $from, string $to) use ($gapThreshold, $daysBetween): void {
        if ($gapThreshold === null || $from > $to) return;
        if ($daysBetween($from, $to) + 1 < $gapThreshold) return;
        echo '<div class="dash-gap"><i></i><span>' . htmlspecialchars(mgl_date_range_pt($from, $to)) . ': nada zerado</span><i></i></div>';
    };
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
    <?php require_once __DIR__ . '/partials/navbar.php'; ?>

    <main class="dash">
        <header class="dash-head">
            <h1 class="dash-title">Dashboard</h1>
            <nav class="dash-tabs" aria-label="Período">
                <?php foreach ($periodOptions as $key => $label): ?>
                    <a href="index.php?action=dashboard&period=<?php echo urlencode($key); ?>"
                       class="dash-tab<?php echo $key === $period ? ' is-active' : ''; ?>"
                       <?php echo $key === $period ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($label); ?></a>
                <?php endforeach; ?>
            </nav>
        </header>

        <section class="dash-nums" aria-label="Resumo do período">
            <div>
                <p class="dash-nl"><?php echo $completedLabel; ?></p>
                <p class="dash-nv"><?php echo $totalCompleted; ?></p>
            </div>
            <div>
                <p class="dash-nl">Horas jogadas</p>
                <p class="dash-nv"><?php echo $fmtHours($dash['total_hours'] ?? 0); ?><small>h</small></p>
                <?php if ($totalCompleted > 1): ?>
                    <p class="dash-ns">média de <?php echo $fmtHours($dash['avg_hours'] ?? 0); ?> h por jogo</p>
                <?php endif; ?>
            </div>
            <div>
                <p class="dash-nl">Nota média</p>
                <?php if ($avgRating !== null): ?>
                    <p class="dash-nv"><?php echo number_format((float) $avgRating, 1, ',', '.'); ?><small>/5</small></p>
                <?php else: ?>
                    <p class="dash-nv dash-nv--empty">—</p>
                <?php endif; ?>
            </div>
        </section>

        <div class="dash-cols">
            <section aria-labelledby="diaryTitle">
                <?php
                    // No anual a janela é de 12 meses fechados, então o rótulo fala em meses
                    if ($period === 'year') {
                        $rangeLabel = mgl_month_pt((int) date('n', strtotime($range['start']))) . ' de ' . date('Y', strtotime($range['start']))
                            . ' a ' . mgl_month_pt((int) date('n', strtotime($range['end']))) . ' de ' . date('Y', strtotime($range['end']));
                    } else {
                        $rangeLabel = mgl_date_range_pt($range['start'], $range['end']);
                    }
                ?>
                <h2 id="diaryTitle" class="dash-sec">Diário · <?php echo htmlspecialchars($rangeLabel); ?></h2>

                <?php if (empty($entries)): ?>
                    <div class="dash-box dash-empty">
                        <strong>Nenhum jogo zerado neste período</strong>
                        Quando você marcar um jogo como zerado, com a data de conclusão, ele aparece aqui.
                    </div>
                <?php else: ?>
                    <div class="dash-box">
                        <?php
                            $prevDate = $dayAfter($range['end']);   // "cursor": até onde o diário já foi listado
                            $prevMonth = null;
                            foreach ($entries as $entry):
                                $date = substr($entry['completion_date'], 0, 10);
                                $ts = strtotime($date);

                                $renderGap($dayAfter($date), $dayBefore($prevDate));
                                $prevDate = $date;

                                if ($period !== 'week') {
                                    $monthKey = date('Y-m', $ts);
                                    if ($monthKey !== $prevMonth) {
                                        echo '<p class="dash-month">' . htmlspecialchars(mgl_month_pt((int) date('n', $ts)) . ' de ' . date('Y', $ts)) . '</p>';
                                        $prevMonth = $monthKey;
                                    }
                                }

                                $gameUrl = 'index.php?action=details&id=' . (int) $entry['game_id'] . '&u=' . urlencode($username);
                                $rating = ($entry['rating'] !== null && $entry['rating'] !== '') ? max(0, min(5, (int) round((float) $entry['rating']))) : null;
                                $genreNames = array_values(array_filter(array_map('mgl_genre_pt', explode(',', (string) $entry['genre']))));
                        ?>
                            <article class="dash-entry">
                                <div class="dash-date">
                                    <b><?php echo (int) date('j', $ts); ?></b>
                                    <span><?php echo mgl_weekday_short_pt((int) date('w', $ts)); ?>, <?php echo htmlspecialchars(mb_substr(mgl_month_pt((int) date('n', $ts)), 0, 3)); ?></span>
                                </div>

                                <a href="<?php echo htmlspecialchars($gameUrl); ?>" class="dash-cover<?php echo empty($entry['cover_image']) ? ' dash-cover--empty' : ''; ?>" tabindex="-1" aria-hidden="true">
                                    <?php if (!empty($entry['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($entry['cover_image']); ?>" alt="" loading="lazy">
                                    <?php else: ?>
                                        <?php echo htmlspecialchars(mb_substr($entry['title'], 0, 1)); ?>
                                    <?php endif; ?>
                                </a>

                                <div class="dash-body">
                                    <a href="<?php echo htmlspecialchars($gameUrl); ?>" class="dash-game"><?php echo htmlspecialchars($entry['title']); ?></a>
                                    <?php if (!empty($genreNames)): ?>
                                        <p class="dash-genres"><?php echo htmlspecialchars(implode(', ', $genreNames)); ?></p>
                                    <?php endif; ?>
                                    <?php if ($rating !== null): ?>
                                        <p class="dash-stars" aria-label="Nota <?php echo $rating; ?> de 5"><?php echo str_repeat('★', $rating); ?><span><?php echo str_repeat('★', 5 - $rating); ?></span></p>
                                    <?php endif; ?>
                                </div>

                                <?php if ($entry['time_spent_hours'] !== null): ?>
                                    <div class="dash-hours"><b><?php echo $fmtHours($entry['time_spent_hours']); ?> h</b><span>jogadas</span></div>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>

                        <?php
                            // Dias sem jogo zerado entre a última entrada e o começo do período
                            $renderGap($range['start'], $dayBefore($prevDate));
                        ?>
                    </div>
                    <?php if (!empty($dash['entries_truncated'])): ?>
                        <p class="dash-note">Mostrando os 100 jogos mais recentes do período. Os números acima consideram todos.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <aside>
                <section class="dash-block" aria-labelledby="libTitle">
                    <h2 id="libTitle" class="dash-sec">Sua biblioteca · <?php echo $libraryTotal; ?> jogo<?php echo $libraryTotal === 1 ? '' : 's'; ?></h2>
                    <?php if ($libraryTotal > 0): ?>
                        <?php foreach ($library as $row): ?>
                            <?php $width = max(2, round($row['total'] / $libraryTotal * 100)); ?>
                            <div class="dash-lib">
                                <div class="dash-lib__head"><span><?php echo htmlspecialchars($row['status']); ?></span><span><?php echo (int) $row['total']; ?></span></div>
                                <div class="dash-lib__track"><div style="width: <?php echo $width; ?>%; background: <?php echo $statusColors[$row['status']] ?? '#a78bfa'; ?>"></div></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="dash-note">Sua biblioteca ainda está vazia.</p>
                    <?php endif; ?>
                </section>

                <?php if ($totalCompleted >= 3 && !empty($genres)): ?>
                    <section class="dash-block" aria-labelledby="genTitle">
                        <h2 id="genTitle" class="dash-sec">Gêneros que você zerou</h2>
                        <?php $maxGenre = max(array_column($genres, 'total')); ?>
                        <?php foreach ($genres as $genre): ?>
                            <div class="dash-lib">
                                <div class="dash-lib__head"><span><?php echo htmlspecialchars($genre['name']); ?></span><span><?php echo (int) $genre['total']; ?></span></div>
                                <div class="dash-lib__track"><div style="width: <?php echo max(4, round($genre['total'] / $maxGenre * 100)); ?>%; background: #a78bfa"></div></div>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            </aside>
        </div>
    </main>

    <script src="<?php echo mgl_asset('assets/js/notifications.js'); ?>"></script>
<?php require __DIR__ . '/partials/igdb_credit.php'; ?>
</body>
</html>
