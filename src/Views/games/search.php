<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyGameLibrary - Buscar Jogos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
            <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tighter uppercase">Buscar Jogos</h1>
            <a href="index.php?action=home" class="bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm border-b-2 border-zinc-950 hover:border-zinc-900 transition-colors shrink-0">Voltar à Biblioteca</a>
        </div>
    </header>

    <main class="max-w-7xl mx-auto px-6">
        
        <div class="bg-zinc-900 p-6 sm:p-8 rounded-sm border-2 border-zinc-800 mb-10 shadow-xl max-w-4xl mx-auto">
            <form action="index.php" method="GET" class="flex flex-col sm:flex-row gap-3">
                <input type="hidden" name="action" value="search">
                <div class="flex-1">
                    <input type="text" id="liveSearchInput" name="q" autocomplete="off" placeholder="Ex: The Witcher 3, Elden Ring, Minecraft..." value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>" required
                           class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-5 py-4 text-lg focus:outline-none focus:border-violet-500 font-medium placeholder-zinc-600">
                </div>
                <button type="submit" class="bg-zinc-200 hover:bg-white text-zinc-900 px-8 py-4 rounded-sm font-black text-lg uppercase tracking-wide transition-colors shadow-lg">Pesquisar</button>
            </form>
        </div>

        <h2 id="searchTitle" class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6" style="display: <?php echo !empty($q) ? 'block' : 'none'; ?>;">
            <?php if (!empty($q)) echo 'Resultados para "<span class="text-violet-400">' . htmlspecialchars($q) . '</span>"'; ?>
        </h2>
        
        <div id="resultsGrid" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            
            <?php if (!empty($results)): ?>
                <?php foreach ($results as $game): ?>
                    <?php $cover = $game['background_image'] ?? ''; ?>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group">
                        <div class="h-48 sm:h-56 bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden relative">
                            <?php if (!empty($cover)): ?>
                                <img src="<?php echo htmlspecialchars($cover); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-zinc-700 font-bold uppercase">Sem Capa</div>
                            <?php endif; ?>
                            <div class="absolute top-2 right-2 bg-zinc-900 text-zinc-300 text-[10px] font-black uppercase tracking-widest px-2 py-1 border border-zinc-700 rounded-sm">
                                <?php echo htmlspecialchars(substr($game['released'] ?? 'N/A', 0, 4)); ?>
                            </div>
                        </div>
                        
                        <div class="p-4 flex flex-col flex-1">
                            <h3 class="font-bold text-white mb-2 leading-tight line-clamp-2 text-lg" title="<?php echo htmlspecialchars($game['name']); ?>">
                                <?php echo htmlspecialchars($game['name']); ?>
                            </h3>
                            <p class="text-xs font-bold text-zinc-500 uppercase tracking-wider mb-4 line-clamp-1">
                                <?php
                                $genres = [];
                                if (!empty($game['genres'])) {
                                    foreach ($game['genres'] as $genre) { $genres[] = $genre['name']; }
                                }
                                echo !empty($genres) ? htmlspecialchars(implode(' • ', $genres)) : 'Desconhecido';
                                ?>
                            </p>
                            
                            <form action="index.php?action=add_game" method="post" class="mt-auto pt-4 border-t-2 border-zinc-800">
                                <input type="hidden" name="external_id" value="<?php echo htmlspecialchars($game['id']); ?>">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($game['name']); ?>">
                                <input type="hidden" name="cover" value="<?php echo htmlspecialchars($cover); ?>">
                                <input type="hidden" name="platform" value="<?php
                                $platforms = [];
                                if (!empty($game['platforms'])) {
                                    foreach ($game['platforms'] as $platform) { $platforms[] = $platform['platform']['name']; }
                                }
                                echo htmlspecialchars(implode(', ', $platforms));
                                ?>">
                                <input type="hidden" name="genre" value="<?php echo htmlspecialchars(implode(', ', $genres)); ?>">
                                <input type="hidden" name="release_date" value="<?php echo htmlspecialchars($game['released'] ?? ''); ?>">
                                <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-bold uppercase tracking-wider py-3 rounded-sm transition-colors text-xs flex items-center justify-center gap-2">
                                    <span class="text-base leading-none">✚</span> Adicionar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>

            <?php elseif (!empty($q)): ?>
                <div class="col-span-full bg-zinc-900 border-2 border-zinc-800 rounded-sm p-12 text-center shadow-xl mt-4">
                    <div class="text-zinc-700 mb-4 text-5xl">📡</div>
                    <h3 class="text-xl font-black text-white uppercase tracking-tight mb-2">Sinal Perdido</h3>
                    <p class="text-zinc-400 font-medium">Nenhum jogo encontrado para "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>
            <?php endif; ?>

        </div>
    </main>
    
    <script src="./assets/js/liveSearch.js"></script>
</body>
</html>