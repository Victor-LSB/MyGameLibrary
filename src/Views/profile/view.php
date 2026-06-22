<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $profileUser = $profileUser ?? []; ?>
    <?php $isOwner = $isOwner ?? false; ?>
    <title>Perfil de <?php echo htmlspecialchars($profileUser['display_name'] ?: $profileUser['username']); ?> - MyGameLibrary</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="icon" type="image/png" href="assets/icon/icong.png?v=1">
</head>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5">
        <div class="max-w-5xl mx-auto flex items-center justify-between gap-4">
            <a href="index.php?action=home" class="text-2xl font-black text-white tracking-tighter uppercase hover:text-violet-400 transition-colors">MyGameLibrary</a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php?action=home" class="bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm border-b-2 border-zinc-950 hover:border-zinc-900 transition-colors shrink-0">A Minha Biblioteca</a>
            <?php else: ?>
                <a href="index.php?action=login" class="bg-violet-600 hover:bg-violet-500 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors shrink-0">Iniciar Sessão</a>
            <?php endif; ?>
        </div>
    </header>

    <main class="max-w-5xl mx-auto px-6 mt-8">
        
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
                            <p class="text-violet-400 font-bold uppercase tracking-widest text-sm mt-1">@<?php echo htmlspecialchars($profileUser['username']); ?></p>
                        </div>
                        
                        <?php if ($isOwner): ?>
                            <a href="index.php?action=edit_profile" class="mt-4 sm:mt-0 bg-zinc-200 hover:bg-white text-zinc-900 px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors shadow-lg shrink-0">
                                Editar Perfil
                            </a>
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

        <div>
            <h2 class="text-2xl font-black text-white uppercase tracking-tight border-l-4 border-violet-500 pl-3 mb-6">Registados Recentemente</h2>
            
            <?php if (!empty($recentGames) && count($recentGames) > 0): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4 sm:gap-6">
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
                                    <?php echo htmlspecialchars($game['status']); ?>
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

    </main>

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