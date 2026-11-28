<?php
/**
 * Card de um jogo da busca (resultado ou populares), com os botões de adicionar.
 * Espera $game no formato de GameAPI::searchGames()['results'][n].
 */
?>
<?php $cover = $game['background_image'] ?? ''; ?>
<div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group">
    <div class="aspect-[3/4] bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden relative">
        <?php if (!empty($cover)): ?>
            <img src="<?php echo htmlspecialchars($cover); ?>" alt="<?php echo htmlspecialchars($game['name']); ?>" loading="lazy" decoding="async" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
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
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
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
            <div class="grid grid-cols-1 gap-1.5">
                <button type="submit" name="initial_status" value="Backlog" class="btn btn-primary">
                    <span class="text-base leading-none">✚</span> Adicionar
                </button>
                <button type="submit" name="initial_status" value="Quero jogar" title="Adicionar como Quero jogar" class="btn btn-secondary hover:!border-fuchsia-500 hover:!text-fuchsia-300 w-full">Quero jogar</button>
            </div>
        </form>
    </div>
</div>
