<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-6 pb-12">
        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6">Atividade de quem você segue</h2>

                <?php if (!empty($activities)): ?>
                    <div class="flex flex-col gap-3">
                        <?php foreach ($activities as $item): ?>
                            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-lg p-4 flex items-center gap-4">
                                <a href="index.php?action=profile&u=<?php echo urlencode($item['actor_username']); ?>" class="shrink-0">
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
                                    <p class="text-zinc-500 text-xs mt-1"><?php echo date('d/m/Y H:i', strtotime($item['created_at'])); ?></p>
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
</body>
</html>
