<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6">
        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tighter uppercase mb-8 text-center">Buscar Jogos</h1>

        
        <div class="bg-zinc-900 p-6 sm:p-8 rounded-sm border-2 border-zinc-800 mb-10 shadow-xl">
            <form action="index.php" method="GET" class="relative" onsubmit="return false;">
                <input type="hidden" name="action" value="search">
                <input type="text" id="liveSearchInput" name="q" autocomplete="off" placeholder="Ex: The Witcher 3, Elden Ring, Minecraft..." value="<?php echo htmlspecialchars($q ?? '', ENT_QUOTES, 'UTF-8'); ?>" autofocus
                       class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-5 py-4 text-lg focus:outline-none focus:border-violet-500 font-medium placeholder-zinc-600">
                <span id="searchSpinner" class="hidden absolute right-5 top-1/2 -translate-y-1/2 h-5 w-5 border-2 border-zinc-600 border-t-violet-500 rounded-full animate-spin"></span>
            </form>
        </div>

        <h2 id="searchTitle" class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6" style="display: <?php echo !empty($q) ? 'block' : 'none'; ?>;">
            <?php if (!empty($q)) echo 'Resultados para "<span class="text-violet-400">' . htmlspecialchars($q) . '</span>"'; ?>
        </h2>
        
        <div id="resultsGrid" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-6">
            
            <?php if (!empty($results)): ?>
                <?php foreach ($results as $game): ?>
                    <?php include __DIR__ . '/_search_card.php'; ?>
                <?php endforeach; ?>

            <?php elseif (!empty($q)): ?>
                <div class="col-span-full bg-zinc-900 border-2 border-zinc-800 rounded-sm p-12 text-center shadow-xl mt-4">
                    <div class="text-zinc-700 mb-4 text-5xl">📡</div>
                    <h3 class="text-xl font-black text-white uppercase tracking-tight mb-2">Sinal Perdido</h3>
                    <p class="text-zinc-400 font-medium">Nenhum jogo encontrado para "<strong><?php echo htmlspecialchars($q); ?></strong>".</p>
                </div>
            <?php endif; ?>

        </div>

        <?php if (!empty($popularGames)): ?>
            <section id="popularSection" aria-labelledby="popularTitle" class="mt-2">
                <h2 id="popularTitle" class="text-xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6">Populares agora</h2>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 2xl:grid-cols-6 gap-3 sm:gap-6">
                    <?php foreach ($popularGames as $game): ?>
                        <?php include __DIR__ . '/_search_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
    
    <script src="<?php echo mgl_asset('assets/js/liveSearch.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/notifications.js'); ?>"></script>
<?php require __DIR__ . '/../partials/igdb_credit.php'; ?>
</body>
</html>