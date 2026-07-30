<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-6 pb-12">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-4 mb-6 flex-wrap">
                    <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3">Atividade de quem você segue</h2>

                    <?php if (!empty($activities)): ?>
                        <select id="feedTypeFilter" class="bg-zinc-900 border-2 border-zinc-800 text-white text-xs font-bold uppercase tracking-wide rounded-sm px-3 py-2 focus:outline-none focus:border-violet-500 cursor-pointer">
                            <option value="">Todas as atividades</option>
                            <option value="game_added">Jogos adicionados</option>
                            <option value="status_changed">Mudanças de status</option>
                            <option value="review_posted">Análises publicadas</option>
                        </select>
                    <?php endif; ?>
                </div>

                <?php if (!empty($activities)): ?>
                    <?php
                        $feedIcons = [
                            'game_added' => ['badge' => 'feed-icon-added', 'border' => 'border-l-amber-600', 'svg' => '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>'],
                            'status_Jogando' => ['badge' => 'feed-icon-playing', 'border' => 'border-l-blue-600', 'svg' => '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>'],
                            'status_Zerado' => ['badge' => 'feed-icon-completed', 'border' => 'border-l-emerald-600', 'svg' => '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7"/></svg>'],
                            'status_Dropado' => ['badge' => 'feed-icon-dropped', 'border' => 'border-l-red-600', 'svg' => '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>'],
                            'review_posted' => ['badge' => 'feed-icon-review', 'border' => 'border-l-violet-600', 'svg' => '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.6L22 9.2l-5 4.9 1.2 7.1L12 17.8 5.8 21.2 7 14.1l-5-4.9 7.1-1.6L12 2z"/></svg>'],
                        ];
                        $heartSvg = '<svg width="100%" height="100%" viewBox="0 0 24 24" fill="currentColor"><path d="M12 21s-7.5-4.6-10-9.1C.5 8.4 2.3 5 6 5c2 0 3.5 1 4.5 2.5C11.5 6 13 5 15 5c3.7 0 5.5 3.4 4 6.9-2.5 4.5-10 9.1-10 9.1z"/></svg>';
                    ?>
                    <div class="flex flex-col gap-3" id="feedList">
                        <?php foreach ($activities as $index => $item): ?>
                            <?php
                                $iconKey = $item['type'] === 'status_changed' ? 'status_' . $item['extra'] : $item['type'];
                                $icon = $feedIcons[$iconKey] ?? $feedIcons['status_Jogando'];
                                $isLiked = !empty($item['liked_by_me']);
                            ?>
                            <div class="feed-card bg-zinc-900 border-2 border-zinc-800 border-l-4 <?php echo $icon['border']; ?> rounded-sm shadow-lg p-4 flex items-center gap-4 hover:border-zinc-700 hover:-translate-y-0.5 transition-all duration-200"
                                 style="animation-delay: <?php echo min($index * 40, 400); ?>ms"
                                 data-activity-type="<?php echo htmlspecialchars($item['type']); ?>">

                                <div class="relative shrink-0">
                                    <a href="index.php?action=profile&u=<?php echo urlencode($item['actor_username']); ?>">
                                        <?php if (!empty($item['actor_avatar'])): ?>
                                            <?php
                                                $actorAvatarVal = $item['actor_avatar'];
                                                $actorAvatarPath = str_starts_with($actorAvatarVal, 'http') ? $actorAvatarVal : './uploads/profile/' . basename($actorAvatarVal);
                                            ?>
                                            <img src="<?php echo htmlspecialchars($actorAvatarPath); ?>" alt="<?php echo htmlspecialchars($item['actor_username']); ?>" class="w-11 h-11 rounded-sm object-cover bg-zinc-950">
                                        <?php else: ?>
                                            <div class="w-11 h-11 rounded-sm bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">
                                                <?php echo substr($item['actor_username'], 0, 1); ?>
                                            </div>
                                        <?php endif; ?>
                                    </a>
                                    <div class="absolute -bottom-1.5 -right-1.5 w-5 h-5 rounded-full border-2 border-zinc-900 flex items-center justify-center <?php echo $icon['badge']; ?>">
                                        <span class="w-2.5 h-2.5 block"><?php echo $icon['svg']; ?></span>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-zinc-300">
                                        <a href="index.php?action=profile&u=<?php echo urlencode($item['actor_username']); ?>" class="text-white font-bold hover:text-violet-400 transition-colors">
                                            <?php echo htmlspecialchars($item['actor_display_name'] ?: $item['actor_username']); ?>
                                        </a>
                                        <?php if ($item['type'] === 'game_added'): ?>
                                            adicionou
                                        <?php elseif ($item['type'] === 'status_changed'): ?>
                                            marcou como <span class="text-violet-400 font-bold"><?php echo htmlspecialchars($item['extra']); ?></span>
                                        <?php elseif ($item['type'] === 'review_posted'): ?>
                                            publicou uma análise de
                                        <?php endif; ?>
                                        <?php if (!empty($item['game_title'])): ?>
                                            <a href="index.php?action=details&id=<?php echo (int) $item['game_id']; ?>&u=<?php echo urlencode($item['actor_username']); ?>" class="text-violet-400 font-bold hover:text-violet-300 transition-colors">
                                                <?php echo htmlspecialchars($item['game_title']); ?>
                                            </a>
                                        <?php endif; ?>
                                    </p>
                                    <div class="flex items-center gap-3 mt-1.5">
                                        <p class="text-zinc-500 text-xs"><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></p>
                                        <button type="button" class="feed-like-btn flex items-center gap-1 text-xs font-bold px-2 py-1 rounded-sm transition-colors <?php echo $isLiked ? 'is-liked' : 'text-zinc-500 hover:text-pink-400 hover:bg-zinc-800'; ?>" data-activity-id="<?php echo (int) $item['id']; ?>">
                                            <span class="w-3.5 h-3.5 block"><?php echo $heartSvg; ?></span>
                                            <span class="feed-like-count"><?php echo (int) $item['like_count']; ?></span>
                                        </button>
                                    </div>
                                </div>

                                <?php if (!empty($item['game_cover'])): ?>
                                    <a href="index.php?action=details&id=<?php echo (int) $item['game_id']; ?>&u=<?php echo urlencode($item['actor_username']); ?>" class="shrink-0 hidden sm:block">
                                        <img src="<?php echo htmlspecialchars($item['game_cover']); ?>" alt="<?php echo htmlspecialchars($item['game_title']); ?>" class="w-12 h-16 object-cover rounded-sm border border-zinc-800">
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-12 text-center shadow-xl">
                        <p class="text-zinc-500 font-medium">Ainda não há atividade por aqui. Siga outros jogadores para ver o que eles andam a jogar.</p>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($followSuggestions)): ?>
                <aside class="w-full lg:w-72 shrink-0">
                    <h2 class="text-lg font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-4">Sugestões para seguir</h2>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl divide-y divide-zinc-800">
                        <?php foreach ($followSuggestions as $suggestion): ?>
                            <a href="index.php?action=profile&u=<?php echo urlencode($suggestion['username']); ?>" class="flex items-center gap-3 p-3 hover:bg-zinc-800/60 transition-colors">
                                <?php if (!empty($suggestion['avatar'])): ?>
                                    <?php
                                        $suggAvatarVal = $suggestion['avatar'];
                                        $suggAvatarPath = str_starts_with($suggAvatarVal, 'http') ? $suggAvatarVal : './uploads/profile/' . basename($suggAvatarVal);
                                    ?>
                                    <img src="<?php echo htmlspecialchars($suggAvatarPath); ?>" alt="<?php echo htmlspecialchars($suggestion['username']); ?>" class="w-10 h-10 rounded-sm object-cover shrink-0 bg-zinc-950">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">
                                        <?php echo substr($suggestion['username'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="text-white text-sm font-bold truncate"><?php echo htmlspecialchars($suggestion['display_name'] ?: $suggestion['username']); ?></p>
                                    <p class="text-zinc-500 text-xs truncate"><?php echo (int) $suggestion['followers_count']; ?> seguidor<?php echo $suggestion['followers_count'] == 1 ? '' : 'es'; ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </aside>
            <?php endif; ?>
        </div>
    </main>

    <script src="./assets/js/notifications.js"></script>
    <script src="./assets/js/feed.js"></script>
</body>
</html>
