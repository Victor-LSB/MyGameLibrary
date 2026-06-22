<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
        <div class="max-w-3xl mx-auto flex items-center justify-between gap-4">
            <h1 class="text-2xl font-black text-white tracking-tighter uppercase">Editar Perfil</h1>
            <a href="index.php?action=profile" class="bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors">Voltar</a>
        </div>
    </header>

    <main class="max-w-3xl mx-auto px-6">
        <div class="bg-zinc-900 p-6 sm:p-10 rounded-sm border-2 border-zinc-800 shadow-2xl">
            
            <?php if (isset($_SESSION['profile_error'])): ?>
                <div class="bg-red-950 border border-red-800 text-red-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm text-center">
                    <?php 
                        echo htmlspecialchars($_SESSION['profile_error']); 
                        unset($_SESSION['profile_error']);
                    ?>
                </div>
            <?php endif; ?>

            <form action="index.php?action=update_profile" method="post" enctype="multipart/form-data" class="space-y-6">
                
                <div>
                    <label for="display_name" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Nome de Exibição</label>
                    <input type="text" id="display_name" name="display_name" value="<?php echo htmlspecialchars($user['display_name'] ?? ''); ?>" placeholder="Como queres ser chamado?"
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium">
                </div>

                <div>
                    <label for="avatar" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Foto de Perfil (Avatar)</label>
                    <?php if (!empty($user['avatar'])): ?>
                        <?php 
                            $avatarVal = $user['avatar'];
                            $avatarPath = str_starts_with($avatarVal, 'http') ? $avatarVal : './uploads/profile/' . basename($avatarVal); 
                        ?>
                        <div class="mb-4">
                            <span class="text-xs text-zinc-500 block mb-2">Imagem atual:</span>
                            <div class="w-32 h-32 rounded-sm border border-zinc-700 overflow-hidden bg-zinc-950">
                                <img src="<?php echo htmlspecialchars($avatarPath); ?>" alt="Avatar Atual" class="w-full h-full object-cover">
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="avatar" name="avatar" accept="image/png, image/jpeg, image/gif, image/webp"
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium file:mr-4 file:py-2 file:px-4 file:rounded-sm file:border-0 file:text-sm file:font-black file:uppercase file:tracking-wider file:bg-violet-600 file:text-white hover:file:bg-violet-500 cursor-pointer">
                </div>

                <div>
                    <label for="banner" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Banner do Perfil</label>
                    <?php if (!empty($user['banner'])): ?>
                        <?php 
                            $bannerVal = $user['banner'];
                            $bannerPath = str_starts_with($bannerVal, 'http') ? $bannerVal : './uploads/profile/' . basename($bannerVal); 
                        ?>
                        <div class="mb-4">
                            <span class="text-xs text-zinc-500 block mb-2">Imagem atual:</span>
                            <div class="w-full h-32 sm:h-48 rounded-sm border border-zinc-700 overflow-hidden bg-zinc-950">
                                <img src="<?php echo htmlspecialchars($bannerPath); ?>" alt="Banner Atual" class="w-full h-full object-cover">
                            </div>
                        </div>
                    <?php endif; ?>
                    <input type="file" id="banner" name="banner" accept="image/png, image/jpeg, image/gif, image/webp"
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium file:mr-4 file:py-2 file:px-4 file:rounded-sm file:border-0 file:text-sm file:font-black file:uppercase file:tracking-wider file:bg-zinc-700 file:text-white hover:file:bg-zinc-600 cursor-pointer">
                </div>

                <div>
                    <label for="bio" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Bio / Sobre Mim</label>
                    <textarea id="bio" name="bio" rows="4" placeholder="Fala um pouco sobre os teus jogos favoritos..."
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium resize-y"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                </div>

                <div class="pt-4 border-t-2 border-zinc-800 flex justify-end">
                    <button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white font-black uppercase tracking-widest py-3 px-8 rounded-sm transition-colors shadow-lg">
                        Guardar Alterações
                    </button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>