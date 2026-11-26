<?php
    $pageTitle = $game['title'] . ' - MyGameLibrary';
    // Mesma limpeza da página de detalhes: quebras de linha viram \n e o resto do HTML sai
    $descriptionDisplay = trim(preg_replace("/\n{3,}/", "\n\n", strip_tags(html_entity_decode(
        str_ireplace(['</p>', '<br>', '<br/>', '<br />'], "\n", (string) ($game['description'] ?? '')), ENT_QUOTES, 'UTF-8'))));
    $descriptionText = trim(preg_replace('/\s+/', ' ', $descriptionDisplay));
    $metaDescription = $descriptionText !== ''
        ? mb_strimwidth($descriptionText, 0, 180, '…')
        : 'Veja o que a comunidade do MyGameLibrary acha de ' . $game['title'] . '.';
    $ogImage = $game['cover_image'] ?? null;
    require __DIR__ . '/../header.php';

    $statuses = mgl_statuses();
    $owners = (int) ($stats['owners'] ?? 0);
    $avgRating = $stats['avg_rating'] !== null ? (float) $stats['avg_rating'] : null;
    $ratingCount = (int) ($stats['rating_count'] ?? 0);
    $byStatus = $stats['by_status'] ?? [];
    $distribution = $stats['rating_distribution'] ?? [];
    $maxDist = $distribution ? max($distribution) : 0;
    $avgHours = $stats['avg_hours'] !== null ? (float) $stats['avg_hours'] : null;
    $year = !empty($game['release_date']) ? substr($game['release_date'], 0, 4) : null;
    $genres = array_filter(array_map('trim', explode(',', (string) ($game['genre'] ?? ''))), fn($g) => $g !== '' && mb_strtolower($g) !== 'desconhecido');
    $isLoggedIn = isset($_SESSION['user_id']);
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
<?php require __DIR__ . '/../partials/navbar.php'; ?>

