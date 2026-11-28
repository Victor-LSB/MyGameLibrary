<?php
    // Essas variáveis vêm do GameController::index() (que faz include desta
    // view, então elas já chegam definidas em tempo de execução). Os valores
    // padrão abaixo são só pra deixar isso explícito pro analisador estático
    // do editor e pra proteger a view caso seja incluída de outro lugar.
    $current_page = $current_page ?? 1;
    $total_pages = $total_pages ?? 1;
    $total_games = $total_games ?? 0;
    $search_query = $search_query ?? '';
    $filter_status = $filter_status ?? '';
    $filter_tag = $filter_tag ?? '';
    $sort = $sort ?? 'recent';
    // Mantém a ordenação escolhida nos links de tag/limpar (só quando não é a padrão)
    $keepSort = $sort !== 'recent' ? '&sort=' . urlencode($sort) : '';
    $extraStyles = array_merge($extraStyles ?? [], ['assets/css/tags.css']);
?>
<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6 pb-12">
        <p class="text-sm text-zinc-400 font-medium mb-6">Bem-vindo, <span class="text-violet-400 font-bold"><?php echo htmlspecialchars(!empty($_SESSION['display_name']) ? $_SESSION['display_name'] : $_SESSION['username']); ?></span>!</p>

        <?php if (isset($_SESSION['verification_notice'])): ?>
            <div class="mb-6 bg-zinc-900 border border-zinc-800 text-zinc-300 px-4 py-3 rounded-sm text-sm">
                <?php echo htmlspecialchars($_SESSION['verification_notice']); unset($_SESSION['verification_notice']); ?>
            </div>
        <?php elseif (isset($_SESSION['email_verified']) && $_SESSION['email_verified'] === false): ?>
            <div class="mb-6 bg-amber-950/60 border border-amber-800 text-amber-300 px-4 py-3 rounded-sm text-sm flex flex-col sm:flex-row items-center justify-between gap-3">
                <span>Confirme o seu e-mail para ativar todos os recursos da sua conta.</span>
                <a href="index.php?action=resend_verification" class="shrink-0 bg-amber-800/60 hover:bg-amber-800 text-amber-100 px-4 py-1.5 rounded-sm font-bold uppercase tracking-wide text-xs transition-colors">Reenviar e-mail</a>
            </div>
        <?php endif; ?>

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 gap-4">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3">
                Minha Biblioteca
                <?php if (!empty($userGames) || !empty($search_query) || !empty($filter_status) || !empty($filter_tag)): ?>
                    <span class="text-zinc-500 text-base font-bold normal-case">(<?php echo (int) ($total_games ?? 0); ?> <?php echo ($total_games ?? 0) === 1 ? 'jogo' : 'jogos'; ?>)</span>
                <?php endif; ?>
            </h2>
        </div>

        <?php if (!empty($filter_tag)): ?>
            <div class="mgl-tag-filter">
                <span><em>Filtrando por</em> <b>#<?php echo htmlspecialchars($filter_tag ?? ''); ?></b></span>
                <a href="index.php?action=home<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?><?php echo $keepSort; ?>">Limpar</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($userTags)): ?>
            <div class="mb-6 bg-zinc-900 rounded-sm border-2 border-zinc-800 p-4 shadow-lg">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <h3 class="text-sm font-black uppercase tracking-widest text-zinc-400">Tags Salvas</h3>
                    <?php if (!empty($filter_tag)): ?>
                        <a href="index.php?action=home<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?><?php echo $keepSort; ?>" class="text-xs font-bold uppercase tracking-wider text-zinc-500 hover:text-white transition-colors">Limpar tag ativa</a>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($userTags as $tag): ?>
                        <?php $isActiveTag = !empty($filter_tag) && $filter_tag === $tag['name']; ?>
                        <div class="mgl-tag<?php echo $isActiveTag ? ' is-active' : ''; ?>">
                            <a href="index.php?action=home&tag=<?php echo urlencode($tag['name']); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?><?php echo $keepSort; ?>"<?php echo $isActiveTag ? ' aria-current="true"' : ''; ?>>
                                <b>#</b><?php echo htmlspecialchars($tag['name']); ?>
                                <span class="mgl-tag-count"><?php echo (int) ($tag['usage_count'] ?? 0); ?></span>
                            </a>
                            <form action="index.php?action=delete_saved_tag" method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                <input type="hidden" name="tag_id" value="<?php echo (int) $tag['id']; ?>">
                                <button type="submit" class="mgl-tag-remove" title="Excluir tag salva" aria-label="Excluir tag <?php echo htmlspecialchars($tag['name']); ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3.5" aria-hidden="true"><path stroke-linecap="square" d="M6 6l12 12M18 6L6 18"/></svg></button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-zinc-900 p-3 sm:p-4 border-2 border-zinc-800 mb-8 flex flex-col md:flex-row gap-3 items-center">
            <form id="searchForm" action="index.php" method="GET" class="flex-1 w-full flex gap-2">
                <input type="hidden" name="action" value="home">
                <?php if (!empty($filter_tag)): ?>
                    <input type="hidden" name="tag" value="<?php echo htmlspecialchars($filter_tag ?? ''); ?>">
                <?php endif; ?>
                <?php if (!empty($filter_status)): ?>
                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filter_status ?? ''); ?>">
                <?php endif; ?>
                <?php if ($sort !== 'recent'): ?>
                    <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                <?php endif; ?>
                <input id="searchInput" type="text" name="search" placeholder="Buscar na biblioteca..."  autocomplete="off" value="<?php echo htmlspecialchars($search_query ?? ''); ?>" 
                    class="flex-1 h-10 bg-zinc-950 border-2 border-zinc-800 text-white text-sm px-4 focus:outline-none focus:border-violet-500 font-medium placeholder-zinc-600">
            </form>

            <form action="index.php" method="GET" class="w-full md:w-auto flex-shrink-0 md:flex md:items-center">
                <input type="hidden" name="action" value="home">
                <?php if (!empty($filter_tag)): ?>
                    <input type="hidden" name="tag" value="<?php echo htmlspecialchars($filter_tag ?? ''); ?>">
                <?php endif; ?>
                <?php if (!empty($search_query)): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                <?php endif; ?>
                <select name="filter_status" data-custom-select class="filterStatus w-full bg-zinc-950 border-2 border-zinc-800 text-white text-sm font-bold uppercase tracking-wide h-10 px-4 focus:outline-none focus:border-violet-500 cursor-pointer appearance-none">
                    <option value="">Todos os status</option>
                    <?php foreach (array_keys(mgl_statuses()) as $statusName): ?>
                        <option value="<?php echo htmlspecialchars($statusName); ?>" <?php if (!empty($filter_status) && $filter_status == $statusName) echo 'selected'; ?>><?php echo htmlspecialchars($statusName); ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="sort" data-custom-select onchange="this.form.submit()" aria-label="Ordenar biblioteca" class="mt-3 md:mt-0 md:ml-2 w-full bg-zinc-950 border-2 border-zinc-800 text-white text-sm font-bold uppercase tracking-wide h-10 px-4 focus:outline-none focus:border-violet-500 cursor-pointer appearance-none">
                    <?php foreach (\Victi\MyGameLibrary\Models\Game::LIBRARY_SORT_LABELS as $sortKey => $sortLabel): ?>
                        <option value="<?php echo $sortKey; ?>" <?php echo $sort === $sortKey ? 'selected' : ''; ?>><?php echo htmlspecialchars($sortLabel); ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>

        <?php if (!empty($userGames) && count($userGames) > 0): ?>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-6">
                
            <?php foreach ($userGames as $game): ?>
                <div class="gameItem bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group" id="game-<?php echo $game['id']; ?>">
                    <?php
                        $isZerado = ($game['status'] ?? '') === 'Zerado';
                        $isPlatinum = !empty($game['platinum_at']);
                    ?>
                    <div class="relative aspect-[3/4] bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden">
                        <a href="index.php?action=details&id=<?php echo $game['id']; ?>" class="block h-full w-full">
                            <?php if (!empty($game['cover_image'])): ?>
                                <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-zinc-700 font-bold uppercase text-center p-2"><?php echo htmlspecialchars($game['title']); ?></div>
                            <?php endif; ?>

                            <div class="absolute top-2 left-2 bg-zinc-900 text-white text-[10px] font-black uppercase tracking-wider px-2 py-1 border border-zinc-700 rounded-sm gameStatus"><?php echo htmlspecialchars($game['status'] ?? ''); ?></div>
                        </a>

                        <button type="button"
                            class="platinum-btn absolute top-2 right-2 w-7 h-7 flex items-center justify-center rounded-sm border transition-colors <?php echo $isPlatinum ? 'bg-amber-400 border-amber-400 text-zinc-900' : ($isZerado ? 'bg-zinc-900/80 border-zinc-700 text-zinc-400 hover:text-amber-400 hover:border-amber-400' : 'bg-zinc-900/60 border-zinc-800 text-zinc-700 cursor-not-allowed'); ?>"
                            data-game-id="<?php echo $game['id']; ?>"
                            data-platinum="<?php echo $isPlatinum ? '1' : '0'; ?>"
                            <?php echo $isZerado ? '' : 'disabled'; ?>
                            title="<?php echo $isZerado ? ($isPlatinum ? 'Platinado — clique para desmarcar' : 'Marcar como platinado') : 'Disponível depois de marcar como Zerado'; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4 pointer-events-none">
                                <path d="M6 3h12v2h2a1 1 0 0 1 1 1v1a5 5 0 0 1-4.53 4.98A6.02 6.02 0 0 1 13 15.9V18h2a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2h2v-2.1a6.02 6.02 0 0 1-3.47-3.92A5 5 0 0 1 3 7V6a1 1 0 0 1 1-1h2V3Zm-2 4v0a3 3 0 0 0 2.4 2.94A9 9 0 0 1 6 7V6H4v1Zm14 0V6h-2v1a9 9 0 0 1-.4 2.94A3 3 0 0 0 18 7Z"/>
                            </svg>
                        </button>
                    </div>

                    <div class="p-3 sm:p-4 flex-1 flex flex-col">
                        <h3 class="font-bold text-white mb-3 line-clamp-2 leading-tight text-base sm:text-lg">
                            <a href="index.php?action=details&id=<?php echo $game['id']; ?>" class="hover:text-violet-400 transition-colors">
                                <?php echo htmlspecialchars($game['title']); ?>
                            </a>
                        </h3>
                        
                        <p class="pRating hidden">Avaliação: <?php echo isset($game['rating']) ? htmlspecialchars($game['rating'] ?? '') : 'Não avaliado'; ?></p>
                        
                        <div class="mb-4 mt-auto">
                            <span class="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Sua nota</span>
                            <form class="ratingForm block" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($game['status'] ?? ''); ?>">
                                
                                <div class="seg">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex-1 cursor-pointer border-r-2 border-zinc-800 last:border-r-0">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" class="peer sr-only" <?php if (isset($game['rating']) && $game['rating'] == $i) echo 'checked'; ?>>
                                        <div class="h-8 flex items-center justify-center text-xs font-extrabold text-zinc-400 hover:bg-zinc-800 hover:text-white peer-checked:bg-amber-400 peer-checked:text-zinc-950 peer-focus-visible:ring-2 peer-focus-visible:ring-violet-400 transition-colors">
                                            <?php echo $i; ?>
                                        </div>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </form>
                        </div>

                        <div class="pt-3 border-t-2 border-zinc-800">
                            <?php $currentStatus = $game['status'] ?? ''; ?>
                            <!-- Celular: os três botões não cabem com folga, então vira um seletor nativo -->
                            <?php $cardStatus = $game['status'] ?? ''; ?>
                            <label class="sm:hidden relative block mt-2">
                                <span class="sr-only">Status</span>
                                <select class="statusSelect w-full h-9 appearance-none bg-zinc-950 border-2 border-zinc-800 text-white text-[11px] font-extrabold uppercase tracking-wide pl-3 pr-7 focus:outline-none focus:border-violet-500" data-current="<?php echo htmlspecialchars($cardStatus); ?>" data-status-select="<?php echo htmlspecialchars($cardStatus); ?>">
                                    <?php if (!in_array($cardStatus, ['Jogando', 'Zerado', 'Dropado'], true)): ?>
                                        <option value="<?php echo htmlspecialchars($cardStatus); ?>" selected disabled><?php echo htmlspecialchars($cardStatus ?: 'Status'); ?></option>
                                    <?php endif; ?>
                                    <?php foreach (['Jogando', 'Zerado', 'Dropado'] as $opt): ?>
                                        <option value="<?php echo $opt; ?>" <?php echo $cardStatus === $opt ? 'selected' : ''; ?>><?php echo $opt; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <svg viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none" aria-hidden="true"><path d="M12 15.5 5 8.5h14z"/></svg>
                            </label>
                            <div class="seg mt-2 hidden sm:flex">
                                <form class="formStatus flex-1 flex" action="index.php?action=change_status" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                    <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating'] ?? ''); ?>">
                                    <input type="hidden" name="status" value="Jogando">
                                    <button type="submit" title="Jogando" data-status-btn="Jogando" class="status-btn seg-item w-full !px-1.5 !text-[9px] min-[400px]:!text-[10px] !tracking-normal <?php echo $currentStatus === 'Jogando' ? 'is-active' : 'is-inactive'; ?>">Jogando</button>
                                </form>

                                <form class="formStatus flex-1 flex" action="index.php?action=change_status" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                    <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating'] ?? ''); ?>">
                                    <input type="hidden" name="status" value="Zerado">
                                    <button type="submit" title="Zerado" data-status-btn="Zerado" class="status-btn seg-item w-full !px-1.5 !text-[9px] min-[400px]:!text-[10px] !tracking-normal <?php echo $currentStatus === 'Zerado' ? 'is-active' : 'is-inactive'; ?>">Zerado</button>
                                </form>

                                <form class="formStatus flex-1 flex" action="index.php?action=change_status" method="post">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                    <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating'] ?? ''); ?>">
                                    <input type="hidden" name="status" value="Dropado">
                                    <button type="submit" title="Dropado" data-status-btn="Dropado" class="status-btn seg-item w-full !px-1.5 !text-[9px] min-[400px]:!text-[10px] !tracking-normal <?php echo $currentStatus === 'Dropado' ? 'is-active' : 'is-inactive'; ?>">Dropado</button>
                                </form>
                            </div>

                            <form action="index.php?action=delete_game" method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <input type="hidden" name="page" value="<?php echo (int) ($current_page ?? 1); ?>">
                                <?php if (!empty($search_query)): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search_query ?? ''); ?>">
                                <?php endif; ?>
                                <?php if (!empty($filter_status)): ?>
                                    <input type="hidden" name="filter_status" value="<?php echo htmlspecialchars($filter_status ?? ''); ?>">
                                <?php endif; ?>
                                <?php if (!empty($filter_tag)): ?>
                                    <input type="hidden" name="tag" value="<?php echo htmlspecialchars($filter_tag ?? ''); ?>">
                                <?php endif; ?>
                                <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>">
                                <button type="submit" class="btn-link is-danger w-full justify-center mt-3">Remover da biblioteca</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            </div>

            <?php if ($total_pages > 1): ?>
                <?php
                    $buildPageUrl = function ($targetPage) use ($search_query, $filter_status, $filter_tag, $sort) {
                        $params = ['action' => 'home', 'page' => $targetPage];
                        if ($sort !== 'recent') $params['sort'] = $sort;
                        if (!empty($search_query)) $params['search'] = $search_query;
                        if (!empty($filter_status)) $params['filter_status'] = $filter_status;
                        if (!empty($filter_tag)) $params['tag'] = $filter_tag;
                        return 'index.php?' . http_build_query($params);
                    };
                ?>
                <div class="mt-8 flex items-center justify-center gap-3">
                    <?php if ($current_page > 1): ?>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($current_page - 1)); ?>" class="btn btn-secondary">← Anterior</a>
                    <?php else: ?>
                        <span class="btn btn-secondary opacity-40 cursor-not-allowed">← Anterior</span>
                    <?php endif; ?>

                    <span class="text-zinc-400 text-sm font-bold uppercase tracking-wide px-2">Página <?php echo (int) $current_page; ?> de <?php echo (int) $total_pages; ?></span>

                    <?php if ($current_page < $total_pages): ?>
                        <a href="<?php echo htmlspecialchars($buildPageUrl($current_page + 1)); ?>" class="btn btn-secondary">Próxima →</a>
                    <?php else: ?>
                        <span class="btn btn-secondary opacity-40 cursor-not-allowed">Próxima →</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-16 text-center shadow-xl mt-8">
                <div class="text-zinc-700 mb-4 text-6xl">👾</div>
                <h3 class="text-2xl font-black text-white uppercase tracking-tight mb-2">Sem Saves Encontrados</h3>
                <p class="text-zinc-400 mb-8 font-medium">Sua biblioteca de jogos está vazia. É hora de iniciar uma nova campanha!</p>
                <a href='index.php?action=search' class="btn btn-primary">Encontrar meu primeiro jogo</a>
            </div>
        <?php endif; ?>

    </main>

    <div id="completionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 backdrop-blur-sm px-4">
        <div class="w-full max-w-lg rounded-sm border-2 border-zinc-800 bg-zinc-900 shadow-2xl shadow-black/60">
            <div class="border-b-2 border-zinc-800 px-6 py-5 border-l-4 border-l-violet-500">
                <h3 class="text-2xl font-black uppercase tracking-tight text-white">Marcar como Zerado</h3>
                <p class="mt-2 text-sm text-zinc-400 font-medium">Adicione os detalhes da conclusão antes de salvar o status.</p>
            </div>

            <form id="completionForm" class="px-6 py-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                <input type="hidden" name="game_id" id="modalGameId">
                <input type="hidden" name="status" id="modalStatus" value="Zerado">

                <div class="grid grid-cols-1 gap-4">
                    <label class="block">
                        <span class="mb-2 block text-xs font-black uppercase tracking-widest text-zinc-500">Data de Conclusão</span>
                        <input type="datetime-local" data-datepicker name="completion_date" id="modalCompletionDate" class="w-full rounded-sm border-2 border-zinc-800 bg-zinc-950 px-4 py-3 text-zinc-100 font-medium outline-none transition-colors focus:border-violet-500">
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-black uppercase tracking-widest text-zinc-500">Horas Jogadas</span>
                        <input type="text" inputmode="decimal" autocomplete="off" data-hours name="time_spent_hours" id="modalTimeSpentHours" class="w-full rounded-sm border-2 border-zinc-800 bg-zinc-950 px-4 py-3 text-zinc-100 font-medium outline-none transition-colors focus:border-violet-500" placeholder="Ex.: 3,1 ou 3:20">
                    </label>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" id="cancelCompletionModal" class="rounded-sm border-2 border-zinc-800 px-4 py-2 text-sm font-bold uppercase tracking-widest text-zinc-300 transition-colors hover:border-zinc-600 hover:text-white">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar Zerado</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="<?php echo mgl_asset('assets/css/form-fields.css'); ?>">
    <script src="<?php echo mgl_asset('assets/js/form-fields.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/status.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/notifications.js'); ?>"></script>
<?php require __DIR__ . '/../partials/igdb_credit.php'; ?>
</body>
</html>