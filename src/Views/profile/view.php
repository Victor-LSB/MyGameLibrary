<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
    <?php $profileUser = $profileUser ?? []; ?>
    <?php $isOwner = $isOwner ?? false; ?>
    <?php $currentUserId = $currentUserId ?? null; ?>
    <?php $profileUserId = $profileUserId ?? ($profileUser['id'] ?? null); ?>
    <?php $isFollowing = $isFollowing ?? false; ?>
    <?php $followsMe = $followsMe ?? false; ?>
    <?php $followingList = $followingList ?? []; ?>
    <?php $followersList = $followersList ?? []; ?>
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
    <title>Perfil de <?php echo htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?> - MyGameLibrary</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="assets/icon/icong.png?v=1">
</head>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

    <?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-7xl mx-auto px-6 mt-8">
        
        <div class="bg-zinc-900 rounded-sm border-2 border-zinc-800 shadow-xl overflow-hidden mb-10 relative">
            <div class="h-48 sm:h-64 md:h-80 w-full relative bg-zinc-800 overflow-hidden group">
                <?php if (!empty($profileUser['banner'])): ?>
                    <?php 
                        $bannerVal = $profileUser['banner'];
                        $bannerPath = str_starts_with($bannerVal, 'http') ? $bannerVal : './uploads/profile/' . basename($bannerVal); 
                    ?>
                    <img src="<?php echo htmlspecialchars($bannerPath); ?>" alt="Banner" class="w-full h-full min-w-full object-cover object-center bg-zinc-800">
                <?php else: ?>
                    <div class="w-full h-full bg-gradient-to-r from-violet-900 to-zinc-900"></div>
                <?php endif; ?>
                
                <button onclick="navigator.clipboard.writeText(window.location.href); alert('Link do perfil copiado!');" class="absolute top-4 right-4 bg-black/50 hover:bg-black/80 backdrop-blur-sm text-white px-3 py-2 rounded-sm text-xs font-bold uppercase tracking-wider transition-colors border border-white/10 z-10">
                    🔗 Compartilhar
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
                            <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar" class="w-full h-full min-w-full object-cover object-center absolute inset-0 bg-zinc-950">
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center text-5xl sm:text-7xl bg-zinc-800 font-black text-zinc-600 uppercase">
                                <?php echo substr($profileUser['username'], 0, 1); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="flex-1 text-center sm:text-left z-10 pt-2 sm:pt-0 pb-2 flex flex-col sm:flex-row justify-between items-center sm:items-end w-full">
                        <div>
                            <h1 class="text-3xl sm:text-4xl font-black text-white uppercase tracking-tight">
                                <?php echo htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?>
                            </h1>
                            <p class="text-violet-400 font-bold uppercase tracking-widest text-sm mt-1 flex items-center justify-center sm:justify-start gap-2">
                                @<?php echo htmlspecialchars($profileUser['username']); ?>
                                <?php if (!$isOwner && $followsMe): ?>
                                    <span class="bg-zinc-800 text-zinc-300 text-[10px] font-black uppercase tracking-widest px-2 py-1 rounded-sm border border-zinc-700">Segue você</span>
                                <?php endif; ?>
                            </p>
                        </div>
                        
                        <?php if ($isOwner): ?>
                            <a href="index.php?action=edit_profile" class="mt-4 sm:mt-0 bg-zinc-200 hover:bg-white text-zinc-900 px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors shadow-lg shrink-0">
                                Editar Perfil
                            </a>
                        <?php elseif ($currentUserId !== null): ?>
                            <button id="followBtn" class="mt-4 sm:mt-0 <?php echo $isFollowing ? 'bg-zinc-700 hover:bg-zinc-600' : 'bg-violet-600 hover:bg-violet-500'; ?> text-white px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors shadow-lg shrink-0" data-user-id="<?= $profileUserId ?>" data-is-following="<?php echo $isFollowing ? 'true' : 'false'; ?>">
                                <?php echo $isFollowing ? 'Deixar de Seguir' : 'Seguir'; ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($profileUser['bio'])): ?>
                    <div class="bg-zinc-950 border border-zinc-800 p-4 sm:p-5 rounded-sm mt-4 relative z-10">
                        <p class="text-zinc-300 whitespace-pre-wrap text-sm sm:text-base leading-relaxed"><?php echo htmlspecialchars($profileUser['bio']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-col lg:flex-row gap-8 items-start">
            <div class="flex-1 min-w-0">
                <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6">Registados Recentemente</h2>

                <?php if (!empty($recentGames) && count($recentGames) > 0): ?>
                    <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5 gap-4 sm:gap-6">
                        <?php foreach ($recentGames as $game): ?>
                            <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl flex flex-col overflow-hidden group">
                                <!-- AQUI ESTÁ A CORREÇÃO: O link agora carrega o ID do jogo e o nome de utilizador dono do perfil (&u=...) -->
                                <a href="index.php?action=details&id=<?php echo $game['id']; ?>&u=<?php echo urlencode($profileUser['username']); ?>" class="block h-40 sm:h-56 bg-zinc-950 border-b-2 border-zinc-800 overflow-hidden relative">
                                    <?php if (!empty($game['cover_image'])): ?>
                                        <img src="<?php echo htmlspecialchars($game['cover_image']); ?>" alt="<?php echo htmlspecialchars($game['title']); ?>" class="w-full h-full object-cover object-center group-hover:scale-105 transition-transform duration-300 bg-zinc-950">
                                    <?php else: ?>
                                        <div class="w-full h-full flex items-center justify-center text-zinc-700 font-bold uppercase text-xs sm:text-base text-center p-2"><?php echo htmlspecialchars($game['title']); ?></div>
                                    <?php endif; ?>

                                    <div class="absolute top-2 left-2 bg-zinc-900/90 backdrop-blur-sm text-white text-[10px] font-black uppercase tracking-wider px-2 py-1 border border-zinc-700 rounded-sm">
                                        <?php echo htmlspecialchars($game['status'] ?? ''); ?>
                                    </div>
                                </a>
                                <div class="p-3">
                                    <h3 class="font-bold text-white text-sm line-clamp-1" title="<?php echo htmlspecialchars($game['title']); ?>">
                                        <!-- A mesma correção de URL foi aplicada no título -->
                                        <a href="index.php?action=details&id=<?php echo $game['id']; ?>&u=<?php echo urlencode($profileUser['username']); ?>" class="hover:text-violet-400 transition-colors">
                                            <?php echo htmlspecialchars($game['title']); ?>
                                        </a>
                                    </h3>
                                    <?php if ($game['rating']): ?>
                                        <p class="text-amber-400 text-xs mt-1 font-black tracking-widest">★ <?php echo $game['rating']; ?>/5</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm p-12 text-center shadow-xl">
                        <p class="text-zinc-500 font-medium">Este jogador ainda não registou nenhum jogo.</p>
                    </div>
                <?php endif; ?>
            </div>

            <aside class="w-full lg:w-72 shrink-0">
                <div class="flex items-stretch gap-1 mb-6 border-l-4 border-violet-500 pl-3">
                    <button type="button" data-tab-target="followingTab" class="follow-tab-btn flex-1 text-left text-sm sm:text-base font-black text-white uppercase tracking-tight pb-1 border-b-2 border-violet-500 transition-colors whitespace-nowrap">
                        Seguindo (<?php echo count($followingList); ?>)
                    </button>
                    <button type="button" data-tab-target="followersTab" class="follow-tab-btn flex-1 text-left text-sm sm:text-base font-black text-zinc-500 uppercase tracking-tight pb-1 border-b-2 border-transparent transition-colors whitespace-nowrap">
                        Seguidores (<?php echo count($followersList); ?>)
                    </button>
                </div>

                <div id="followingTab" class="follow-tab-panel bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl divide-y divide-zinc-800 max-h-[32rem] overflow-y-auto">
                    <?php if (!empty($followingList)): ?>
                        <?php foreach ($followingList as $friend): ?>
                            <a href="index.php?action=profile&u=<?php echo urlencode($friend['username']); ?>" class="flex items-center gap-3 p-3 hover:bg-zinc-800/60 transition-colors">
                                <?php if (!empty($friend['avatar'])): ?>
                                    <?php
                                        $friendAvatarVal = $friend['avatar'];
                                        $friendAvatarPath = str_starts_with($friendAvatarVal, 'http') ? $friendAvatarVal : './uploads/profile/' . basename($friendAvatarVal);
                                    ?>
                                    <img src="<?php echo htmlspecialchars($friendAvatarPath); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" class="w-10 h-10 rounded-sm object-cover shrink-0 bg-zinc-950">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">
                                        <?php echo substr($friend['username'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="text-white text-sm font-bold truncate"><?php echo htmlspecialchars($friend['display_name'] ?: $friend['username']); ?></p>
                                    <p class="text-zinc-500 text-xs truncate">@<?php echo htmlspecialchars($friend['username']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-zinc-500 text-sm p-4 text-center">Ainda não segue ninguém.</p>
                    <?php endif; ?>
                </div>

                <div id="followersTab" class="follow-tab-panel hidden bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-xl divide-y divide-zinc-800 max-h-[32rem] overflow-y-auto">
                    <?php if (!empty($followersList)): ?>
                        <?php foreach ($followersList as $friend): ?>
                            <a href="index.php?action=profile&u=<?php echo urlencode($friend['username']); ?>" class="flex items-center gap-3 p-3 hover:bg-zinc-800/60 transition-colors">
                                <?php if (!empty($friend['avatar'])): ?>
                                    <?php
                                        $friendAvatarVal = $friend['avatar'];
                                        $friendAvatarPath = str_starts_with($friendAvatarVal, 'http') ? $friendAvatarVal : './uploads/profile/' . basename($friendAvatarVal);
                                    ?>
                                    <img src="<?php echo htmlspecialchars($friendAvatarPath); ?>" alt="<?php echo htmlspecialchars($friend['username']); ?>" class="w-10 h-10 rounded-sm object-cover shrink-0 bg-zinc-950">
                                <?php else: ?>
                                    <div class="w-10 h-10 rounded-sm shrink-0 bg-zinc-800 flex items-center justify-center text-zinc-500 font-black uppercase">
                                        <?php echo substr($friend['username'], 0, 1); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="min-w-0">
                                    <p class="text-white text-sm font-bold truncate"><?php echo htmlspecialchars($friend['display_name'] ?: $friend['username']); ?></p>
                                    <p class="text-zinc-500 text-xs truncate">@<?php echo htmlspecialchars($friend['username']); ?></p>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-zinc-500 text-sm p-4 text-center">Ainda não tem seguidores.</p>
                    <?php endif; ?>
                </div>

                <?php if ($isOwner && !empty($followSuggestions)): ?>
                    <h2 class="text-lg font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mt-8 mb-4">Sugestões para seguir</h2>
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
                    <button type="submit" class="shrink-0 bg-violet-600 hover:bg-violet-500 text-white px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors self-start sm:self-end">Comentar</button>
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
                        <button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white px-4 py-2 rounded-sm font-black uppercase tracking-wide text-[10px] transition-colors">Responder</button>
                        <button type="button" class="comment-reply-cancel bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-3 py-2 rounded-sm font-black uppercase tracking-wide text-[10px] transition-colors">Cancelar</button>
                    </div>
                </form>
            </template>
        </section>

    </main>

    <script>
        // Alterna entre as abas "Seguindo" e "Seguidores" sem recarregar a página
        document.querySelectorAll('.follow-tab-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.follow-tab-btn').forEach(function (b) {
                    b.classList.remove('text-white', 'border-violet-500');
                    b.classList.add('text-zinc-500', 'border-transparent');
                });
                btn.classList.remove('text-zinc-500', 'border-transparent');
                btn.classList.add('text-white', 'border-violet-500');

                document.querySelectorAll('.follow-tab-panel').forEach(function (panel) {
                    panel.classList.add('hidden');
                });
                document.getElementById(btn.dataset.tabTarget).classList.remove('hidden');
            });
        });
    </script>

    <script src="./assets/js/follow.js"></script>
    <script src="./assets/js/notifications.js"></script>
    <script src="./assets/js/profile-comments.js"></script>

    <?php if (isset($_SESSION['profile_success'])): ?>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        Swal.fire({
            toast: true, position: 'top-end', showConfirmButton: false, timer: 3000,
            icon: 'success', title: '<?php echo $_SESSION['profile_success']; ?>'
        });
    </script>
    <?php unset($_SESSION['profile_success']); endif; ?>
</body>
</html>