<main class="max-w-screen-2xl mx-auto px-4 sm:px-6">
    <?php if (!empty($_SESSION['search_error'])): ?>
        <div class="mb-6 bg-red-950/60 border border-red-800 text-red-300 px-4 py-3 rounded-sm text-sm"><?php echo htmlspecialchars($_SESSION['search_error']); unset($_SESSION['search_error']); ?></div>
    <?php endif; ?>

    <?php if (!empty($backUrl)): ?>
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="inline-flex items-center gap-2 mb-6 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 hover:text-white px-4 py-2.5 rounded-sm font-bold uppercase tracking-wide text-xs transition-colors">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
            <?php echo htmlspecialchars($backLabel); ?>
        </a>
    <?php endif; ?>

    <div class="grid gap-8 lg:grid-cols-12 items-start">
        <!-- Coluna da esquerda: capa, ação e números da comunidade (fixa ao rolar no desktop) -->
        <aside class="lg:col-span-4 xl:col-span-3 space-y-6 lg:sticky lg:top-6">
            <div class="w-48 sm:w-56 lg:w-full mx-auto bg-zinc-950 border-4 border-zinc-800 rounded-sm overflow-hidden shadow-xl aspect-[3/4]">
                <?php if (!empty($game['cover_image'])): ?>
                    <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="Capa de <?php echo htmlspecialchars($game['title']); ?>" class="w-full h-full object-cover">
                <?php else: ?>
                    <div class="w-full h-full flex items-center justify-center text-zinc-600 font-black uppercase text-xl text-center p-4">Sem capa</div>
                <?php endif; ?>
            </div>

            <!-- Ação do visitante -->
            <div>
                <?php if ($viewerEntry): ?>
                    <?php $vs = $viewerEntry['status'] ?? null; ?>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-4 space-y-3">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-xs font-bold uppercase tracking-widest text-zinc-500">Na sua biblioteca</span>
                            <?php if ($vs && isset($statuses[$vs])): ?>
                                <span class="text-[10px] font-black uppercase px-2 py-1 rounded-sm <?php echo $statuses[$vs]['badge']; ?>"><?php echo htmlspecialchars($vs); ?></span>
                            <?php endif; ?>
                        </div>
                        <a href="<?php echo htmlspecialchars(mgl_url('details', ['id' => (int) $game['id'], 'u' => $_SESSION['username'] ?? ''])); ?>" class="block text-center bg-zinc-800 hover:bg-zinc-700 text-white px-4 py-2.5 rounded-sm font-bold uppercase tracking-wide text-xs transition-colors">Ver / editar minha análise</a>
                    </div>
                <?php elseif ($isLoggedIn): ?>
                    <form action="index.php?action=add_game" method="post" class="grid gap-2">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                        <input type="hidden" name="external_id" value="<?php echo htmlspecialchars((string) $game['external_id']); ?>">
                        <input type="hidden" name="title" value="<?php echo htmlspecialchars($game['title']); ?>">
                        <input type="hidden" name="platform" value="<?php echo htmlspecialchars((string) ($game['platform'] ?? '')); ?>">
                        <input type="hidden" name="genre" value="<?php echo htmlspecialchars((string) ($game['genre'] ?? '')); ?>">
                        <input type="hidden" name="release_date" value="<?php echo htmlspecialchars((string) ($game['release_date'] ?? '')); ?>">
                        <input type="hidden" name="cover" value="<?php echo htmlspecialchars((string) ($game['cover_image'] ?? '')); ?>">
                        <input type="hidden" name="return_to_game" value="1">
                        <button type="submit" name="initial_status" value="Backlog" class="bg-violet-600 hover:bg-violet-500 text-white px-5 py-3 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors">+ Adicionar à biblioteca</button>
                        <button type="submit" name="initial_status" value="Quero jogar" class="bg-zinc-800 hover:bg-fuchsia-700 text-zinc-200 hover:text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-xs transition-colors">Quero jogar</button>
                    </form>
                <?php else: ?>
                    <a href="index.php?action=register" class="block text-center bg-violet-600 hover:bg-violet-500 text-white px-5 py-3 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors">Crie sua conta para registrar este jogo</a>
                <?php endif; ?>
            </div>

            <?php if ($owners > 0): ?>
                <section class="bg-zinc-900 rounded-sm border-2 border-zinc-800 p-5">
                    <h2 class="text-sm font-black text-white uppercase tracking-widest mb-4">Na comunidade</h2>
                    <div class="flex h-3 rounded-full overflow-hidden bg-zinc-800" role="img" aria-label="Distribuição de status">
                        <?php foreach ($statuses as $name => $meta): $n = (int) ($byStatus[$name] ?? 0); if (!$n) continue; ?>
                            <span style="width: <?php echo round($n / $owners * 100, 2); ?>%; background: <?php echo $meta['hex']; ?>" title="<?php echo htmlspecialchars($name) . ': ' . $n; ?>"></span>
                        <?php endforeach; ?>
                    </div>
                    <ul class="mt-4 space-y-2 text-sm">
                        <?php foreach ($statuses as $name => $meta): $n = (int) ($byStatus[$name] ?? 0); if (!$n) continue; ?>
                            <li class="flex items-center justify-between">
                                <span class="flex items-center gap-2 text-zinc-300"><span class="w-2.5 h-2.5 rounded-full" style="background: <?php echo $meta['hex']; ?>"></span><?php echo htmlspecialchars($name); ?></span>
                                <span class="font-bold text-white"><?php echo $n; ?></span>
                            </li>
                        <?php endforeach; ?>
                        <?php if ((int) ($stats['platinum_count'] ?? 0) > 0): ?>
                            <li class="flex items-center justify-between pt-2 border-t border-zinc-800">
                                <span class="text-zinc-300">🏆 Platinaram</span>
                                <span class="font-bold text-white"><?php echo (int) $stats['platinum_count']; ?></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </section>
            <?php endif; ?>
        </aside>

        <!-- Conteúdo -->
        <div class="lg:col-span-8 xl:col-span-9 min-w-0 space-y-8">
            <section>
                <h1 class="text-3xl sm:text-5xl font-black text-white tracking-tighter uppercase leading-none break-words"><?php echo htmlspecialchars($game['title']); ?></h1>
                <div class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <?php if ($year): ?>
                        <span class="bg-zinc-800 text-zinc-300 font-bold px-2.5 py-1 rounded-sm"><?php echo htmlspecialchars($year); ?></span>
                    <?php endif; ?>
                    <?php foreach ($genres as $genre): ?>
                        <span class="bg-violet-600/15 text-violet-300 border border-violet-500/30 font-semibold px-2.5 py-1 rounded-full"><?php echo htmlspecialchars(mgl_genre_pt($genre)); ?></span>
                    <?php endforeach; ?>
                </div>

                <dl class="mt-6 grid grid-cols-3 gap-3 max-w-3xl">
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-4">
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Nota média</dt>
                        <dd class="mt-1 text-3xl font-black text-amber-400"><?php echo $avgRating !== null ? number_format($avgRating, 1, ',', '') . '<span class="text-base text-zinc-500">/5</span>' : '<span class="text-zinc-600">—</span>'; ?></dd>
                        <dd class="text-[11px] text-zinc-500"><?php echo $ratingCount; ?> <?php echo $ratingCount === 1 ? 'nota' : 'notas'; ?></dd>
                    </div>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-4">
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Bibliotecas</dt>
                        <dd class="mt-1 text-3xl font-black text-white"><?php echo $owners; ?></dd>
                        <dd class="text-[11px] text-zinc-500"><?php echo (int) ($byStatus['Zerado'] ?? 0); ?> zeraram</dd>
                    </div>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-4">
                        <dt class="text-[10px] font-bold uppercase tracking-widest text-zinc-500">Para zerar</dt>
                        <dd class="mt-1 text-3xl font-black text-white"><?php echo $avgHours !== null ? number_format($avgHours, 0, ',', '.') . '<span class="text-base text-zinc-500"> h</span>' : '<span class="text-zinc-600">—</span>'; ?></dd>
                        <dd class="text-[11px] text-zinc-500">média de quem zerou</dd>
                    </div>
                </dl>
            </section>

            <div class="grid gap-8 xl:grid-cols-3 items-start">
                <div class="xl:col-span-2 space-y-8 min-w-0">
                    <?php if ($descriptionText !== ''): ?>
                        <section class="bg-zinc-900 rounded-sm border-2 border-zinc-800 p-5 sm:p-6">
                            <h2 class="text-sm font-black text-white uppercase tracking-widest mb-3">Sobre o jogo</h2>
                            <div id="gameDescription" class="text-zinc-300 leading-relaxed line-clamp-6"><?php echo nl2br(htmlspecialchars($descriptionDisplay)); ?></div>
                            <?php if (mb_strlen($descriptionText) > 500): ?>
                                <button type="button" id="gameDescriptionToggle" class="mt-3 text-xs font-bold uppercase tracking-wider text-violet-400 hover:text-violet-300">Mostrar mais</button>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <section>
                        <h2 class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-4">Análises <span class="text-zinc-500 text-base">(<?php echo count($reviews); ?>)</span></h2>
                        <?php if (empty($reviews)): ?>
                            <p class="text-zinc-500 text-sm bg-zinc-900 border-2 border-zinc-800 rounded-sm p-5">Ninguém publicou uma análise deste jogo ainda.<?php echo $viewerEntry ? ' Que tal ser o primeiro?' : ''; ?></p>
                        <?php else: ?>
                            <div class="space-y-4">
                                <?php foreach ($reviews as $r): $avatar = mgl_avatar_url($r['avatar']); $reviewUrl = mgl_url('details', ['id' => (int) $game['id'], 'u' => $r['username']]); ?>
                                    <article class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-5">
                                        <header class="flex items-center gap-3">
                                            <a href="<?php echo htmlspecialchars(mgl_url('profile', ['u' => $r['username']])); ?>" class="shrink-0">
                                                <?php if ($avatar): ?>
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" loading="lazy" class="w-10 h-10 rounded-sm object-cover bg-zinc-950">
                                                <?php else: ?>
                                                    <span class="w-10 h-10 rounded-sm bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase"><?php echo htmlspecialchars(mb_substr($r['username'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                            </a>
                                            <div class="flex-1 min-w-0">
                                                <a href="<?php echo htmlspecialchars(mgl_url('profile', ['u' => $r['username']])); ?>" class="text-white font-bold text-sm hover:text-violet-400 transition-colors"><?php echo htmlspecialchars($r['display_name'] ?: $r['username']); ?></a>
                                                <div class="flex items-center gap-2 mt-0.5 text-xs">
                                                    <?php if ($r['rating']): ?><span class="text-amber-400"><?php echo str_repeat('★', (int) $r['rating']); ?><span class="text-zinc-700"><?php echo str_repeat('★', 5 - (int) $r['rating']); ?></span></span><?php endif; ?>
                                                    <?php if ($r['status'] && isset($statuses[$r['status']])): ?>
                                                        <span class="text-[10px] font-black uppercase px-1.5 py-0.5 rounded-sm <?php echo $statuses[$r['status']]['badge']; ?>"><?php echo htmlspecialchars($r['status']); ?></span>
                                                    <?php endif; ?>
                                                    <?php if (!empty($r['platinum_at'])): ?><span title="Platinado">🏆</span><?php endif; ?>
                                                    <?php if (!empty($r['time_spent_hours'])): ?><span class="text-zinc-500"><?php echo number_format((float) $r['time_spent_hours'], 0, ',', '.'); ?> h</span><?php endif; ?>
                                                </div>
                                            </div>
                                        </header>
                                        <p class="mt-4 text-zinc-300 text-sm leading-relaxed whitespace-pre-wrap break-words"><?php echo htmlspecialchars($r['review_excerpt']); ?><?php echo (int) $r['review_length'] > 700 ? '…' : ''; ?></p>
                                        <a href="<?php echo htmlspecialchars($reviewUrl); ?>" class="mt-4 inline-flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-violet-400 hover:text-violet-300">
                                            <?php echo (int) $r['review_length'] > 700 ? 'Ler análise completa' : 'Abrir análise'; ?>
                                            <?php if ((int) $r['comment_count'] > 0): ?><span class="text-zinc-500 normal-case tracking-normal font-semibold">· <?php echo (int) $r['comment_count']; ?> <?php echo (int) $r['comment_count'] === 1 ? 'comentário' : 'comentários'; ?></span><?php endif; ?>
                                        </a>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </section>
                </div>

                <div class="space-y-8">
                    <?php if ($isLoggedIn): ?>
                        <section>
                            <h2 class="text-sm font-black text-white uppercase tracking-widest mb-3">Quem você segue</h2>
                            <?php if (empty($followedPlayers)): ?>
                                <p class="text-zinc-500 text-sm bg-zinc-900 border-2 border-zinc-800 rounded-sm p-4">Ninguém que você segue tem este jogo ainda.</p>
                            <?php else: ?>
                                <ul class="space-y-2">
                                    <?php foreach ($followedPlayers as $p): $avatar = mgl_avatar_url($p['avatar']); ?>
                                        <li>
                                            <a href="<?php echo htmlspecialchars(mgl_url('details', ['id' => (int) $game['id'], 'u' => $p['username']])); ?>" class="flex items-center gap-3 bg-zinc-900 border-2 border-zinc-800 hover:border-violet-500 rounded-sm p-3 transition-colors">
                                                <?php if ($avatar): ?>
                                                    <img src="<?php echo htmlspecialchars($avatar); ?>" alt="" loading="lazy" class="w-10 h-10 rounded-sm object-cover bg-zinc-950">
                                                <?php else: ?>
                                                    <span class="w-10 h-10 rounded-sm bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase"><?php echo htmlspecialchars(mb_substr($p['username'], 0, 1)); ?></span>
                                                <?php endif; ?>
                                                <span class="flex-1 min-w-0">
                                                    <span class="block text-white font-bold text-sm truncate"><?php echo htmlspecialchars($p['display_name'] ?: $p['username']); ?></span>
                                                    <span class="flex items-center gap-2 mt-1">
                                                        <?php if ($p['status'] && isset($statuses[$p['status']])): ?>
                                                            <span class="text-[10px] font-black uppercase px-1.5 py-0.5 rounded-sm <?php echo $statuses[$p['status']]['badge']; ?>"><?php echo htmlspecialchars($p['status']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($p['rating']): ?>
                                                            <span class="text-amber-400 text-xs"><?php echo str_repeat('★', (int) $p['rating']); ?></span>
                                                        <?php endif; ?>
                                                        <?php if ($p['has_review']): ?>
                                                            <span class="text-[10px] text-violet-300 font-bold uppercase">análise</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </span>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </section>
                    <?php endif; ?>

                    <?php if ($ratingCount > 0): ?>
                        <section class="bg-zinc-900 rounded-sm border-2 border-zinc-800 p-5">
                            <h2 class="text-sm font-black text-white uppercase tracking-widest mb-4">Notas</h2>
                            <ul class="space-y-1.5">
                                <?php for ($star = 5; $star >= 1; $star--): $n = (int) ($distribution[$star] ?? 0); ?>
                                    <li class="flex items-center gap-2 text-xs">
                                        <span class="w-8 text-amber-400 font-bold"><?php echo $star; ?> ★</span>
                                        <span class="flex-1 h-2 bg-zinc-800 rounded-full overflow-hidden"><span class="block h-full bg-amber-400 rounded-full" style="width: <?php echo $maxDist ? round($n / $maxDist * 100) : 0; ?>%"></span></span>
                                        <span class="w-6 text-right text-zinc-400"><?php echo $n; ?></span>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php require __DIR__ . '/../partials/igdb_credit.php'; ?>
</main>

<script>
(function () {
    const text = document.getElementById('gameDescription');
    const toggle = document.getElementById('gameDescriptionToggle');
    if (!text || !toggle) return;
    toggle.addEventListener('click', () => {
        const collapsed = text.classList.toggle('line-clamp-6');
        toggle.textContent = collapsed ? 'Mostrar mais' : 'Mostrar menos';
    });
})();
</script>
</body>
</html>
