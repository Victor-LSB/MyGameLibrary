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
    $isSearchPage  = $currentAction === 'search';

?>
<header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md py-3 lg:py-0 mb-8">
    <!-- Mesma largura e respiro lateral do conteúdo das páginas, para o logo alinhar com elas -->
    <div class="max-w-screen-2xl mx-auto px-4 sm:px-6 lg:h-16 flex flex-wrap lg:flex-nowrap justify-between items-center gap-3 lg:gap-4">
        <a href="index.php?action=home" class="text-xl sm:text-2xl font-black text-white tracking-tighter uppercase hover:text-violet-400 transition-colors shrink-0">MyGameLibrary</a>

        <?php if (isset($_SESSION['user_id'])): $me = mgl_current_user(); $meAvatar = mgl_avatar_url($me['avatar'] ?? null); $meName = ($me['display_name'] ?? '') ?: ($_SESSION['username'] ?? ''); ?>
            <!-- Celular/tablet: o menu fica recolhido atrás deste botão (o ponto violeta avisa notificação nova) -->
            <button type="button" id="mainNavToggle" class="lg:hidden relative flex items-center justify-center w-[34px] h-[34px] bg-zinc-800 hover:bg-zinc-700 text-white transition-colors" aria-controls="mainNavMenu" aria-expanded="false" aria-label="Abrir menu">
                <svg id="mainNavIconOpen" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-5 h-5"><path stroke-linecap="square" d="M4 7h16M4 12h16M4 17h16"/></svg>
                <svg id="mainNavIconClose" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-5 h-5 hidden"><path stroke-linecap="square" d="M6 6l12 12M18 6L6 18"/></svg>
                <span id="unreadDotMobile" class="hidden absolute -top-1 -right-1 w-3 h-3 bg-violet-500 ring-2 ring-zinc-900"></span>
            </button>

            <div id="mainNavMenu" class="hidden lg:flex lg:flex-1 w-full lg:w-auto flex-wrap lg:flex-nowrap items-center gap-2 lg:gap-3 pt-2 lg:pt-0">
                <!-- Abas encaixadas: a página atual acende em roxo -->
                <nav class="seg w-full lg:w-auto lg:ml-4" aria-label="Páginas">
                    <?php foreach ($navLinks as $key => $link): $isCurrent = $currentAction === $key; ?>
                        <a href="index.php?action=<?php echo $link['action']; ?>"
                           <?php echo $isCurrent ? 'aria-current="page"' : ''; ?>
                           class="seg-item !h-[30px] lg:!px-4 !text-[11px] min-[400px]:!text-xs !tracking-normal min-[400px]:!tracking-wider <?php echo $isCurrent ? 'is-on' : ''; ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 shrink-0"><?php echo $link['icon']; ?></svg>
                            <?php echo $link['label']; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>

                <div class="relative w-full lg:w-auto lg:ml-auto order-last lg:order-none">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-zinc-500 pointer-events-none" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="square" d="m20 20-3.5-3.5"/></svg>
                    <input type="text" id="userSearchInput" autocomplete="off" placeholder="Buscar usuários" aria-label="Buscar usuários"
                           class="w-full lg:w-36 xl:w-44 2xl:w-52 h-[34px] bg-zinc-950 border-2 border-zinc-800 focus:border-violet-500 text-white placeholder-zinc-500 text-[13px] pl-8 pr-3 focus:outline-none transition-colors">
                    <div id="userSearchDropdown" class="hidden absolute left-0 top-[calc(100%+8px)] w-72 bg-zinc-900 border-2 border-zinc-800 shadow-2xl max-h-96 overflow-y-auto z-50 divide-y divide-zinc-800"></div>
                </div>

                <a href="index.php?action=search" <?php echo $isSearchPage ? 'aria-current="page"' : ''; ?>
                   title="Adicionar jogo" class="btn btn-primary flex-1 lg:flex-none lg:px-2.5 xl:px-4<?php echo $isSearchPage ? ' ring-2 ring-violet-400 ring-offset-2 ring-offset-zinc-900' : ''; ?>">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" class="w-3.5 h-3.5"><path stroke-linecap="square" d="M12 5v14M5 12h14"/></svg>
                    <span class="lg:hidden xl:inline">Adicionar jogo</span>
                </a>

                <div class="relative">
                    <button id="notificationBell" aria-label="Notificações" class="relative flex items-center justify-center w-[34px] h-[34px] bg-zinc-800 hover:bg-zinc-700 text-zinc-300 hover:text-white transition-colors">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-4 h-4">
                            <path d="M12 2a6 6 0 0 0-6 6v3.586l-1.707 1.707A1 1 0 0 0 5 15h14a1 1 0 0 0 .707-1.707L18 11.586V8a6 6 0 0 0-6-6zm0 20a3 3 0 0 0 3-3H9a3 3 0 0 0 3 3z"/>
                        </svg>
                        <span id="unreadCount" class="hidden absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 items-center justify-center bg-violet-600 text-white text-[10px] font-black leading-none">0</span>
                    </button>

                    <div id="notificationPanel" class="hidden absolute right-0 top-[calc(100%+8px)] w-80 sm:w-96 bg-zinc-900 border-2 border-zinc-800 shadow-2xl max-h-[28rem] overflow-y-auto z-50">
                        <div class="flex items-center justify-between px-4 py-3 border-b-2 border-zinc-800">
                            <h3 class="text-white font-black uppercase text-sm tracking-wide">Notificações</h3>
                            <div class="flex items-center gap-4">
                                <button id="markAllRead" class="btn-link !text-violet-400 hover:!text-violet-300">Marcar tudo lido</button>
                                <button id="clearAllNotifications" class="btn-link is-danger">Limpar</button>
                            </div>
                        </div>
                        <div id="notificationsList" class="divide-y divide-zinc-800"></div>
                    </div>
                </div>

                <div class="relative">
                    <button id="profileMenuButton" aria-label="Menu do perfil" class="flex items-center h-[34px] border-2 <?php echo $isProfileArea ? 'border-violet-500' : 'border-zinc-800 hover:border-zinc-600'; ?> bg-zinc-950 text-zinc-200 hover:text-white transition-colors">
                        <?php if ($meAvatar): ?>
                            <img src="<?php echo htmlspecialchars($meAvatar); ?>" alt="" class="w-[30px] h-[30px] object-cover bg-zinc-800">
                        <?php else: ?>
                            <span class="w-[30px] h-[30px] flex items-center justify-center bg-violet-600 text-white text-sm font-black uppercase"><?php echo htmlspecialchars(mb_substr($meName, 0, 1)); ?></span>
                        <?php endif; ?>
                        <span class="hidden xl:block max-w-[9rem] truncate pl-2.5 text-xs font-extrabold uppercase tracking-wider"><?php echo htmlspecialchars($meName); ?></span>
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="w-3.5 h-3.5 mx-2 text-zinc-500"><path d="M12 15.5 5 8.5h14z"/></svg>
                    </button>

                    <div id="profileMenuPanel" class="hidden absolute right-0 top-[calc(100%+8px)] w-60 bg-zinc-900 border-2 border-zinc-800 shadow-2xl z-50 divide-y divide-zinc-800 overflow-hidden">
                        <a href="index.php?action=profile" class="block px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Meu Perfil</a>
                        <a href="index.php?action=year_review" class="block px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Retrospectiva <?php echo date('Y'); ?></a>
                        <a href="index.php?action=export_library" class="block px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Exportar biblioteca (CSV)</a>
                        <a href="index.php?action=edit_profile" class="block px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Editar Perfil</a>
                        <a href="index.php?action=change_password" class="block px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-zinc-300 hover:bg-zinc-800 hover:text-white transition-colors">Alterar Senha</a>
                        <a href="index.php?action=logout" class="block px-4 py-3 text-xs font-extrabold uppercase tracking-wider text-zinc-300 hover:bg-red-600 hover:text-white transition-colors">Sair</a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <a href="index.php?action=login" class="btn btn-primary shrink-0">Entrar</a>
        <?php endif; ?>
    </div>
