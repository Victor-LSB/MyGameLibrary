<?php
    $profileUser = $profileUser ?? [];
    // <head> compartilhado (header.php): CSS compilado, favicon, Open Graph etc.
    $profileDisplayName = ($profileUser['display_name'] ?? '') ?: ($profileUser['username'] ?? '');
    $pageTitle = 'Perfil de ' . $profileDisplayName . ' - MyGameLibrary';
    $profileBio = trim((string) ($profileUser['bio'] ?? ''));
    $metaDescription = $profileBio !== ''
        ? mb_strimwidth($profileBio, 0, 180, '…')
        : 'Veja a biblioteca de jogos, o Top 5 e as análises de ' . $profileDisplayName . ' no MyGameLibrary.';
    // Prévia de link: o card gerado (avatar + números + Top 5). "v" muda quando o card muda,
    // para o WhatsApp/Discord não ficarem mostrando a versão antiga do cache deles.
    $cardVersion = substr(md5(json_encode([
        $profileUser['avatar'] ?? null, $profileDisplayName,
        array_map(fn($slot) => $slot['id'] ?? null, $topGames ?? []), $libraryTotalAll ?? null,
    ])), 0, 8);
    $profileCardUrl = 'index.php?' . http_build_query(['action' => 'profile_card', 'u' => $profileUser['username'] ?? '', 'v' => $cardVersion]);
    $cardSupported = \Victi\MyGameLibrary\Controllers\ProfileCardController::isSupported();
    $ogImage = $cardSupported
        ? $profileCardUrl
        : (mgl_avatar_url($profileUser['avatar'] ?? null) ?: mgl_avatar_url($profileUser['banner'] ?? null));
    $ogType = 'profile';
    $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
    $profileLink = mgl_url('profile', ['u' => $profileUser['username'] ?? '']);
    $profileShareUrl = $appUrl !== '' ? $appUrl . '/' . ltrim(preg_replace('#^' . preg_quote(mgl_base_path(), '#') . '#', '', $profileLink), '/') : '';
    $extraStyles = ['assets/css/social.css'];
    if (!empty($isOwner)) {
        // edição no próprio perfil usa o mesmo recorte da página de edição
        $extraStyles[] = 'assets/lib/cropperjs/cropper.min.css';
        $extraStyles[] = 'assets/css/image-cropper.css';
    }
    require __DIR__ . '/../header.php';
