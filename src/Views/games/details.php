<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
    <?php
        $currentTags = '';
        if (isset($gameTags) && is_array($gameTags) && !empty($gameTags)) {
            $tagNames = [];
            foreach ($gameTags as $tag) {
                if (!empty($tag['name'])) {
                    $tagNames[] = $tag['name'];
                }
            }
            $currentTags = implode(', ', $tagNames);
        }
    ?>

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
        <div class="max-w-5xl mx-auto flex items-center justify-between gap-4">
            <h1 class="text-2xl font-black text-white tracking-tighter uppercase line-clamp-1 flex-1">
                <?php echo isset($game['title']) ? htmlspecialchars($game['title']) : 'Jogo não encontrado'; ?>
            </h1>
            <a href="index.php?action=home" class="bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm border-b-2 border-zinc-950 hover:border-zinc-900 transition-colors shrink-0">Voltar à Biblioteca</a>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6">
        
        <?php if (!isset($game) || !$game): ?>
            <div class="bg-zinc-900 rounded-sm border-2 border-zinc-800 p-8 text-center shadow-2xl">
                <p class="text-zinc-400 font-medium text-lg">As informações deste jogo não foram encontradas na biblioteca deste utilizador.</p>
                <a href="index.php?action=home" class="inline-block mt-4 text-violet-400 hover:text-violet-300 font-bold underline">Voltar para a página inicial</a>
            </div>
        <?php else: ?>

        <div class="bg-zinc-900 rounded-sm border-2 border-zinc-800 p-6 sm:p-8 flex flex-col md:flex-row gap-8 shadow-2xl">
            
            <div class="w-full md:w-1/3 shrink-0 flex flex-col gap-4">
                <div class="bg-zinc-950 border-4 border-zinc-800 rounded-sm overflow-hidden shadow-xl aspect-[3/4]">
                    <?php if (!empty($game['cover_image'])): ?>
                        <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="Capa de <?php echo htmlspecialchars($game['title']); ?>" class="w-full h-full object-cover">
                    <?php else: ?>
                        <div class="w-full h-full flex items-center justify-center text-zinc-600 font-black uppercase text-2xl text-center p-4">Sem Capa</div>
                    <?php endif; ?>
                </div>
                
                <div class="bg-zinc-950 border-2 border-zinc-800 p-4 rounded-sm text-center">
                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-1">Status Atual</span>
                    <span class="text-xl font-bold <?php 
                        echo isset($game['status']) ? match($game['status']) {
                            'Jogando' => 'text-blue-400',
                            'Completo' => 'text-emerald-400',
                            'Dropado' => 'text-amber-400',
                            default => 'text-white'
                        } : 'text-zinc-600';
                    ?> uppercase tracking-tight"><?php echo isset($game['status']) ? htmlspecialchars($game['status']) : 'N/A'; ?></span>
                </div>

                <?php if (isset($game['rating']) && $game['rating']): ?>
                <div class="bg-zinc-950 border-2 border-zinc-800 p-4 rounded-sm text-center">
                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-1">A Sua Nota</span>
                    <div class="text-3xl text-amber-400 font-black tracking-widest">
                        <?php 
                            for($i=1; $i<=5; $i++) {
                                echo $i <= $game['rating'] ? '★' : '<span class="text-zinc-800">★</span>';
                            }
                        ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($game['completion_date']) || !empty($game['time_spent_hours'])): ?>
                <div class="bg-zinc-950 border-2 border-zinc-800 p-4 rounded-sm text-center">
                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-3">Conclusão</span>
                    <div class="space-y-2 text-sm">
                        <?php if (!empty($game['completion_date'])): ?>
                            <div>
                                <span class="block text-zinc-500 uppercase tracking-widest text-[10px] mb-1">Data</span>
                                <span class="font-bold text-white"><?php echo htmlspecialchars(date('d/m/Y', strtotime($game['completion_date']))); ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($game['time_spent_hours'])): ?>
                            <div>
                                <span class="block text-zinc-500 uppercase tracking-widest text-[10px] mb-1">Horas gastas</span>
                                <span class="font-bold text-white"><?php echo htmlspecialchars(number_format((float) $game['time_spent_hours'], 2, ',', '.')); ?> h</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <div class="w-full md:w-2/3 flex flex-col">
                
                <div>
                    <h3 class="text-xl font-bold text-white mb-2 uppercase tracking-tight">Descrição</h3>
                    <?php if (!empty($game['description'])): ?>
                        <div class="bg-zinc-950 border-2 border-zinc-800 p-5 rounded-sm">
                            <p class="text-zinc-300 leading-relaxed text-sm sm:text-base">
                                <?php 
                                    // 1. Troca tags de fechamento de parágrafo e quebras de linha HTML por \n reais
                                    $desc = str_ireplace(['</p>', '<br>', '<br/>', '<br />'], "\n", $game['description']);
                                    // 2. Remove qualquer outra tag HTML restante (como <b>, <i>, <p>)
                                    $desc = strip_tags($desc);
                                    // 3. Evita espaçamentos gigantescos (limita a no máximo 2 quebras de linha seguidas)
                                    $desc = preg_replace("/[\r\n]{3,}/", "\n\n", $desc);
                                    
                                    // 4. Converte de volta para visualização segura
                                    echo nl2br(htmlspecialchars(trim($desc))); 
                                ?>
                            </p>
                        </div>
                    <?php else: ?>
                        <div class="bg-zinc-950 border-2 border-zinc-800 p-5 rounded-sm">
                            <p class="text-zinc-600 italic">Nenhuma descrição disponível para este jogo.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6 flex-grow">
                    <h3 class="text-xl font-bold text-white mb-2 uppercase tracking-tight">Análise</h3>
                    
                    <?php if (isset($isOwner) && $isOwner): ?>
                        <form action="index.php?action=save_review" method="POST" class="space-y-4">
                            <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                            <textarea name="review" rows="6" class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors resize-y font-medium text-sm sm:text-base min-h-[160px]" placeholder="Escreva o que achou da experiência..."><?php echo htmlspecialchars($game['review'] ?? ''); ?></textarea>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Data de conclusão</span>
                                    <input type="date" name="completion_date" value="<?php echo htmlspecialchars($game['completion_date'] ?? ''); ?>" class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium text-sm">
                                </label>
                                <label class="block">
                                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Tempo gasto (horas)</span>
                                    <input type="number" name="time_spent_hours" min="0" step="0.25" value="<?php echo htmlspecialchars(isset($game['time_spent_hours']) ? (string) $game['time_spent_hours'] : ''); ?>" placeholder="Ex.: 15.5" class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium text-sm">
                                </label>
                            </div>
                            <label class="block">
                                <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Tags Personalizadas</span>
                                <input type="text" name="tags" value="<?php echo htmlspecialchars($currentTags); ?>" placeholder="Adicionar tags separadas por vírgula" class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium text-sm">
                                <span class="block mt-2 text-xs text-zinc-500">Exemplo: RPG, Coop, Relaxante</span>
                            </label>
                            <div>
                                <button type="submit" class="w-full sm:w-auto bg-violet-600 hover:bg-violet-500 text-white px-8 py-3 rounded-sm font-black uppercase tracking-widest text-sm transition-colors shadow-lg">Guardar Análise</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="bg-zinc-950 border-2 border-zinc-800 p-5 rounded-sm min-h-[150px]">
                            <?php if (!empty($game['review'])): ?>
                                <p class="text-zinc-300 leading-relaxed whitespace-pre-wrap text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($game['review'])); ?></p>
                            <?php else: ?>
                                <p class="text-zinc-600 italic">Este jogador ainda não escreveu uma análise para este jogo.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($gameTags)): ?>
                <div class="mt-6">
                    <h3 class="text-xl font-bold text-white mb-2 uppercase tracking-tight">Tags</h3>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($gameTags as $tag): ?>
                            <div class="inline-flex items-center gap-1 rounded-full border border-violet-500/30 bg-violet-600/15 px-3 py-1.5 text-sm font-semibold text-violet-300">
                                <a href="index.php?action=home&tag=<?php echo urlencode($tag['name']); ?>" class="inline-flex items-center gap-2 hover:text-white transition-colors">
                                    <span>#</span>
                                    <span><?php echo htmlspecialchars($tag['name']); ?></span>
                                </a>
                                <?php if (isset($isOwner) && $isOwner): ?>
                                    <form action="index.php?action=remove_custom_tag" method="POST" class="inline-flex">
                                        <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                                        <input type="hidden" name="tag_id" value="<?php echo htmlspecialchars($tag['id']); ?>">
                                        <button type="submit" class="ml-2 text-xs font-black uppercase tracking-widest text-amber-200/80 hover:text-red-300 transition-colors" title="Remover tag">x</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
        
        <?php endif; ?>
    </main>

    <?php if (isset($_SESSION['review_success'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            icon: 'success',
            title: '<?php echo $_SESSION['review_success']; ?>'
        });
    </script>
    <?php unset($_SESSION['review_success']); endif; ?>

</body>
</html>