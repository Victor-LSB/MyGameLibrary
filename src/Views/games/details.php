<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">
<?php
    $isOwner = $isOwner ?? false;
    $username_profile = $username_profile ?? '';
    $currentUserId = $currentUserId ?? null;
    $reviewComments = $reviewComments ?? [];

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

    if (!function_exists('mgl_review_avatar_path')) {
        function mgl_review_avatar_path($avatar) {
            if (empty($avatar)) return null;
            return str_starts_with($avatar, 'http') ? $avatar : './uploads/profile/' . basename($avatar);
        }
    }

    if (!function_exists('mgl_render_review_comment_avatar')) {
        function mgl_render_review_comment_avatar($user, $sizeClasses) {
            $path = mgl_review_avatar_path($user['avatar'] ?? null);
            if ($path) {
                return '<img src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($user['username']) . '" class="' . $sizeClasses . ' rounded-sm object-cover shrink-0 bg-zinc-950">';
            }
            $initial = substr($user['username'] ?? '?', 0, 1);
            return '<div class="' . $sizeClasses . ' rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">' . htmlspecialchars($initial) . '</div>';
        }
    }

    if (!function_exists('mgl_render_review_comment')) {
        function mgl_render_review_comment($comment, $currentUserId, $targetUserId, $isReply = false) {
            $isRemoved = $comment['removed_at'] !== null;
            $isAuthor = $currentUserId !== null && (int) $currentUserId === (int) $comment['author_id'];
            $isReviewOwner = $currentUserId !== null && (int) $currentUserId === (int) $targetUserId;
            $canDelete = !$isRemoved && ($isAuthor || $isReviewOwner);
            $canReply = !$isReply && !$isRemoved && $currentUserId !== null;
            $wrapClasses = $isReply ? 'flex gap-3 py-3 pl-10' : 'flex gap-3 py-4';

            ob_start();
            ?>
            <article id="review-comment-<?php echo (int) $comment['id']; ?>" class="<?php echo $wrapClasses; ?>" data-comment-id="<?php echo (int) $comment['id']; ?>" data-author-id="<?php echo (int) $comment['author_id']; ?>">
                <?php echo mgl_render_review_comment_avatar($comment, $isReply ? 'w-8 h-8' : 'w-10 h-10'); ?>
                <div class="comment-body flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="index.php?action=profile&u=<?php echo urlencode($comment['username']); ?>" class="text-white text-sm font-bold hover:text-violet-400 transition-colors">
                            <?php echo htmlspecialchars($comment['display_name'] ?: $comment['username']); ?>
                        </a>
                        <time class="text-zinc-500 text-xs"><?php echo htmlspecialchars($comment['created_at']); ?></time>
                    </div>
                    <p class="comment-content text-zinc-300 text-sm mt-0.5 whitespace-pre-wrap break-words"><?php echo $isRemoved ? '<span class="italic text-zinc-600">Comentário removido.</span>' : htmlspecialchars($comment['content']); ?></p>
                    <?php if ($canReply || $canDelete): ?>
                        <div class="flex items-center gap-4 mt-2">
                            <?php if ($canReply): ?>
                                <button type="button" class="comment-reply-btn text-zinc-500 hover:text-violet-400 text-xs font-bold uppercase tracking-wide transition-colors">Responder</button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <button type="button" class="comment-delete-btn text-zinc-500 hover:text-red-400 text-xs font-bold uppercase tracking-wide transition-colors">Apagar</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!$isReply): ?>
                        <?php $replies = $comment['replies'] ?? []; $repliesCount = count($replies); ?>
                        <button type="button" class="comment-toggle-replies-btn flex items-center gap-1.5 text-violet-400 hover:text-violet-300 text-xs font-bold uppercase tracking-wide transition-colors mt-4 <?php echo $repliesCount > 0 ? '' : 'hidden'; ?>" data-expanded="false">
                            <svg class="comment-toggle-chevron w-3.5 h-3.5 transition-transform duration-200" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd" /></svg>
                            Respostas (<span class="comment-replies-count"><?php echo $repliesCount; ?></span>)
                        </button>
                        <div class="comment-replies mt-3 space-y-1 border-l-2 border-zinc-800 hidden">
                            <?php foreach ($replies as $reply): ?>
                                <?php echo mgl_render_review_comment($reply, $currentUserId, $targetUserId, true); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
            <?php
            return ob_get_clean();
        }
    }
