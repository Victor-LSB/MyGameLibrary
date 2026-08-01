<?php
    // Barra de navegação compartilhada por todas as páginas autenticadas.
    // Inclui o sino de notificações, para ficar disponível em qualquer página.
    $currentAction = $_GET['action'] ?? 'home';

    $navLinks = [
        'home' => [
            'label'  => 'Biblioteca',
            'action' => 'home',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />',
        ],
        'feed' => [
            'label'  => 'Feed',
            'action' => 'feed',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 7.5h1.5m-1.5 3h1.5m-7.5 3h7.5m-7.5 3h7.5m3-9h3.375c.621 0 1.125.504 1.125 1.125V18a2.25 2.25 0 0 1-2.25 2.25M16.5 7.5V18a2.25 2.25 0 0 0 2.25 2.25M16.5 7.5V4.875c0-.621-.504-1.125-1.125-1.125H4.125C3.504 3.75 3 4.254 3 4.875V18a2.25 2.25 0 0 0 2.25 2.25h13.5M6 7.5h3v3H6v-3Z" />',
        ],
        'dashboard' => [
            'label'  => 'Dashboard',
            'action' => 'dashboard',
            'icon'   => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />',
        ],
    ];

    $isProfileArea = in_array($currentAction, ['profile', 'edit_profile', 'change_password'], true);
?>
<header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
    <div class="max-w-screen-2xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
        <a href="index.php?action=home" class="text-2xl font-black text-white tracking-tighter uppercase hover:text-violet-400 transition-colors shrink-0">MyGameLibrary</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="flex flex-wrap items-center justify-center gap-2">
                <div class="relative w-full sm:w-auto order-last sm:order-none basis-full sm:basis-auto">
                    <input type="text" id="userSearchInput" autocomplete="off" placeholder="Buscar usuários..."
                           class="w-full sm:w-44 bg-zinc-800 border-2 border-zinc-800 focus:border-violet-500 text-white placeholder-zinc-500 text-sm rounded-sm px-3 py-2.5 focus:outline-none transition-colors">
                    <div id="userSearchDropdown" class="hidden absolute left-0 top-12 w-72 bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl max-h-96 overflow-y-auto z-50 divide-y divide-zinc-800"></div>
                </div>

                <?php foreach ($navLinks as $key => $link): ?>
                    <a href="index.php?action=<?php echo $link['action']; ?>"
                       class="<?php echo $currentAction === $key ? 'bg-violet-600 hover:bg-violet-500' : 'bg-zinc-800 hover:bg-zinc-700'; ?> flex items-center gap-2 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors border-b-2 border-zinc-950 hover:border-zinc-900">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4 shrink-0"><?php echo $link['icon']; ?></svg>
                        <?php echo $link['label']; ?>
                    </a>
                <?php endforeach; ?>

                <a href="index.php?action=search" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors shadow-lg flex items-center gap-2">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 shrink-0">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Adicionar Jogo
                </a>

                <div class="relative">
                    <button id="notificationBell" class="relative flex items-center justify-center w-10 h-10 rounded-sm bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-5 h-5">
                            <path d="M12 2a6 6 0 0 0-6 6v3.586l-1.707 1.707A1 1 0 0 0 5 15h14a1 1 0 0 0 .707-1.707L18 11.586V8a6 6 0 0 0-6-6zm0 20a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3z"/>
                        </svg>
                        <span id="unreadCount" class="hidden absolute -top-1 -right-1 min-w-[18px] h-[18px] px-1 items-center justify-center rounded-full bg-violet-600 text-white text-[10px] font-bold leading-none">0</span>
                    </button>

                    <div id="notificationPanel" class="hidden absolute right-0 top-12 w-80 sm:w-96 bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl max-h-[28rem] overflow-y-auto z-50">
                        <div class="flex items-center justify-between px-4 py-3 border-b border-zinc-800">
                            <h3 class="text-white font-black uppercase text-sm tracking-wide">Notificações</h3>
                            <div class="flex items-center gap-3">
                                <button id="markAllRead" class="text-violet-400 hover:text-violet-300 text-xs font-bold uppercase tracking-wide">Marcar tudo lido</button>
                                <button id="clearAllNotifications" class="text-zinc-500 hover:text-red-400 text-xs font-bold uppercase tracking-wide">Limpar</button>
                            </div>
                        </div>
                        <div id="notificationsList" class="divide-y divide-zinc-800"></div>
                    </div>
                </div>

                <div class="relative">
                    <button id="profileMenuButton" class="flex items-center gap-1.5 <?php echo $isProfileArea ? 'bg-violet-600 hover:bg-violet-500' : 'bg-zinc-800 hover:bg-zinc-700'; ?> text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors border-b-2 border-zinc-950 hover:border-zinc-900">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-4 h-4 shrink-0">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                        Perfil
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5">
                            <path d="M12 15.5 5 8.5h14z"/>
                        </svg>
                    </button>

                    <div id="profileMenuPanel" class="hidden absolute right-0 top-12 w-56 bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl z-50 divide-y divide-zinc-800 overflow-hidden">
                        <a href="index.php?action=profile" class="block px-4 py-3 text-sm font-bold uppercase tracking-wide text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Meu Perfil</a>
                        <a href="index.php?action=edit_profile" class="block px-4 py-3 text-sm font-bold uppercase tracking-wide text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Editar Perfil</a>
                        <a href="index.php?action=change_password" class="block px-4 py-3 text-sm font-bold uppercase tracking-wide text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Alterar Senha</a>
                        <a href="index.php?action=logout" class="block px-4 py-3 text-sm font-bold uppercase tracking-wide text-zinc-300 hover:bg-red-600 hover:text-white transition-colors">Sair</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a href="index.php?action=login" class="bg-violet-600 hover:bg-violet-500 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors shrink-0">Iniciar Sessão</a>
        <?php endif; ?>
    </div>
</header>

<?php if (isset($_SESSION['user_id'])): ?>
    <script src="./assets/js/userSearch.js"></script>
    <script>
        (function () {
            const profileMenuButton = document.getElementById('profileMenuButton');
            const profileMenuPanel = document.getElementById('profileMenuPanel');
            if (!profileMenuButton || !profileMenuPanel) return;

            profileMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                profileMenuPanel.classList.toggle('hidden');
            });

            document.addEventListener('click', (e) => {
                if (!profileMenuButton.contains(e.target) && !profileMenuPanel.contains(e.target)) {
                    profileMenuPanel.classList.add('hidden');
                }
            });
        })();
    </script>
<?php endif; ?>