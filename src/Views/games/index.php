<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <h1 class="text-3xl font-black text-white tracking-tighter uppercase">MyGameLibrary</h1>
                <p class="text-sm text-zinc-400 font-medium mt-1">Bem-vindo, <span class="text-violet-400 font-bold"><?php echo htmlspecialchars(!empty($_SESSION['display_name']) ? $_SESSION['display_name'] : $_SESSION['username']); ?></span>!</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href='index.php?action=dashboard' class="bg-zinc-800 hover:bg-zinc-700 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors border-b-2 border-zinc-950 hover:border-zinc-900">📊 Dashboard</a>
                <a href='index.php?action=profile' class="bg-zinc-800 hover:bg-zinc-700 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors border-b-2 border-zinc-950 hover:border-zinc-900">👤 Meu Perfil</a>
                
                <a href='index.php?action=search' class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors shadow-lg">✚ Adicionar Jogo</a>
                <a href='index.php?action=logout' class="bg-zinc-800 hover:bg-red-600 hover:text-white text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm border-b-2 border-zinc-950 hover:border-red-800 transition-colors">Sair</a>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6 pb-12">
        <div class="flex flex-col sm:flex-row justify-between items-end mb-6 gap-4">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3">Minha Biblioteca</h2>
        </div>

        <?php if (!empty($filter_tag)): ?>
            <div class="mb-4 inline-flex items-center gap-2 bg-violet-600/15 text-violet-300 border border-violet-500/30 px-4 py-2 rounded-full text-sm font-semibold">
                <span>Filtrando por tag:</span>
                <span>#<?php echo htmlspecialchars($filter_tag); ?></span>
                <a href="index.php?action=home<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?>" class="text-white/70 hover:text-white ml-1">Limpar</a>
            </div>
        <?php endif; ?>

        <?php if (!empty($userTags)): ?>
            <div class="mb-6 bg-zinc-900 rounded-sm border-2 border-zinc-800 p-4 shadow-lg">
                <div class="flex items-center justify-between gap-4 mb-3">
                    <h3 class="text-sm font-black uppercase tracking-widest text-zinc-400">Tags Salvas</h3>
                    <?php if (!empty($filter_tag)): ?>
                        <a href="index.php?action=home<?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?>" class="text-xs font-bold uppercase tracking-wider text-zinc-500 hover:text-white transition-colors">Limpar tag ativa</a>
                    <?php endif; ?>
                </div>
                <div class="flex flex-wrap gap-2">
                    <?php foreach ($userTags as $tag): ?>
                        <?php $isActiveTag = !empty($filter_tag) && $filter_tag === $tag['name']; ?>
                        <div class="inline-flex items-center overflow-hidden rounded-full border transition-colors <?php echo $isActiveTag ? 'bg-violet-600 text-white border-violet-400' : 'bg-zinc-950 text-zinc-300 border-zinc-800 hover:border-violet-500 hover:text-violet-300'; ?>">
                            <a href="index.php?action=home&tag=<?php echo urlencode($tag['name']); ?><?php echo !empty($search_query) ? '&search=' . urlencode($search_query) : ''; ?><?php echo !empty($filter_status) ? '&filter_status=' . urlencode($filter_status) : ''; ?>" class="inline-flex items-center gap-2 px-3 py-1.5 text-sm font-semibold transition-colors">
                                <span>#</span>
                                <span><?php echo htmlspecialchars($tag['name']); ?></span>
                                <span class="text-[10px] <?php echo $isActiveTag ? 'text-violet-100/80' : 'text-zinc-500'; ?>">(<?php echo (int) ($tag['usage_count'] ?? 0); ?>)</span>
                            </a>
                            <form action="index.php?action=delete_saved_tag" method="POST" class="flex">
                                <input type="hidden" name="tag_id" value="<?php echo (int) $tag['id']; ?>">
                                <button type="submit" class="inline-flex h-full min-h-[32px] w-9 items-center justify-center border-l border-zinc-700 bg-zinc-800 text-zinc-400 transition-colors hover:bg-red-600 hover:text-white" title="Excluir tag salva" aria-label="Excluir tag salva">
                                    <span class="text-sm font-black leading-none">×</span>
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-zinc-900 p-5 rounded-sm border-2 border-zinc-800 mb-8 flex flex-col md:flex-row gap-4 items-center shadow-lg">
            <form id="searchForm" action="index.php" method="GET" class="flex-1 w-full flex gap-2">
                <input type="hidden" name="action" value="home">
                <?php if (!empty($filter_tag)): ?>
                    <input type="hidden" name="tag" value="<?php echo htmlspecialchars($filter_tag); ?>">
                <?php endif; ?>
                <input id="searchInput" type="text" name="search" placeholder="Buscar na biblioteca..."  autocomplete="off" value="<?php echo htmlspecialchars($search_query ?? ''); ?>" 
                    class="flex-1 bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 font-medium placeholder-zinc-600">
                <button type="submit" class="bg-zinc-200 hover:bg-white text-zinc-900 px-6 py-3 rounded-sm font-bold uppercase tracking-wide transition-colors">Buscar</button>
            </form>

            <form action="index.php" method="GET" class="w-full md:w-auto flex-shrink-0">
                <input type="hidden" name="action" value="home">
                <?php if (!empty($filter_tag)): ?>
                    <input type="hidden" name="tag" value="<?php echo htmlspecialchars($filter_tag); ?>">
                <?php endif; ?>
                <select name="filter_status" class="filterStatus w-full bg-zinc-950 border-2 border-zinc-800 text-white font-medium rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 cursor-pointer appearance-none">
                    <option value="">Todos os status</option>
                    <option value="Backlog" <?php if (!empty($filter_status) && $filter_status == 'Backlog') echo 'selected'; ?>>Backlog</option>
                    <option value="Jogando" <?php if (!empty($filter_status) && $filter_status == 'Jogando') echo 'selected'; ?>>Jogando</option>
                    <option value="Zerado" <?php if (!empty($filter_status) && $filter_status == 'Zerado') echo 'selected'; ?>>Zerado</option>
                    <option value="Dropado" <?php if (!empty($filter_status) && $filter_status == 'Dropado') echo 'selected'; ?>>Dropado</option>
                </select>
            </form>
        </div>

        <?php if (!empty($userGames) && count($userGames) > 0): ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
                
            <?php foreach ($userGames as $game): ?>
                <div class="gameItem bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group" id="game-<?php echo $game['id']; ?>">
                    <a href="index.php?action=details&id=<?php echo $game['id']; ?>" class="block h-56 bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden relative">
                        <?php if (!empty($game['cover_image'])): ?>
                            <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-zinc-700 font-bold uppercase text-center p-2"><?php echo htmlspecialchars($game['title']); ?></div>
                        <?php endif; ?>
                        
                        <div class="absolute top-2 left-2 bg-zinc-900 text-white text-[10px] font-black uppercase tracking-wider px-2 py-1 border border-zinc-700 rounded-sm gameStatus">Status: <?php echo htmlspecialchars($game['status']); ?></div>
                    </a>

                    <div class="p-4 flex-1 flex flex-col">
                        <h3 class="font-bold text-white mb-3 line-clamp-2 leading-tight text-lg">
                            <a href="index.php?action=details&id=<?php echo $game['id']; ?>" class="hover:text-violet-400 transition-colors">
                                <?php echo htmlspecialchars($game['title']); ?>
                            </a>
                        </h3>
                        
                        <p class="pRating hidden">Avaliação: <?php echo isset($game['rating']) ? htmlspecialchars($game['rating']) : 'Não avaliado'; ?></p>
                        
                        <div class="mb-4 mt-auto">
                            <span class="text-[10px] font-black text-zinc-500 uppercase tracking-widest block mb-1.5">Sua Nota</span>
                            <form class="ratingForm block" method="post">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <input type="hidden" name="status" value="<?php echo htmlspecialchars($game['status']); ?>">
                                
                                <div class="flex gap-1 w-full">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                    <label class="flex-1 cursor-pointer">
                                        <input type="radio" name="rating" value="<?php echo $i; ?>" class="peer sr-only" <?php if (isset($game['rating']) && $game['rating'] == $i) echo 'checked'; ?>>
                                        <div class="py-1.5 text-center bg-zinc-950 text-zinc-600 peer-checked:bg-amber-400 peer-checked:text-zinc-900 border border-zinc-800 peer-checked:border-amber-400 font-black rounded-sm text-sm hover:bg-zinc-800 hover:text-zinc-300 transition-all">
                                            <?php echo $i; ?>
                                        </div>
                                    </label>
                                    <?php endfor; ?>
                                </div>
                            </form>
                        </div>

                        <div class="pt-3 border-t-2 border-zinc-800">
                            <div class="grid grid-cols-3 gap-1 mb-2">
                                <form class="formStatus" action="index.php?action=change_status" method="post">
                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                    <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating']); ?>">
                                    <input type="hidden" name="status" value="Jogando">
                                    <button type="submit" title="Jogando" class="w-full text-[11px] font-bold uppercase tracking-wider py-2 bg-blue-600 text-white rounded-sm hover:bg-blue-500 transition-colors">Play</button>
                                </form>

                                <form class="formStatus" action="index.php?action=change_status" method="post">
                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                    <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating']); ?>">
                                    <input type="hidden" name="status" value="Zerado">
                                    <button type="submit" title="Zerado" class="w-full text-[11px] font-bold uppercase tracking-wider py-2 bg-emerald-600 text-white rounded-sm hover:bg-emerald-500 transition-colors">Zerado</button>
                                </form>

                                <form class="formStatus" action="index.php?action=change_status" method="post">
                                    <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                    <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating']); ?>">
                                    <input type="hidden" name="status" value="Dropado">
                                    <button type="submit" title="Dropado" class="w-full text-[11px] font-bold uppercase tracking-wider py-2 bg-amber-600 text-white rounded-sm hover:bg-amber-500 transition-colors">Drop</button>
                                </form>
                            </div>

                            <form action="index.php?action=delete_game" method="post">
                                <input type="hidden" name="game_id" value="<?php echo $game['id']; ?>">
                                <button type="submit" class="w-full text-[11px] uppercase tracking-wider font-bold py-2 mt-1 text-zinc-400 bg-zinc-950 border border-zinc-800 rounded-sm hover:bg-red-600 hover:text-white hover:border-red-600 transition-colors">Remover</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            </div>
        <?php else: ?>
            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-16 text-center shadow-xl mt-8">
                <div class="text-zinc-700 mb-4 text-6xl">👾</div>
                <h3 class="text-2xl font-black text-white uppercase tracking-tight mb-2">Sem Saves Encontrados</h3>
                <p class="text-zinc-400 mb-8 font-medium">Sua biblioteca de jogos está vazia. É hora de iniciar uma nova campanha!</p>
                <a href='index.php?action=search' class="inline-block bg-violet-600 hover:bg-violet-500 text-white px-8 py-4 rounded-sm font-black uppercase tracking-widest transition-colors shadow-lg">Encontrar meu primeiro jogo</a>
            </div>
        <?php endif; ?>

    </main>

    <div id="completionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 backdrop-blur-sm px-4">
        <div class="w-full max-w-lg rounded-sm border-2 border-amber-900 bg-[#15110e] shadow-2xl shadow-black/60">
            <div class="border-b border-amber-900/60 px-6 py-5">
                <p class="text-[10px] font-black uppercase tracking-[0.35em] text-amber-400/80">Dark Academia Archive</p>
                <h3 class="mt-2 text-2xl font-black uppercase tracking-tight text-zinc-100">Mark as Completed</h3>
                <p class="mt-2 text-sm text-zinc-400">Add the completion details before saving the status as Zerado.</p>
            </div>

            <form id="completionForm" class="px-6 py-6">
                <input type="hidden" name="game_id" id="modalGameId">
                <input type="hidden" name="status" id="modalStatus" value="Zerado">

                <div class="grid grid-cols-1 gap-4">
                    <label class="block">
                        <span class="mb-2 block text-xs font-black uppercase tracking-widest text-amber-200/70">Completion Date</span>
                        <input type="datetime-local" name="completion_date" id="modalCompletionDate" class="w-full rounded-sm border border-amber-900/70 bg-zinc-950 px-4 py-3 text-zinc-100 outline-none transition-colors focus:border-amber-400">
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-black uppercase tracking-widest text-amber-200/70">Time Spent (hours)</span>
                        <input type="number" min="0" step="0.25" name="time_spent_hours" id="modalTimeSpentHours" class="w-full rounded-sm border border-amber-900/70 bg-zinc-950 px-4 py-3 text-zinc-100 outline-none transition-colors focus:border-amber-400" placeholder="Ex.: 24.5">
                    </label>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" id="cancelCompletionModal" class="rounded-sm border border-zinc-700 px-4 py-2 text-sm font-bold uppercase tracking-widest text-zinc-300 transition-colors hover:border-zinc-500 hover:text-white">Cancelar</button>
                    <button type="submit" class="rounded-sm bg-amber-700 px-5 py-2.5 text-sm font-black uppercase tracking-widest text-zinc-950 transition-colors hover:bg-amber-600">Salvar Zerado</button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="./assets/js/status.js"></script>
</body>
</html>