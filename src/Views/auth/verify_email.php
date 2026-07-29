<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen flex flex-col selection:bg-violet-600 selection:text-white">

    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5">
        <div class="max-w-7xl mx-auto flex justify-center">
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase">MyGameLibrary</h1>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md bg-zinc-900 p-8 sm:p-10 rounded-sm border-2 border-zinc-800 shadow-2xl text-center">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight mb-6 border-b-2 border-zinc-800 pb-4">Confirmação de E-mail</h2>

            <?php if (isset($error)): ?>
                <div class="bg-red-950 border border-red-800 text-red-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <a href="index.php?action=login" class="inline-block bg-violet-600 hover:bg-violet-500 text-white px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors">Ir para o login</a>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-emerald-950 border border-emerald-800 text-emerald-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm">
                    <?php echo htmlspecialchars($success); ?>
                </div>
                <a href="index.php?action=home" class="inline-block bg-violet-600 hover:bg-violet-500 text-white px-6 py-2.5 rounded-sm font-black uppercase tracking-wide text-xs transition-colors">Ir para a minha biblioteca</a>
            <?php endif; ?>
        </div>
    </main>
</body>
</html>
