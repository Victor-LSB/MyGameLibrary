<?php require __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">
<?php require __DIR__ . '/../partials/navbar.php'; ?>
    <main class="max-w-xl mx-auto px-6 py-16 text-center">
        <p class="text-7xl sm:text-8xl font-black text-violet-500 tracking-tighter">404</p>
        <h1 class="mt-4 text-2xl font-black text-white uppercase tracking-tight">Game over para esta página</h1>
        <p class="mt-3 text-zinc-400 leading-relaxed"><?php echo htmlspecialchars($notFoundMessage ?? ''); ?></p>
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-center">
            <a href="index.php?action=home" class="btn btn-primary">
                <?php echo isset($_SESSION['user_id']) ? 'Ir para a biblioteca' : 'Ir para o início'; ?>
            </a>
            <?php if (isset($_SESSION['user_id'])): ?>
                <a href="index.php?action=feed" class="btn btn-secondary">Ver o feed</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
