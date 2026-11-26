<?php
    $extraStyles = ['assets/css/social.css'];
    require_once __DIR__ . '/../header.php';

    $feedGroups = $feedGroups ?? [];
    $followSuggestions = $followSuggestions ?? [];
    $currentUserId = $_SESSION['user_id'] ?? null;

    // Verbo de cada tipo de atividade. 'class' pinta só a palavra do verbo (informação, não decoração).
    $statusVerbs = [
        'Jogando'   => ['class' => 'st-playing', 'text' => 'começou a jogar'],
        'Zerado'    => ['class' => 'st-done',    'text' => 'zerou'],
        'Dropado'   => ['class' => 'st-dropped', 'text' => 'dropou'],
        'Platina'   => ['class' => 'st-plat',    'text' => 'platinou'],
        'Quero jogar' => ['class' => 'st-want',  'text' => 'quer jogar'],
    ];

    $gameUrl = function (array $item): string {
        return 'index.php?action=details&id=' . (int) $item['game_id'] . '&u=' . urlencode($item['actor_username']);
    };
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6 pb-12">
        <div class="feed-layout">
            <?php if (!empty($me)): $meAvatar = mgl_avatar_url($me['avatar'] ?? null); ?>
                <aside class="feed-me" aria-label="Seu resumo">
                    <a href="<?php echo htmlspecialchars(mgl_url('profile', ['u' => $me['username']])); ?>" class="flex items-center gap-3 group">
                        <?php if ($meAvatar): ?>
                            <img src="<?php echo htmlspecialchars($meAvatar); ?>" alt="" class="w-14 h-14 rounded-sm object-cover bg-zinc-950 border-2 border-zinc-800">
                        <?php else: ?>
                            <span class="w-14 h-14 rounded-sm bg-zinc-800 flex items-center justify-center text-zinc-500 text-xl font-black uppercase"><?php echo htmlspecialchars(mb_substr($me['username'], 0, 1)); ?></span>
                        <?php endif; ?>
                        <span class="min-w-0">
                            <span class="block text-white font-black truncate group-hover:text-violet-300"><?php echo htmlspecialchars($me['display_name'] ?: $me['username']); ?></span>
                            <span class="block text-xs text-zinc-500 truncate">@<?php echo htmlspecialchars($me['username']); ?></span>
                        </span>
                    </a>
                    <dl class="mt-5 grid grid-cols-2 gap-2 text-center">
                        <div class="bg-zinc-950 border border-zinc-800 rounded-sm py-2"><dd class="text-lg font-black text-white"><?php echo (int) ($meStats['games'] ?? 0); ?></dd><dt class="text-[10px] uppercase tracking-widest text-zinc-500">Jogos</dt></div>
                        <div class="bg-zinc-950 border border-zinc-800 rounded-sm py-2"><dd class="text-lg font-black text-emerald-400"><?php echo (int) ($meStats['completed_year'] ?? 0); ?></dd><dt class="text-[10px] uppercase tracking-widest text-zinc-500">Zerados <?php echo date('Y'); ?></dt></div>
                        <div class="bg-zinc-950 border border-zinc-800 rounded-sm py-2"><dd class="text-lg font-black text-white"><?php echo (int) ($meStats['followers'] ?? 0); ?></dd><dt class="text-[10px] uppercase tracking-widest text-zinc-500">Seguidores</dt></div>
                        <div class="bg-zinc-950 border border-zinc-800 rounded-sm py-2"><dd class="text-lg font-black text-white"><?php echo (int) ($meStats['following'] ?? 0); ?></dd><dt class="text-[10px] uppercase tracking-widest text-zinc-500">Seguindo</dt></div>
                    </dl>
                    <nav class="mt-5 grid gap-1 text-sm font-bold">
                        <a href="index.php?action=home" class="px-3 py-2 rounded-sm text-zinc-300 hover:bg-zinc-800 hover:text-white">Minha biblioteca</a>
                        <a href="index.php?action=year_review" class="px-3 py-2 rounded-sm text-zinc-300 hover:bg-zinc-800 hover:text-white">Retrospectiva <?php echo date('Y'); ?></a>
                        <a href="index.php?action=dashboard" class="px-3 py-2 rounded-sm text-zinc-300 hover:bg-zinc-800 hover:text-white">Dashboard</a>
                    </nav>
                </aside>
            <?php endif; ?>

            <section aria-labelledby="feedTitle" class="feed-main">
                <div class="feed-head">
                    <h1 id="feedTitle" class="feed-title">Feed</h1>

                    <?php if (!empty($feedGroups)): ?>
                        <div class="feed-chips" role="group" aria-label="Filtrar por tipo de atividade">
                            <button type="button" class="feed-chip" data-feed-filter="" aria-pressed="true">Tudo</button>
                            <button type="button" class="feed-chip" data-feed-filter="game_added" aria-pressed="false">Jogos novos</button>
                            <button type="button" class="feed-chip" data-feed-filter="status_changed" aria-pressed="false">Status</button>
                            <button type="button" class="feed-chip" data-feed-filter="review_posted" aria-pressed="false">Análises</button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($feedGroups)): ?>
                    <div id="feedList">
                        <?php
                            $currentDay = null;
                            $dayOpen = false;
                        ?>
                        <?php foreach ($feedGroups as $group): ?>
                            <?php
                                $items = $group['items'];
                                $first = $items[0];          // atividade mais recente do grupo
                                $count = count($items);
                                $type = $group['type'];
                                $isGroup = $count > 1;
                                $isReview = $type === 'review_posted';

                                // Separador de dia: abre uma seção nova quando o dia muda
                                if ($group['day'] !== $currentDay) {
                                    if ($dayOpen) echo '</div></section>';
                                    $currentDay = $group['day'];
                                    $dayOpen = true;
                                    echo '<section class="feed-day"><h2 class="feed-day-title">' . htmlspecialchars(mgl_day_label($first['created_at'])) . '</h2><div class="feed-day__panel">';
                                }

                                // Quem fez e o que fez
                                $isMe = $currentUserId !== null && (int) $first['actor_id'] === (int) $currentUserId;
                                $actorName = $isMe ? 'Você' : ($first['actor_display_name'] ?: $first['actor_username']);
                                $actorAvatar = mgl_avatar_url($first['actor_avatar']);
                                $profileUrl = 'index.php?action=profile&u=' . urlencode($first['actor_username']);

                                $verbClass = '';
                                $verbText = '';
                                $suffix = '';
                                if ($type === 'game_added' && $group['extra'] === 'Quero jogar') {
                                    $verbClass = $statusVerbs['Quero jogar']['class'];
                                    $verbText = $statusVerbs['Quero jogar']['text'];
                                } elseif ($type === 'game_added') {
                                    $verbText = 'adicionou';
                                } elseif ($isReview) {
                                    $verbText = 'publicou uma análise de';
                                } elseif (isset($statusVerbs[$group['extra']])) {
                                    $verbClass = $statusVerbs[$group['extra']]['class'];
                                    $verbText = $statusVerbs[$group['extra']]['text'];
                                } else {
                                    $verbText = 'marcou';
                                    $suffix = ' como <strong class="feed-game">' . htmlspecialchars((string) $group['extra']) . '</strong>';
                                }

                                $liked = !empty($first['liked_by_me']);
                                $likeCount = (int) $first['like_count'];
                                $title = (string) ($first['game_title'] ?? '');
                                $itemClass = 'feed-item' . ($isGroup ? ' feed-item--group' : '') . ($isReview ? ' feed-item--review' : '');
                            ?>
                            <article id="act-<?php echo (int) $first['id']; ?>" class="<?php echo $itemClass; ?>" data-activity-type="<?php echo htmlspecialchars($type); ?>">
                                <?php if (!$isGroup): ?>
                                    <?php if (!empty($first['game_id'])): ?>
                                        <a href="<?php echo htmlspecialchars($gameUrl($first)); ?>" class="feed-cover<?php echo empty($first['game_cover']) ? ' feed-cover--empty' : ''; ?>" tabindex="-1" aria-hidden="true">
                                            <?php if (!empty($first['game_cover'])): ?>
                                                <img src="<?php echo htmlspecialchars($first['game_cover']); ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(mb_substr($title, 0, 1)); ?>
                                            <?php endif; ?>
                                        </a>
                                    <?php else: ?>
                                        <div class="feed-cover feed-cover--empty" aria-hidden="true">?</div>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <div class="feed-main">
                                    <p class="feed-line">
                                        <a href="<?php echo htmlspecialchars($profileUrl); ?>" class="feed-actor">
                                            <?php if ($actorAvatar): ?>
                                                <img src="<?php echo htmlspecialchars($actorAvatar); ?>" alt="" loading="lazy">
                                            <?php else: ?>
                                                <span class="feed-actor__initial"><?php echo htmlspecialchars(mb_substr($first['actor_username'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($actorName); ?>
                                        </a>
                                        <?php if ($verbClass): ?>
                                            <span class="<?php echo $verbClass; ?>"><?php echo $verbText; ?></span>
                                        <?php else: ?>
                                            <?php echo $verbText; ?>
                                        <?php endif; ?>
                                        <?php if ($isGroup): ?>
                                            <strong class="feed-game"><?php echo $count; ?> jogos</strong>
                                        <?php elseif (!empty($first['game_id']) && $title !== ''): ?>
                                            <a href="<?php echo htmlspecialchars($gameUrl($first)); ?>" class="feed-game"><?php echo htmlspecialchars($title); ?></a>
                                        <?php else: ?>
                                            <span class="feed-gone">um jogo que foi removido</span>
                                        <?php endif; ?>
                                        <?php echo $suffix; ?>
                                    </p>

                                    <?php if ($isReview): ?>
                                        <?php
                                            $rating = (int) ($first['rating'] ?? 0);
                                            $rating = max(0, min(5, $rating));
                                            $reviewFull = trim((string) ($first['review_excerpt'] ?? ''));
                                            $excerpt = mb_strimwidth($reviewFull, 0, 280, '…');
                                            $truncated = (int) ($first['review_length'] ?? 0) > 280;
                                        ?>
                                        <?php if ($rating > 0): ?>
                                            <div class="feed-rating" aria-label="Nota <?php echo $rating; ?> de 5">
                                                <span class="feed-stars" aria-hidden="true"><?php echo str_repeat('★', $rating); ?><span class="feed-stars__off"><?php echo str_repeat('★', 5 - $rating); ?></span></span>
                                                <span><?php echo $rating; ?>/5</span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($excerpt !== ''): ?>
                                            <p class="feed-excerpt"><?php echo htmlspecialchars($excerpt); ?></p>
                                            <?php if ($truncated && !empty($first['game_id'])): ?>
                                                <a href="<?php echo htmlspecialchars($gameUrl($first)); ?>" class="feed-readmore">Ler análise completa</a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($isGroup): ?>
                                        <?php $shown = array_slice($items, 0, 7); $hidden = $count - count($shown); ?>
                                        <div class="feed-strip">
                                            <?php foreach ($shown as $groupItem): ?>
                                                <a href="<?php echo htmlspecialchars($gameUrl($groupItem)); ?>" class="feed-cover<?php echo empty($groupItem['game_cover']) ? ' feed-cover--empty' : ''; ?>" title="<?php echo htmlspecialchars((string) $groupItem['game_title']); ?>">
                                                    <?php if (!empty($groupItem['game_cover'])): ?>
                                                        <img src="<?php echo htmlspecialchars($groupItem['game_cover']); ?>" alt="<?php echo htmlspecialchars((string) $groupItem['game_title']); ?>" loading="lazy">
                                                    <?php else: ?>
                                                        <?php echo htmlspecialchars(mb_substr((string) $groupItem['game_title'], 0, 1)); ?>
                                                    <?php endif; ?>
                                                </a>
                                            <?php endforeach; ?>
                                            <?php if ($hidden > 0): ?>
                                                <span class="feed-more" aria-label="e mais <?php echo $hidden; ?> jogos">+<?php echo $hidden; ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="feed-meta">
                                        <time datetime="<?php echo htmlspecialchars(date('c', strtotime($first['created_at']))); ?>" title="<?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($first['created_at']))); ?>"><?php echo htmlspecialchars(mgl_time_ago($first['created_at'])); ?></time>
                                        <button type="button" class="feed-like<?php echo $liked ? ' is-liked' : ''; ?>" data-activity-id="<?php echo (int) $first['id']; ?>" aria-pressed="<?php echo $liked ? 'true' : 'false'; ?>" aria-label="Curtir">
                                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 21s-7.5-4.6-10-9.1C.5 8.4 2.3 5 6 5c2 0 3.5 1 4.5 2.5C11.5 6 13 5 15 5c3.7 0 5.5 3.4 4 6.9-2.5 4.5-10 9.1-10 9.1z"/></svg>
                                            <span class="feed-like__count"><?php echo $likeCount > 0 ? $likeCount : ''; ?></span>
                                        </button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                        <?php if ($dayOpen) echo '</div></section>'; ?>
                    </div>

                    <?php if (!empty($feedHasMore)): ?>
                        <?php $lastGroup = end($feedGroups); $lastId = (int) ($lastGroup['items'][0]['id'] ?? 0); ?>
                        <div class="mt-8 text-center">
                            <a href="index.php?action=feed&amp;limit=<?php echo (int) $feedNextLimit; ?>#act-<?php echo $lastId; ?>" class="btn btn-secondary">Carregar mais</a>
                        </div>
                    <?php endif; ?>

                    <p id="feedFilterEmpty" class="feed-filter-empty" hidden>Nada desse tipo por aqui ainda.</p>
                <?php else: ?>
                    <div class="feed-empty">
                        <strong>Seu feed está vazio</strong>
                        Siga outros jogadores para ver o que eles andam adicionando, zerando e analisando. Dá pra achá-los pela busca no topo da página.
                    </div>
                <?php endif; ?>
            </section>

                <aside class="feed-aside" aria-labelledby="suggestTitle">
                    <h2 id="suggestTitle" class="feed-aside__title">Quem seguir</h2>
                    <?php if (empty($followSuggestions)): ?>
                        <p class="mt-3 text-sm text-zinc-500 leading-relaxed">Você já segue todo mundo que a gente conhece por aqui. Use a busca de usuários no topo para encontrar mais gente.</p>
                    <?php endif; ?>
                    <?php foreach ($followSuggestions as $suggestion): ?>
                        <?php $suggAvatar = mgl_avatar_url($suggestion['avatar'] ?? null); ?>
                        <div class="person">
                            <a href="index.php?action=profile&u=<?php echo urlencode($suggestion['username']); ?>" class="person__who">
                                <?php if ($suggAvatar): ?>
                                    <img src="<?php echo htmlspecialchars($suggAvatar); ?>" alt="" class="person__avatar" loading="lazy">
                                <?php else: ?>
                                    <div class="person__avatar person__avatar--initial"><?php echo htmlspecialchars(mb_substr($suggestion['username'], 0, 1)); ?></div>
                                <?php endif; ?>
                                <div class="person__text">
                                    <span class="person__name"><?php echo htmlspecialchars($suggestion['display_name'] ?: $suggestion['username']); ?></span>
                                    <span class="person__sub"><?php echo (int) $suggestion['followers_count']; ?> seguidor<?php echo $suggestion['followers_count'] == 1 ? '' : 'es'; ?></span>
                                </div>
                            </a>
                            <button type="button" class="fbtn fbtn--follow" data-follow-user="<?php echo (int) $suggestion['id']; ?>" data-following="false" aria-pressed="false">Seguir</button>
                        </div>
                    <?php endforeach; ?>
                </aside>

        </div>
    </main>

    <script src="<?php echo mgl_asset('assets/js/notifications.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/friends.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/feed.js'); ?>"></script>
<?php require __DIR__ . '/../partials/igdb_credit.php'; ?>
</body>
</html>
