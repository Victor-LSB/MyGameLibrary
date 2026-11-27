<?php
/**
 * Seção da biblioteca do perfil (#biblioteca).
 * Usada pelo view.php e também devolvida sozinha quando o JS pede ?partial=library,
 * para trocar filtro/paginação/"ver todos" sem recarregar a página.
 */
?>
<?php
    $libraryMode = $libraryMode ?? false;
    $libraryTotalAll = $libraryTotalAll ?? 0;
    $librarySort = $librarySort ?? 'recent';
    $librarySearch = $librarySearch ?? '';
    $libraryPage = $libraryPage ?? 1;
    $libraryPages = $libraryPages ?? 1;
    $libraryTotal = $libraryTotal ?? 0;
    $gamesToShow = $libraryMode ? ($libraryGames ?? []) : $recentGames;
    // Endereços pelo helper: /perfil/victor?view=all... com PRETTY_URLS=true, ou o formato antigo.
    $profileName    = $profileUser['username'];
    $profileUrl     = mgl_url('profile', ['u' => $profileName]);
    $libraryAllUrl  = mgl_url('profile', ['u' => $profileName, 'view' => 'all']);
    $libraryParams  = ['u' => $profileName, 'view' => 'all', 'sort' => $librarySort]
                    + ($librarySearch !== '' ? ['q' => $librarySearch] : []);
    $libraryPageUrl = fn(int $page) => mgl_url('profile', $libraryParams + ['page' => $page]) . '#biblioteca';
?>
<section id="biblioteca">
<div class="flex flex-wrap items-center justify-between gap-3 mb-6">
    <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3">
        <?php echo $libraryMode ? 'Biblioteca (' . (int) $libraryTotal . ')' : 'Adicionados recentemente'; ?>
    </h2>
    <?php if ($libraryMode): ?>
        <a href="<?php echo htmlspecialchars($profileUrl, ENT_QUOTES); ?>#biblioteca" data-library-link class="text-xs font-bold uppercase tracking-wider text-zinc-400 hover:text-violet-400 transition-colors">&larr; Voltar aos recentes</a>
    <?php elseif ($libraryTotalAll > count($recentGames)): ?>
        <a href="<?php echo htmlspecialchars($libraryAllUrl, ENT_QUOTES); ?>#biblioteca" data-library-link class="text-xs font-bold uppercase tracking-wider text-violet-400 hover:text-violet-300 transition-colors">Ver biblioteca completa (<?php echo (int) $libraryTotalAll; ?>) &rarr;</a>
    <?php endif; ?>
</div>

<?php if ($libraryMode): ?>
    <form method="get" action="<?php echo htmlspecialchars(mgl_pretty_enabled() ? $profileUrl : 'index.php', ENT_QUOTES); ?>" data-library-form class="flex flex-wrap gap-3 mb-6">
        <?php if (!mgl_pretty_enabled()): ?>
            <input type="hidden" name="action" value="profile">
            <input type="hidden" name="u" value="<?php echo htmlspecialchars($profileName, ENT_QUOTES); ?>">
        <?php endif; ?>
        <input type="hidden" name="view" value="all">
        <input type="search" name="q" value="<?php echo htmlspecialchars($librarySearch, ENT_QUOTES); ?>" placeholder="Buscar jogo..." maxlength="60"
               class="flex-1 min-w-[160px] bg-zinc-900 border-2 border-zinc-800 text-white text-sm px-3 py-2 rounded-sm focus:border-violet-500 outline-none">
        <select name="sort" data-custom-select onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()" class="bg-zinc-900 border-2 border-zinc-800 text-white text-sm px-3 py-2 rounded-sm focus:border-violet-500 outline-none cursor-pointer appearance-none">
            <?php foreach (\Victi\MyGameLibrary\Models\Game::LIBRARY_SORT_LABELS as $key => $label): ?>
                <option value="<?php echo $key; ?>" <?php echo $librarySort === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>
<?php endif; ?>

<?php if (!empty($gamesToShow) && count($gamesToShow) > 0): ?>
    <!-- data-top5-source: o top-games.js deixa arrastar estes cards para o Top 5 (só no perfil do dono) -->
    <div data-top5-source class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-4 sm:gap-6">
        <?php foreach ($gamesToShow as $game): ?>
            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group"
                 data-top5-game
                 data-game-id="<?php echo (int) $game['id']; ?>"
                 data-game-title="<?php echo htmlspecialchars($game['title'], ENT_QUOTES); ?>"
                 data-game-cover="<?php echo htmlspecialchars($game['cover_image'] ?? '', ENT_QUOTES); ?>">
                <!-- AQUI ESTÁ A CORREÇÃO: O link agora carrega o ID do jogo e o nome de usuário dono do perfil (&u=...) -->
                <a href="index.php?action=details&id=<?php echo $game['id']; ?>&u=<?php echo urlencode($profileUser['username']); ?>" class="block aspect-[3/4] bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden relative">
                    <?php if (!empty($game['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>" loading="lazy" decoding="async" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300 bg-zinc-950">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-zinc-700 font-bold uppercase text-xs sm:text-base text-center p-2"><?php echo htmlspecialchars($game['title']); ?></div>
                    <?php endif; ?>

                    <div class="absolute top-2 left-2 bg-zinc-900/90 backdrop-blur-sm text-white text-[10px] font-black uppercase tracking-wider px-2 py-1 border border-zinc-700 rounded-sm">
                        <?php echo htmlspecialchars($game['status'] ?? ''); ?>
                    </div>
                </a>
                <div class="p-3">
                    <h3 class="font-bold text-white text-sm line-clamp-1" title="<?php echo htmlspecialchars($game['title']); ?>">
                        <!-- A mesma correção de URL foi aplicada no título -->
                        <a href="index.php?action=details&id=<?php echo $game['id']; ?>&u=<?php echo urlencode($profileUser['username']); ?>" class="hover:text-violet-400 transition-colors">
                            <?php echo htmlspecialchars($game['title']); ?>
                        </a>
                    </h3>
                    <?php if ($game['rating']): ?>
                        <p class="text-amber-400 text-xs mt-1 font-black tracking-widest">★ <?php echo $game['rating']; ?>/5</p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <?php if ($libraryMode && $libraryPages > 1): ?>
        <nav class="flex items-center justify-center gap-4 mt-8" aria-label="Paginação da biblioteca">
            <?php if ($libraryPage > 1): ?>
                <a href="<?php echo htmlspecialchars($libraryPageUrl($libraryPage - 1), ENT_QUOTES); ?>" data-library-link class="px-4 py-2 bg-zinc-900 border-2 border-zinc-800 hover:border-violet-500 text-white text-sm font-bold rounded-sm">&larr; Anterior</a>
            <?php endif; ?>
            <span class="text-zinc-400 text-sm">Página <?php echo (int) $libraryPage; ?> de <?php echo (int) $libraryPages; ?></span>
            <?php if ($libraryPage < $libraryPages): ?>
                <a href="<?php echo htmlspecialchars($libraryPageUrl($libraryPage + 1), ENT_QUOTES); ?>" data-library-link class="px-4 py-2 bg-zinc-900 border-2 border-zinc-800 hover:border-violet-500 text-white text-sm font-bold rounded-sm">Próxima &rarr;</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php else: ?>
    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-12 text-center shadow-xl">
        <p class="text-zinc-500 font-medium"><?php echo ($libraryMode && $librarySearch !== '') ? 'Nenhum jogo encontrado para essa busca.' : 'Este jogador ainda não registrou nenhum jogo.'; ?></p>
    </div>
<?php endif; ?>
</section>