?>
    <?php $isOwner = $isOwner ?? false; ?>
    <?php $currentUserId = $currentUserId ?? null; ?>
    <?php $profileUserId = $profileUserId ?? ($profileUser['id'] ?? null); ?>
    <?php $isFollowing = $isFollowing ?? false; ?>
    <?php $followsMe = $followsMe ?? false; ?>
    <?php $followingCount = $followingCount ?? 0; ?>
    <?php $followersCount = $followersCount ?? 0; ?>
    <?php $mutualCount = $mutualCount ?? 0; ?>
    <?php $commonCount = $commonCount ?? 0; ?>
    <?php $friendsTotal = $friendsTotal ?? 0; ?>
    <?php $friendsPreview = $friendsPreview ?? []; ?>
    <?php $followSuggestions = $followSuggestions ?? []; ?>
    <?php $profileComments = $profileComments ?? []; ?>
    <?php
        if (!function_exists('mgl_avatar_path')) {
            function mgl_avatar_path($avatar) {
                if (empty($avatar)) return null;
                return str_starts_with($avatar, 'http') ? $avatar : './uploads/profile/' . basename($avatar);
            }
        }

        if (!function_exists('mgl_render_comment_avatar')) {
            function mgl_render_comment_avatar($user, $sizeClasses) {
                $path = mgl_avatar_path($user['avatar'] ?? null);
                if ($path) {
                    return '<img src="' . htmlspecialchars($path) . '" alt="' . htmlspecialchars($user['username']) . '" class="' . $sizeClasses . ' rounded-sm object-cover shrink-0 bg-zinc-950">';
                }
                $initial = substr($user['username'] ?? '?', 0, 1);
                return '<div class="' . $sizeClasses . ' rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">' . htmlspecialchars($initial) . '</div>';
            }
        }

        if (!function_exists('mgl_render_comment')) {
            function mgl_render_comment($comment, $currentUserId, $profileUserId, $isReply = false) {
                $isRemoved = $comment['removed_at'] !== null;
                $isAuthor = $currentUserId !== null && (int) $currentUserId === (int) $comment['author_id'];
                $isOwner = $currentUserId !== null && (int) $currentUserId === (int) $profileUserId;
                $canDelete = !$isRemoved && ($isAuthor || $isOwner);
                $canReply = !$isReply && !$isRemoved && $currentUserId !== null;
                $wrapClasses = $isReply ? 'flex gap-3 py-3 pl-10' : 'flex gap-3 py-4';

                ob_start();
                ?>
                <article id="comment-<?php echo (int) $comment['id']; ?>" class="<?php echo $wrapClasses; ?>" data-comment-id="<?php echo (int) $comment['id']; ?>" data-author-id="<?php echo (int) $comment['author_id']; ?>">
                    <?php echo mgl_render_comment_avatar($comment, $isReply ? 'w-8 h-8' : 'w-10 h-10'); ?>
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
                                    <button type="button" class="comment-reply-btn btn-link">Responder</button>
                                <?php endif; ?>
                                <?php if ($canDelete): ?>
                                    <button type="button" class="comment-delete-btn btn-link is-danger">Apagar</button>
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
                                    <?php echo mgl_render_comment($reply, $currentUserId, $profileUserId, true); ?>
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
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

    <?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-screen-2xl mx-auto px-4 sm:px-6 mt-8">
        <?php if ($isOwner): ?>
        <form id="profileEditForm" class="pe-form" action="index.php?action=update_profile" method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
        <?php endif; ?>
        <div class="bg-zinc-900 rounded-sm border-2 border-zinc-800 shadow-xl overflow-hidden mb-10 relative" data-profile-card>
            <div class="h-48 sm:h-64 md:h-80 w-full relative bg-zinc-800 overflow-hidden group">
                <?php if (!empty($profileUser['banner'])): ?>
                    <?php 
                        $bannerVal = $profileUser['banner'];
                        $bannerPath = str_starts_with($bannerVal, 'http') ? $bannerVal : './uploads/profile/' . basename($bannerVal); 
                    ?>
                    <img src="<?php echo htmlspecialchars($bannerPath); ?>" alt="Banner" class="w-full h-full min-w-full object-cover object-center bg-zinc-800" data-crop-target="banner">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-r from-violet-900 to-zinc-900" data-crop-placeholder="banner"></div>
                    <?php if ($isOwner): ?><img src="" alt="Banner" class="w-full h-full min-w-full object-cover object-center absolute inset-0" data-crop-target="banner" hidden><?php endif; ?>
                <?php endif; ?>

                <?php if ($isOwner): ?>
                    <label class="pe-swap pe-swap--banner">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><path d="M4 7h3l2-3h6l2 3h3v13H4z"/><circle cx="12" cy="13" r="4"/></svg>
                        Trocar banner
                        <input type="file" id="banner" name="banner" class="pe-file" accept="image/png, image/jpeg, image/gif, image/webp">
                    </label>
                <?php endif; ?>
                
                <button type="button" id="openShareProfile" class="btn btn-secondary absolute top-4 right-4 z-10 !bg-zinc-950/80 backdrop-blur-sm">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" class="w-3.5 h-3.5" aria-hidden="true"><path stroke-linecap="square" d="M7 17 17 7M9 7h8v8"/></svg>Compartilhar
                </button>
            </div>

            <div class="px-6 sm:px-10 pb-8 relative">
                <div class="flex flex-col sm:flex-row items-center sm:items-end gap-6 -mt-16 sm:-mt-24 mb-4">
                    <div class="w-32 h-32 sm:w-48 sm:h-48 rounded-sm border-4 border-zinc-900 bg-zinc-950 overflow-hidden shrink-0 z-10 shadow-2xl relative">
                        <?php if (!empty($profileUser['avatar'])): ?>
                            <?php 
                                $avatarVal = $profileUser['avatar'];
                                $avatarPath = str_starts_with($avatarVal, 'http') ? $avatarVal : './uploads/profile/' . basename($avatarVal); 
                            ?>
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="w-full h-full min-w-full object-cover object-center absolute inset-0 bg-zinc-950" data-crop-target="avatar">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-5xl sm:text-7xl bg-zinc-800 font-black text-zinc-600 uppercase" data-crop-placeholder="avatar">
                                <?php echo substr($profileUser['username'], 0, 1); ?>
                            </div>
                            <?php if ($isOwner): ?><img src="" alt="Avatar" class="w-full h-full min-w-full object-cover object-center absolute inset-0" data-crop-target="avatar" hidden><?php endif; ?>
                        <?php endif; ?>
                        <?php if ($isOwner): ?>
                            <label class="pe-swap pe-swap--avatar">
                                Trocar foto
                                <input type="file" id="avatar" name="avatar" class="pe-file" accept="image/png, image/jpeg, image/gif, image/webp">
                            </label>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-1 text-center sm:text-left z-10 pt-2 sm:pt-0 pb-2 flex flex-col sm:flex-row justify-between items-center sm:items-end w-full">
                        <div>
                            <h1 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tight pe-view">
                                <?php echo htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?>
                            </h1>
                            <?php if ($isOwner): ?>
                                <input type="text" name="display_name" class="pe-input pe-input--name" maxlength="50"
                                       value="<?php echo htmlspecialchars($profileUser['display_name'] ?? ''); ?>"
                                       placeholder="<?php echo htmlspecialchars($profileUser['username']); ?>" aria-label="Nome de exibição">
                            <?php endif; ?>
                            <p class="text-violet-400 font-bold uppercase tracking-widest text-sm mt-1 flex items-center justify-center sm:justify-start gap-2">
                                @<?php echo htmlspecialchars($profileUser['username']); ?>
                                <?php if (!$isOwner && $followsMe): ?>
                                    <span class="bg-zinc-800 text-zinc-300 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-sm border border-zinc-700">Segue você</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if ($isOwner): ?>
                            <a href="index.php?action=edit_profile" class="btn btn-secondary mt-4 sm:mt-0 shrink-0 pe-view" data-edit-profile>
                                Editar perfil
                            </a>
                            <div class="pe-actions mt-4 sm:mt-0 shrink-0">
                                <button type="button" class="btn btn-secondary" data-edit-cancel>Cancelar</button>
                                <button type="submit" class="btn btn-primary">Salvar</button>
                            </div>
                        <?php elseif ($currentUserId !== null): ?>
                            <button id="followBtn" class="btn mt-4 sm:mt-0 shrink-0 <?php echo $isFollowing ? 'btn-secondary' : 'btn-accent'; ?>" data-user-id="<?= $profileUserId ?>" data-is-following="<?php echo $isFollowing ? 'true' : 'false'; ?>">
                                <?php echo $isFollowing ? 'Deixar de seguir' : 'Seguir'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($profileUser['bio'])): ?>
                    <div class="bg-zinc-950 border border-zinc-800 p-4 sm:p-5 rounded-sm mt-4 relative z-10 pe-view">
                        <p class="text-zinc-300 whitespace-pre-wrap text-sm sm:text-base leading-relaxed"><?php echo htmlspecialchars($profileUser['bio']); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($isOwner): ?>
                    <div class="pe-edit-only mt-4 relative z-10">
                        <textarea name="bio" rows="3" class="pe-input pe-input--bio" placeholder="Fale um pouco sobre seus jogos favoritos..." aria-label="Bio"><?php echo htmlspecialchars($profileUser['bio'] ?? ''); ?></textarea>
                        <p class="pe-hint">Banner: 3:1, 2400 × 800 px ou maior. Foto: quadrada, 512 × 512 px ou maior. JPG, PNG, WEBP ou GIF, até 5 MB.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($isOwner): ?>
        </form>
        <?php endif; ?>

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <div class="flex-1 min-w-0">
                <?php $topGames = $topGames ?? array_fill(0, 5, null); ?>
                <?php if ($isOwner || !empty(array_filter($topGames))): ?>
                    <section id="top5" class="mb-10"
                             data-editable="<?php echo $isOwner ? '1' : '0'; ?>"
                             data-username="<?php echo htmlspecialchars($profileUser['username'], ENT_QUOTES); ?>"
                             data-slots="<?php echo htmlspecialchars(json_encode($topGames, JSON_UNESCAPED_UNICODE), ENT_QUOTES); ?>">
                        <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-2">Top 5</h2>
                        <?php if ($isOwner): ?>
                            <p class="text-xs text-zinc-500 mb-4">Clique em um slot vazio para adicionar um jogo da sua biblioteca, ou arraste um jogo da biblioteca para um slot (ele substitui o que estiver lá). Arraste os cards para trocar de posição.</p>
                        <?php else: ?>
                            <div class="mb-4"></div>
                        <?php endif; ?>
                        <div data-top5-grid class="grid grid-cols-3 sm:grid-cols-5 gap-3 sm:gap-4"></div>
                    </section>
                <?php endif; ?>

                <?php include __DIR__ . '/_library.php'; ?>
            </div>

            <aside class="w-full lg:w-72 shrink-0">
                <section class="friends-card" aria-labelledby="friendsTitle">
                    <h2 id="friendsTitle" class="friends-card__title">Amigos</h2>

                    <div class="friends-stats">
                        <button type="button" class="friends-stat" data-friends-open="followers">
                            <strong data-count="followers"><?php echo (int) $followersCount; ?></strong>
                            Seguidores
                        </button>
                        <button type="button" class="friends-stat" data-friends-open="following">
                            <strong data-count="following"><?php echo (int) $followingCount; ?></strong>
                            Seguindo
                        </button>
                        <button type="button" class="friends-stat" data-friends-open="mutual">
                            <strong data-count="mutual"><?php echo (int) $mutualCount; ?></strong>
                            Mútuos
                        </button>
                    </div>

                    <?php if (!empty($friendsPreview)): ?>
                        <?php $moreFriends = max(0, (int) $friendsTotal - count($friendsPreview)); ?>
                        <ul class="facepile" aria-label="Alguns amigos">
                            <?php foreach ($friendsPreview as $previewUser): ?>
                                <?php $previewAvatar = mgl_avatar_path($previewUser['avatar'] ?? null); ?>
                                <li>
                                    <a href="index.php?action=profile&u=<?php echo urlencode($previewUser['username']); ?>" title="<?php echo htmlspecialchars($previewUser['display_name'] ?: $previewUser['username']); ?>">
                                        <?php if ($previewAvatar): ?>
                                            <img src="<?php echo htmlspecialchars($previewAvatar); ?>" alt="<?php echo htmlspecialchars($previewUser['username']); ?>" loading="lazy">
                                        <?php else: ?>
                                            <span class="facepile__initial"><?php echo htmlspecialchars(substr($previewUser['username'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($moreFriends > 0): ?>
                                <li>
                                    <button type="button" class="facepile__more" data-friends-open="followers" aria-label="Ver mais <?php echo $moreFriends; ?> pessoas">+<?php echo $moreFriends; ?></button>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <?php if (!$isOwner && $currentUserId !== null && $commonCount > 0): ?>
                            <button type="button" class="friends-card__common" data-friends-open="common">
                                <?php echo (int) $commonCount; ?> <?php echo $commonCount === 1 ? 'pessoa em comum' : 'pessoas em comum'; ?> com você
                            </button>
                        <?php endif; ?>

                        <button type="button" class="friends-card__all" data-friends-open="followers">Ver todos</button>
                    <?php else: ?>
                        <p class="friends-card__empty" style="margin-top: 1rem;">
                            <?php echo $isOwner
                                ? 'Quando você começar a seguir alguém, ou alguém seguir você, aparece aqui.'
                                : 'Ainda não há conexões neste perfil.'; ?>
                        </p>
                    <?php endif; ?>
                </section>

                <?php if ($isOwner && !empty($followSuggestions)): ?>
                    <section class="friends-suggest" aria-labelledby="suggestTitle">
                        <h2 id="suggestTitle" class="feed-aside__title">Quem seguir</h2>
                        <?php foreach ($followSuggestions as $suggestion): ?>
                            <?php $suggAvatar = mgl_avatar_path($suggestion['avatar'] ?? null); ?>
                            <div class="person">
                                <a href="index.php?action=profile&u=<?php echo urlencode($suggestion['username']); ?>" class="person__who">
                                    <?php if ($suggAvatar): ?>
                                        <img src="<?php echo htmlspecialchars($suggAvatar); ?>" alt="" class="person__avatar" loading="lazy">
                                    <?php else: ?>
                                        <div class="person__avatar person__avatar--initial"><?php echo htmlspecialchars(substr($suggestion['username'], 0, 1)); ?></div>
                                    <?php endif; ?>
                                    <div class="person__text">
                                        <span class="person__name"><?php echo htmlspecialchars($suggestion['display_name'] ?: $suggestion['username']); ?></span>
                                        <span class="person__sub"><?php echo (int) $suggestion['followers_count']; ?> seguidor<?php echo $suggestion['followers_count'] == 1 ? '' : 'es'; ?></span>
                                    </div>
                                </a>
                                <button type="button" class="fbtn fbtn--follow" data-follow-user="<?php echo (int) $suggestion['id']; ?>" data-following="false" aria-pressed="false">Seguir</button>
                            </div>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <?php if (!empty($playingGames)): ?>
                    <section class="playing-now" aria-labelledby="playingTitle">
                        <h2 id="playingTitle" class="feed-aside__title">Jogando agora</h2>
                        <ul>
                            <?php foreach ($playingGames as $pg): ?>
                                <?php
                                    $pgCover = mgl_cover_thumb($pg['cover_image'] ?? null);
                                    $pgSince = '';
                                    if (!empty($pg['since'])) {
                                        $pgDays = (int) floor((time() - strtotime($pg['since'])) / 86400);
                                        $pgSince = $pgDays <= 0 ? 'começou hoje' : 'há ' . $pgDays . ($pgDays === 1 ? ' dia' : ' dias');
                                    }
                                ?>
                                <li>
                                    <a href="<?php echo htmlspecialchars(mgl_url('details', ['id' => (int) $pg['id'], 'u' => $profileUser['username']]), ENT_QUOTES); ?>">
                                        <?php if ($pgCover): ?>
                                            <img src="<?php echo htmlspecialchars($pgCover, ENT_QUOTES); ?>" alt="" loading="lazy">
                                        <?php else: ?>
                                            <span class="playing-now__nocover" aria-hidden="true"><?php echo htmlspecialchars(mb_substr($pg['title'], 0, 1)); ?></span>
                                        <?php endif; ?>
                                        <span class="playing-now__text">
                                            <b><?php echo htmlspecialchars($pg['title']); ?></b>
                                            <?php if ($pgSince !== ''): ?><small><i aria-hidden="true"></i><?php echo $pgSince; ?></small><?php endif; ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </aside>
        </div>

        <section id="profileComments" class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl p-6 sm:p-8 mt-10" data-profile-user-id="<?php echo (int) $profileUserId; ?>">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6">
                Comentários (<span id="commentsCount"><?php echo count($profileComments); ?></span>)
            </h2>

            <?php if ($currentUserId !== null): ?>
                <form id="newCommentForm" class="flex flex-col sm:flex-row gap-3 mb-8">
                    <textarea name="content" maxlength="1000" rows="2" required placeholder="Deixe um comentário no perfil de <?php echo htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?>..." class="flex-1 bg-zinc-950 border-2 border-zinc-800 rounded-sm p-3 text-sm text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-violet-500 resize-none"></textarea>
                    <button type="submit" class="btn btn-primary shrink-0 self-start sm:self-end">Comentar</button>
                </form>
            <?php else: ?>
                <p class="text-zinc-500 text-sm mb-8">
                    <a href="index.php?action=login" class="text-violet-400 hover:underline">Faça login</a> para comentar neste perfil.
                </p>
            <?php endif; ?>

            <div id="commentsList" class="divide-y divide-zinc-800">
                <?php foreach ($profileComments as $comment): ?>
                    <?php echo mgl_render_comment($comment, $currentUserId, $profileUserId); ?>
                <?php endforeach; ?>
            </div>
            <p id="noCommentsMsg" class="text-zinc-500 text-sm py-6 text-center <?php echo !empty($profileComments) ? 'hidden' : ''; ?>">
                Seja o primeiro a comentar por aqui.
            </p>

            <template id="replyFormTemplate">
                <form class="comment-reply-form flex flex-col sm:flex-row gap-2 mt-3 pl-10">
                    <textarea name="content" maxlength="1000" rows="1" required placeholder="Escreva uma resposta..." class="flex-1 bg-zinc-950 border-2 border-zinc-800 rounded-sm p-2 text-xs text-zinc-200 placeholder-zinc-600 focus:outline-none focus:border-violet-500 resize-none"></textarea>
                    <div class="flex gap-2 shrink-0 self-start sm:self-end">
                        <button type="submit" class="btn btn-primary">Responder</button>
                        <button type="button" class="btn btn-secondary comment-reply-cancel">Cancelar</button>
                    </div>
                </form>
            </template>
        </section>

    </main>

    <!-- Janela de compartilhar perfil: prévia do card + copiar link / baixar imagem -->
    <div id="shareProfileModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/75 backdrop-blur-sm p-4" role="dialog" aria-modal="true" aria-labelledby="shareProfileTitle">
        <div class="w-full max-w-2xl bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl">
            <div class="flex items-center justify-between px-5 py-4 border-b-2 border-zinc-800">
                <h2 id="shareProfileTitle" class="text-white font-black uppercase tracking-tight">Compartilhar perfil</h2>
                <button type="button" data-close-share class="w-9 h-9 flex items-center justify-center rounded-sm text-zinc-400 hover:text-white hover:bg-zinc-800" aria-label="Fechar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-5 h-5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
                </button>
            </div>
            <div class="p-5 space-y-4">
                <?php if ($cardSupported): ?>
                    <div class="relative aspect-[1200/630] bg-zinc-950 border-2 border-zinc-800 rounded-sm overflow-hidden">
                        <div id="shareCardLoading" class="absolute inset-0 flex items-center justify-center text-zinc-600 text-sm font-bold uppercase tracking-widest">Gerando prévia…</div>
                        <img id="shareCardImg" data-src="<?php echo htmlspecialchars($profileCardUrl); ?>" alt="Prévia do card do perfil" class="relative w-full h-full object-cover opacity-0 transition-opacity duration-300">
                    </div>
                    <p class="text-xs text-zinc-500">É assim que o link aparece no WhatsApp, Discord e redes sociais.</p>
                <?php endif; ?>
                <div class="flex gap-2">
                    <input id="shareProfileUrl" type="text" readonly value="<?php echo htmlspecialchars($profileShareUrl); ?>" class="flex-1 min-w-0 bg-zinc-950 border-2 border-zinc-800 text-zinc-300 text-sm rounded-sm px-3 py-2.5 focus:outline-none focus:border-violet-500">
                    <button type="button" id="copyProfileUrl" class="btn btn-primary shrink-0 h-auto">Copiar link</button>
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="nativeShareProfile" class="btn btn-secondary hidden">Compartilhar…</button>
                    <?php if ($cardSupported): ?>
                        <a id="downloadProfileCard" href="<?php echo htmlspecialchars($profileCardUrl); ?>" download="mygamelibrary-<?php echo htmlspecialchars($profileUser['username'] ?? 'perfil'); ?>.jpg" class="btn btn-secondary">Baixar imagem</a>
                    <?php endif; ?>
                    <a href="<?php echo htmlspecialchars(mgl_url('year_review', ['u' => $profileUser['username'] ?? ''])); ?>" class="btn btn-secondary">Ver retrospectiva <?php echo date('Y'); ?></a>
                </div>
            </div>
        </div>
    </div>
    <script>
    (function () {
        const modal = document.getElementById('shareProfileModal');
        const openBtn = document.getElementById('openShareProfile');
        if (!modal || !openBtn) return;
        const urlInput = document.getElementById('shareProfileUrl');
        const copyBtn = document.getElementById('copyProfileUrl');
        const nativeBtn = document.getElementById('nativeShareProfile');
        const img = document.getElementById('shareCardImg');
        if (!urlInput.value) urlInput.value = location.origin + location.pathname + location.search.replace(/([?&])(view|sort|q|page)=[^&]*/g, '$1').replace(/[?&]+$/, '');

        function open() {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            // A imagem só é pedida (e gerada no servidor) quando a janela abre
            if (img && !img.src) {
                img.addEventListener('load', () => {
                    img.classList.remove('opacity-0');
                    document.getElementById('shareCardLoading')?.remove();
                }, { once: true });
                img.src = img.dataset.src;
            }
            copyBtn.focus();
        }
        function close() {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
            openBtn.focus();
        }

        openBtn.addEventListener('click', open);
        modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close-share]')) close(); });
        document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !modal.classList.contains('hidden')) close(); });

        copyBtn.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(urlInput.value);
            } catch (e) {
                urlInput.select();
                document.execCommand('copy');
            }
            copyBtn.textContent = 'Copiado!';
            setTimeout(() => { copyBtn.textContent = 'Copiar link'; }, 2000);
        });

        if (navigator.share) {
            nativeBtn.classList.remove('hidden');
            nativeBtn.addEventListener('click', async () => {
                const data = { title: document.title, url: urlInput.value };
                // No celular, manda junto a imagem do card quando o navegador aceita arquivos
                try {
                    if (img && img.src && navigator.canShare) {
                        const blob = await (await fetch(img.src)).blob();
                        const file = new File([blob], 'perfil.jpg', { type: 'image/jpeg' });
                        if (navigator.canShare({ files: [file] })) data.files = [file];
                    }
                    await navigator.share(data);
                } catch (e) { /* cancelado */ }
            });
        }
    })();
    </script>

    <div id="friendsModal" class="fmodal" hidden role="dialog" aria-modal="true" aria-labelledby="friendsModalTitle"
         data-username="<?php echo htmlspecialchars($profileUser['username']); ?>"
         data-owner="<?php echo $isOwner ? '1' : '0'; ?>"
         data-follows-me="<?php echo $followsMe ? '1' : '0'; ?>">
        <div class="fmodal__backdrop" data-fmodal-close></div>
        <div class="fmodal__panel" tabindex="-1">
            <div class="fmodal__head">
                <h2 id="friendsModalTitle" class="fmodal__title"><?php echo $isOwner ? 'Seus amigos' : 'Amigos de ' . htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?></h2>
                <button type="button" class="fmodal__close" data-fmodal-close aria-label="Fechar">&times;</button>
            </div>
            <div class="fmodal__tabs" role="tablist">
                <button type="button" class="fmodal__tab" role="tab" data-tab="followers" aria-selected="true">Seguidores <span class="fmodal__tab-count" data-count="followers"><?php echo (int) $followersCount; ?></span></button>
                <button type="button" class="fmodal__tab" role="tab" data-tab="following" aria-selected="false">Seguindo <span class="fmodal__tab-count" data-count="following"><?php echo (int) $followingCount; ?></span></button>
                <button type="button" class="fmodal__tab" role="tab" data-tab="mutual" aria-selected="false">Mútuos <span class="fmodal__tab-count" data-count="mutual"><?php echo (int) $mutualCount; ?></span></button>
                <?php if (!$isOwner && $currentUserId !== null): ?>
                    <button type="button" class="fmodal__tab" role="tab" data-tab="common" aria-selected="false">Em comum <span class="fmodal__tab-count"><?php echo (int) $commonCount; ?></span></button>
                <?php endif; ?>
            </div>
            <div class="fmodal__search">
                <input type="search" placeholder="Buscar por nome ou @usuário" aria-label="Buscar nesta lista" maxlength="50" autocomplete="off">
            </div>
            <div class="fmodal__body">
                <ul class="fmodal__list" aria-live="polite"></ul>
                <p class="fmodal__status" hidden></p>
                <button type="button" class="fmodal__more" hidden>Carregar mais</button>
            </div>
        </div>
    </div>

    <?php if ($isOwner): ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>
    <script src="<?php echo mgl_asset('assets/lib/cropperjs/cropper.min.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/imagePreview.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/profile-edit.js'); ?>"></script>
    <?php endif; ?>
    <script src="<?php echo mgl_asset('assets/js/top-games.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/friends.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/follow.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/notifications.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/profile-comments.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/profile-library.js'); ?>"></script>

    <?php if ($isOwner && isset($_SESSION['profile_error'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.documentElement.dataset.openProfileEdit = '1';
        Swal.fire({ icon: 'error', title: <?php echo json_encode($_SESSION['profile_error'], JSON_UNESCAPED_UNICODE); ?> });
    </script>
    <?php unset($_SESSION['profile_error']); endif; ?>

    <?php if (isset($_SESSION['profile_success'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
            icon: 'success', title: '<?php echo $_SESSION['profile_success']; ?>'
        });
    </script>
    <?php unset($_SESSION['profile_success']); endif; ?>
<?php require __DIR__ . '/../partials/igdb_credit.php'; ?>
</body>
</html>