?>

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-5xl mx-auto px-6">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-black text-white tracking-tighter uppercase line-clamp-1 flex-1">
                <?php echo isset($game['title']) ? htmlspecialchars($game['title']) : 'Jogo não encontrado'; ?>
            </h1>
            <a href="<?php echo $isOwner ? 'index.php?action=home' : 'index.php?action=profile&u=' . urlencode($username_profile); ?>" class="bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm border-b-2 border-zinc-950 hover:border-zinc-900 transition-colors shrink-0">
                <?php echo $isOwner ? 'Voltar à Biblioteca' : 'Voltar ao Perfil'; ?>
            </a>
        </div>

        
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
                
                <div class="bg-zinc-950 border-2 border-zinc-800 p-4 rounded-sm text-center" id="statusCard">
                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-1">Status Atual</span>
                    <?php if ($isOwner): ?>
                        <?php $currentStatus = $game['status'] ?? ''; ?>
                        <span id="statusLabel" class="text-xl font-bold <?php
                            echo isset($game['status']) ? match($game['status']) {
                                'Jogando' => 'text-blue-400',
                                'Completo' => 'text-emerald-400',
                                'Dropado' => 'text-amber-400',
                                default => 'text-white'
                            } : 'text-zinc-600';
                        ?> uppercase tracking-tight block mb-3"><?php echo isset($game['status']) ? htmlspecialchars($game['status']) : 'N/A'; ?></span>

                        <div class="grid grid-cols-3 gap-1.5">
                            <form class="detailStatusForm" data-status="Jogando">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                                <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating'] ?? ''); ?>">
                                <input type="hidden" name="status" value="Jogando">
                                <button type="submit" class="detail-status-btn w-full text-[10px] font-bold uppercase py-2 bg-blue-600 text-white rounded-sm hover:bg-blue-500 transition-all duration-200 <?php echo $currentStatus === 'Jogando' ? 'is-active' : 'is-inactive'; ?>">Jogando</button>
                            </form>
                            <form class="detailStatusForm" data-status="Zerado">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                                <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating'] ?? ''); ?>">
                                <input type="hidden" name="status" value="Zerado">
                                <button type="submit" class="detail-status-btn w-full text-[10px] font-bold uppercase py-2 bg-emerald-600 text-white rounded-sm hover:bg-emerald-500 transition-all duration-200 <?php echo $currentStatus === 'Zerado' ? 'is-active' : 'is-inactive'; ?>">Zerado</button>
                            </form>
                            <form class="detailStatusForm" data-status="Dropado">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                                <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                                <input type="hidden" name="rating" value="<?php echo htmlspecialchars($game['rating'] ?? ''); ?>">
                                <input type="hidden" name="status" value="Dropado">
                                <button type="submit" class="detail-status-btn w-full text-[10px] font-bold uppercase py-2 bg-amber-600 text-white rounded-sm hover:bg-amber-500 transition-all duration-200 <?php echo $currentStatus === 'Dropado' ? 'is-active' : 'is-inactive'; ?>">Dropado</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <span class="text-xl font-bold <?php
                            echo isset($game['status']) ? match($game['status']) {
                                'Jogando' => 'text-blue-400',
                                'Completo' => 'text-emerald-400',
                                'Dropado' => 'text-amber-400',
                                default => 'text-white'
                            } : 'text-zinc-600';
                        ?> uppercase tracking-tight"><?php echo isset($game['status']) ? htmlspecialchars($game['status']) : 'N/A'; ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($isOwner || (isset($game['rating']) && $game['rating'])): ?>
                <div class="bg-zinc-950 border-2 border-zinc-800 p-4 rounded-sm text-center">
                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-1">A Sua Nota</span>
                    <?php if ($isOwner): ?>
                        <form id="detailRatingForm">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                            <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($game['status'] ?? ''); ?>">
                            <div class="flex gap-1 w-full">
                                <?php for($i=1; $i<=5; $i++): ?>
                                <label class="flex-1 cursor-pointer">
                                    <input type="radio" name="rating" value="<?php echo $i; ?>" class="peer sr-only" <?php if (isset($game['rating']) && $game['rating'] == $i) echo 'checked'; ?>>
                                    <div class="py-1.5 text-center bg-zinc-900 text-zinc-600 peer-checked:bg-amber-400 peer-checked:text-zinc-900 border border-zinc-800 peer-checked:border-amber-400 font-black rounded-sm text-sm hover:bg-zinc-800 hover:text-zinc-300 transition-all">
                                        <?php echo $i; ?>
                                    </div>
                                </label>
                                <?php endfor; ?>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-3xl text-amber-400 font-black tracking-widest">
                            <?php
                                for($i=1; $i<=5; $i++) {
                                    echo $i <= $game['rating'] ? '★' : '<span class="text-zinc-800">★</span>';
                                }
                            ?>
                        </div>
                    <?php endif; ?>
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
                        <?php
                            // 1. Troca tags de fechamento de parágrafo e quebras de linha HTML por \n reais
                            $desc = str_ireplace(['</p>', '<br>', '<br/>', '<br />'], "\n", $game['description']);
                            // 2. Remove qualquer outra tag HTML restante (como <b>, <i>, <p>)
                            $desc = strip_tags($desc);
                            // 3. Evita espaçamentos gigantescos (limita a no máximo 2 quebras de linha seguidas)
                            $desc = preg_replace("/[\r\n]{3,}/", "\n\n", $desc);
                            $desc = trim($desc);

                            // Descrições muito longas começam recolhidas, com um botão para expandir.
                            $isLongDescription = mb_strlen($desc) > 500;
                        ?>
                        <div class="bg-zinc-950 border-2 border-zinc-800 p-5 rounded-sm">
                            <div id="gameDescription" class="game-description<?php echo $isLongDescription ? ' is-collapsed' : ''; ?>">
                                <p class="text-zinc-300 leading-relaxed text-sm sm:text-base">
                                    <?php echo nl2br(htmlspecialchars($desc)); ?>
                                </p>
                            </div>
                            <?php if ($isLongDescription): ?>
                                <button type="button" id="toggleDescriptionBtn" class="mt-3 text-violet-400 hover:text-violet-300 font-bold uppercase tracking-wide text-xs transition-colors">
                                    Mostrar mais
                                </button>
                            <?php endif; ?>
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
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                            <input type="hidden" name="game_id" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                            <textarea name="review" rows="6" class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors resize-y font-medium text-sm sm:text-base min-h-[160px]" placeholder="Escreva o que achou da experiência..."><?php echo htmlspecialchars($game['review'] ?? ''); ?></textarea>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label class="block">
                                    <span class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Data de conclusão</span>
                                    <input type="date" name="completion_date" max="<?php echo date('Y-m-d'); ?>" value="<?php echo htmlspecialchars($game['completion_date'] ?? ''); ?>" class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium text-sm">
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
                                <button type="submit" class="w-full sm:w-auto bg-violet-600 hover:bg-violet-500 text-white px-8 py-3 rounded-sm font-black uppercase tracking-widest text-sm transition-colors shadow-lg">Salvar Análise</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="bg-zinc-950 border-2 border-zinc-800 p-5 rounded-sm min-h-[150px]">
                            <?php if (!empty($game['review'])): ?>
                                <p class="text-zinc-300 leading-relaxed whitespace-pre-wrap text-sm sm:text-base"><?php echo nl2br(htmlspecialchars($game['review'] ?? '')); ?></p>
                            <?php else: ?>
                                <p class="text-zinc-600 italic">Este jogador ainda não escreveu uma análise para este jogo.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="mt-6" id="reviewComments" data-target-user-id="<?php echo (int) ($target_user_id ?? 0); ?>" data-game-id="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                    <h3 class="text-xl font-bold text-white mb-2 uppercase tracking-tight">
                        Comentários (<span id="reviewCommentsCount"><?php echo count($reviewComments); ?></span>)
                    </h3>

                    <?php if ($currentUserId !== null): ?>
                        <form id="newReviewCommentForm" class="flex flex-col sm:flex-row gap-3 mb-6">
                            <textarea name="content" maxlength="1000" rows="2" required placeholder="Comente esta análise..." class="flex-1 bg-zinc-950 border-2 border-zinc-800 rounded-sm p-3 text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-violet-500 resize-none"></textarea>
                            <button type="submit" class="shrink-0 bg-violet-600 hover:bg-violet-500 text-white px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors self-start sm:self-end">Comentar</button>
                        </form>
                    <?php else: ?>
                        <p class="text-zinc-500 text-sm mb-6">
                            <a href="index.php?action=login" class="text-violet-400 hover:underline">Faça login</a> para comentar nesta análise.
                        </p>
                    <?php endif; ?>

                    <div id="reviewCommentsList" class="divide-y divide-zinc-800">
                        <?php foreach ($reviewComments as $comment): ?>
                            <?php echo mgl_render_review_comment($comment, $currentUserId, $target_user_id ?? 0); ?>
                        <?php endforeach; ?>
                    </div>
                    <p id="noReviewCommentsMsg" class="text-zinc-500 text-sm py-6 text-center <?php echo !empty($reviewComments) ? 'hidden' : ''; ?>">
                        Seja o primeiro a comentar esta análise.
                    </p>
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
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
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

    <?php if (isset($game) && $game): ?>
    <template id="reviewReplyFormTemplate">
        <form class="comment-reply-form flex flex-col sm:flex-row gap-2 mt-3 pl-10">
            <textarea name="content" maxlength="1000" rows="1" required placeholder="Escreva uma resposta..." class="flex-1 bg-zinc-950 border-2 border-zinc-800 rounded-sm p-2 text-xs text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-violet-500 resize-none"></textarea>
            <div class="flex gap-2 shrink-0 self-start sm:self-end">
                <button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white px-4 py-2 rounded-sm font-black uppercase tracking-wide text-[10px] transition-colors">Responder</button>
                <button type="button" class="comment-reply-cancel bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-3 py-2 rounded-sm font-black uppercase tracking-wide text-[10px] transition-colors">Cancelar</button>
            </div>
        </form>
    </template>
    <?php endif; ?>

    <?php if ($isOwner && isset($game) && $game): ?>
    <div id="detailCompletionModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 backdrop-blur-sm px-4">
        <div class="w-full max-w-lg rounded-sm border-2 border-zinc-800 bg-zinc-900 shadow-2xl shadow-black/60">
            <div class="border-b-2 border-zinc-800 px-6 py-5 border-l-4 border-l-violet-500">
                <h3 class="text-2xl font-black uppercase tracking-tight text-white">Marcar como Zerado</h3>
                <p class="mt-2 text-sm text-zinc-400 font-medium">Adicione os detalhes da conclusão antes de salvar o status.</p>
            </div>

            <form id="detailCompletionForm" class="px-6 py-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
                <input type="hidden" name="game_id" id="detailModalGameId" value="<?php echo htmlspecialchars($game_id ?? ''); ?>">
                <input type="hidden" name="status" value="Zerado">

                <div class="grid grid-cols-1 gap-4">
                    <label class="block">
                        <span class="mb-2 block text-xs font-black uppercase tracking-widest text-zinc-500">Data de Conclusão</span>
                        <input type="datetime-local" name="completion_date" id="detailModalCompletionDate" class="w-full rounded-sm border-2 border-zinc-800 bg-zinc-950 px-4 py-3 text-zinc-100 font-medium outline-none transition-colors focus:border-violet-500">
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-xs font-black uppercase tracking-widest text-zinc-500">Horas Jogadas</span>
                        <input type="number" min="0" step="0.25" name="time_spent_hours" id="detailModalTimeSpentHours" value="<?php echo htmlspecialchars(isset($game['time_spent_hours']) ? (string) $game['time_spent_hours'] : ''); ?>" class="w-full rounded-sm border-2 border-zinc-800 bg-zinc-950 px-4 py-3 text-zinc-100 font-medium outline-none transition-colors focus:border-violet-500" placeholder="Ex.: 24.5">
                    </label>
                </div>

                <div class="mt-6 flex items-center justify-end gap-3">
                    <button type="button" id="detailCancelCompletionModal" class="rounded-sm border-2 border-zinc-800 px-4 py-2 text-sm font-bold uppercase tracking-widest text-zinc-300 transition-colors hover:border-zinc-600 hover:text-white">Cancelar</button>
                    <button type="submit" class="rounded-sm bg-violet-600 px-5 py-2.5 text-sm font-black uppercase tracking-widest text-white transition-colors hover:bg-violet-500">Salvar Zerado</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

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

    <style>
        .game-description {
            overflow: hidden;
            transition: max-height 0.4s ease;
        }
        .game-description.is-collapsed {
            max-height: 9em;
            position: relative;
        }
        .game-description.is-collapsed::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 3em;
            background: linear-gradient(to bottom, transparent, #09090b);
            pointer-events: none;
        }
    </style>
    <script>
        (function () {
            var toggleBtn = document.getElementById('toggleDescriptionBtn');
            var description = document.getElementById('gameDescription');
            if (!toggleBtn || !description) return;

            var collapsedHeight = '9em';

            toggleBtn.addEventListener('click', function () {
                var isCollapsed = description.classList.contains('is-collapsed');

                if (isCollapsed) {
                    // Expandir: mede a altura real do conteúdo e anima suavemente até lá
                    var fullHeight = description.scrollHeight + 'px';
                    description.classList.remove('is-collapsed');
                    description.style.maxHeight = fullHeight;
                    toggleBtn.textContent = 'Mostrar menos';
                } else {
                    // Recolher: anima suavemente de volta até a altura reduzida
                    description.style.maxHeight = collapsedHeight;
                    description.classList.add('is-collapsed');
                    toggleBtn.textContent = 'Mostrar mais';
                }
            });
        })();
    </script>

    <?php if ($isOwner && isset($game) && $game): ?>
    <?php if (!isset($_SESSION['review_success'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php endif; ?>
    <script src="./assets/js/details-status.js"></script>
    <?php endif; ?>
    <?php if (isset($game) && $game): ?>
    <script src="./assets/js/review-comments.js"></script>
    <?php endif; ?>
    <script src="./assets/js/notifications.js"></script>
</body>
</html>