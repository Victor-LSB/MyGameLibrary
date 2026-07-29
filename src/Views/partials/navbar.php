<?php
    // Barra de navegação compartilhada por todas as páginas autenticadas.
    // Inclui o sino de notificações, para ficar disponível em qualquer página.
    $currentAction = $_GET['action'] ?? 'home';

    $navLinks = [
        'home'      => ['label' => '📚 Biblioteca', 'action' => 'home'],
        'feed'      => ['label' => '📰 Feed', 'action' => 'feed'],
        'dashboard' => ['label' => '📊 Dashboard', 'action' => 'dashboard'],
        'profile'   => ['label' => '👤 Meu Perfil', 'action' => 'profile'],
    ];
?>
<header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5 mb-8">
    <div class="max-w-7xl mx-auto flex flex-col sm:flex-row justify-between items-center gap-4">
        <a href="index.php?action=home" class="text-3xl font-black text-white tracking-tighter uppercase hover:text-violet-400 transition-colors shrink-0">MyGameLibrary</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <div class="flex flex-wrap items-center justify-center gap-3">
                <?php foreach ($navLinks as $key => $link): ?>
                    <a href="index.php?action=<?php echo $link['action']; ?>"
                       class="<?php echo $currentAction === $key ? 'bg-violet-600 hover:bg-violet-500' : 'bg-zinc-800 hover:bg-zinc-700'; ?> text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors border-b-2 border-zinc-950 hover:border-zinc-900">
                        <?php echo $link['label']; ?>
                    </a>
                <?php endforeach; ?>

                <a href="index.php?action=search" class="bg-violet-600 hover:bg-violet-700 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors shadow-lg">✚ Adicionar Jogo</a>

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

                <a href="index.php?action=logout" class="bg-zinc-800 hover:bg-red-600 hover:text-white text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm border-b-2 border-zinc-950 hover:border-red-800 transition-colors">Sair</a>
            </div>
        <?php else: ?>
            <a href="index.php?action=login" class="bg-violet-600 hover:bg-violet-500 text-white px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors shrink-0">Iniciar Sessão</a>
        <?php endif; ?>
    </div>
</header>
