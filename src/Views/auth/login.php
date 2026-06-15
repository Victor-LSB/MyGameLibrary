<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - GameLoggd</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen flex flex-col selection:bg-violet-600 selection:text-white">

    <!-- Top Header Minimalista -->
    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5">
        <div class="max-w-7xl mx-auto flex justify-center">
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase">GameLoggd</h1>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md bg-zinc-900 p-8 sm:p-10 rounded-sm border-2 border-zinc-800 shadow-2xl">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight mb-8 text-center border-b-2 border-zinc-800 pb-4">Acesso ao Sistema</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-950 border border-red-800 text-red-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <form action="index.php?action=login" method="post" class="space-y-5">
                <div>
                    <label for="email" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Email</label>
                    <input type="email" id="email" name="email" required
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium">
                </div>
                
                <div>
                    <label for="password" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Senha</label>
                    <input type="password" id="password" name="password" required
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium">
                </div>
                
                <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-black uppercase tracking-widest py-4 rounded-sm transition-colors shadow-lg mt-6">
                    Entrar
                </button>
                
                <div class="text-center pt-3">
                    <a href="index.php?action=forgot_password" class="inline-block text-violet-400 hover:text-violet-300 font-bold uppercase tracking-wide transition-colors text-xs">Esqueceu sua senha?</a>
                </div>
                
                <div class="mt-6 text-center pt-6 border-t border-zinc-800">
                    <p class="text-zinc-500 font-medium text-sm">Não tem uma conta?</p>
                    <a href="index.php?action=register" class="inline-block mt-1 text-violet-400 hover:text-violet-300 font-bold uppercase tracking-wide transition-colors text-sm">Registrar nova conta</a>
                </div>
            </form>
        </div>
    </main>
</body>
</html>