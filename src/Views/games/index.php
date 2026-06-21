<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MyGameLibrary - Meus Jogos</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
        <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
            <div>
                <h1 class="text-3xl font-black text-white tracking-tighter uppercase">MyGameLibrary</h1>
                <p class="text-sm text-zinc-400 font-medium mt-1">Bem-vindo, <span class="text-violet-400 font-bold"><?php echo htmlspecialchars(!empty($_SESSION['display_name']) ? $_SESSION['display_name'] : $_SESSION['username']); ?></span>!</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <!-- NOVO: Botão Meu Perfil -->
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

        <div class="bg-zinc-900 p-5 rounded-sm border-2 border-zinc-800 mb-8 flex flex-col md:flex-row gap-4 items-center shadow-lg">
            <form id="searchForm" action="index.php" method="GET" class="flex-1 w-full flex gap-2">
                <input type="hidden" name="action" value="home">
                <input id="searchInput" type="text" name="search" placeholder="Buscar na biblioteca..."  autocomplete="off" value="<?php echo htmlspecialchars($search_query ?? ''); ?>" 
                    class="flex-1 bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 font-medium placeholder-zinc-600">
                <button type="submit" class="bg-zinc-200 hover:bg-white text-zinc-900 px-6 py-3 rounded-sm font-bold uppercase tracking-wide transition-colors">Buscar</button>
            </form>

            <form action="index.php" method="GET" class="w-full md:w-auto flex-shrink-0">
                <input type="hidden" name="action" value="home">
                <select name="filter_status" class="filterStatus w-full bg-zinc-950 border-2 border-zinc-800 text-white font-medium rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 cursor-pointer appearance-none">
                    <option value="">Todos os status</option>
                    <option value="Backlog" <?php if (!empty($filter_status) && $filter_status == 'Backlog') echo 'selected'; ?>>Backlog</option>
                    <option value="Jogando" <?php if (!empty($filter_status) && $filter_status == 'Jogando') echo 'selected'; ?>>Jogando</option>
                    <option value="Completo" <?php if (!empty($filter_status) && $filter_status == 'Completo') echo 'selected'; ?>>Completo</option>
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
                                    <input type="hidden" name="status" value="Completo">
                                    <button type="submit" title="Completo" class="w-full text-[11px] font-bold uppercase tracking-wider py-2 bg-emerald-600 text-white rounded-sm hover:bg-emerald-500 transition-colors">Zerado</button>
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

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="./assets/js/status.js"></script>
</body>
</html>