</header>

<?php if (isset($_SESSION['user_id'])): ?>
    <script>
        // Menu recolhível do celular
        (function () {
            const toggle = document.getElementById('mainNavToggle');
            const menu = document.getElementById('mainNavMenu');
            if (!toggle || !menu) return;
            toggle.addEventListener('click', () => {
                const open = menu.classList.toggle('hidden') === false;
                menu.classList.toggle('flex', open);
                toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                toggle.setAttribute('aria-label', open ? 'Fechar menu' : 'Abrir menu');
                document.getElementById('mainNavIconOpen').classList.toggle('hidden', open);
                document.getElementById('mainNavIconClose').classList.toggle('hidden', !open);
            });
        })();
    </script>
    <script src="<?php echo mgl_asset('assets/js/userSearch.js'); ?>"></script>
    <script src="<?php echo mgl_asset('assets/js/customSelect.js'); ?>"></script>
    <script>
        // Só um dropdown aberto por vez: quem abre avisa os demais pelo evento "mgl:dropdown-open".
        // Esc fecha todos.
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.dispatchEvent(new CustomEvent('mgl:dropdown-open', { detail: { id: '__all__' } }));
            }
        });

        (function () {
            const ID = 'profile';
            const profileMenuButton = document.getElementById('profileMenuButton');
            const profileMenuPanel = document.getElementById('profileMenuPanel');
            if (!profileMenuButton || !profileMenuPanel) return;

            profileMenuButton.setAttribute('aria-haspopup', 'true');
            profileMenuButton.setAttribute('aria-expanded', 'false');

            function close() {
                profileMenuPanel.classList.add('hidden');
                profileMenuButton.setAttribute('aria-expanded', 'false');
            }

            profileMenuButton.addEventListener('click', (e) => {
                e.stopPropagation();
                if (profileMenuPanel.classList.contains('hidden')) {
                    document.dispatchEvent(new CustomEvent('mgl:dropdown-open', { detail: { id: ID } }));
                    profileMenuPanel.classList.remove('hidden');
                    profileMenuButton.setAttribute('aria-expanded', 'true');
                } else {
                    close();
                }
            });

            document.addEventListener('click', (e) => {
                if (!profileMenuButton.contains(e.target) && !profileMenuPanel.contains(e.target)) close();
            });

            document.addEventListener('mgl:dropdown-open', (e) => {
                if (e.detail.id !== ID) close();
            });
        })();
    </script>
<?php endif; ?>
<?php if (!isset($_SESSION['user_id'])): ?>
    <!-- Visitantes sem login também veem os selects no estilo do site (perfil, retrospectiva) -->
    <script src="<?php echo mgl_asset('assets/js/customSelect.js'); ?>"></script>
<?php endif; ?>
