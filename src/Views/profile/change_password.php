<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen pb-12 selection:bg-violet-600 selection:text-white">

<?php require_once __DIR__ . '/../partials/navbar.php'; ?>

    <main class="max-w-md mx-auto px-6">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h1 class="text-2xl font-black text-white tracking-tighter uppercase">Alterar Senha</h1>
            <a href="index.php?action=profile" class="bg-zinc-800 hover:bg-zinc-700 text-zinc-300 px-5 py-2.5 rounded-sm font-bold uppercase tracking-wide text-sm transition-colors">Voltar</a>
        </div>

        <div class="bg-zinc-900 p-6 sm:p-10 rounded-sm border-2 border-zinc-800 shadow-2xl">

            <?php if (isset($error)): ?>
                <div class="bg-red-950 border border-red-800 text-red-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-emerald-950 border border-emerald-800 text-emerald-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm text-center">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <form action="index.php?action=change_password" method="post" id="changePasswordForm" class="space-y-5">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">

                <div>
                    <label for="current_password" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Senha Atual</label>
                    <input type="password" id="current_password" name="current_password" required
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium">
                </div>

                <div>
                    <label for="new_password" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Nova Senha</label>
                    <input type="password" id="new_password" name="new_password" required minlength="8"
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium"
                        placeholder="Mínimo 8 caracteres">
                    <p id="passwordStrength" class="text-xs text-zinc-400 mt-1.5">
                        <span id="strengthBar" class="inline-block w-12 h-1 bg-zinc-700 rounded-full"></span>
                        <span id="strengthText" class="ml-2">Fraca</span>
                    </p>
                    <p id="messageErrorPassword" class="text-red-500 text-xs font-bold mt-1.5 empty:hidden"></p>
                    <?php require __DIR__ . '/../partials/password_requirements.php'; ?>
                </div>

                <div>
                    <label for="password_confirm" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Confirmar Nova Senha</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8"
                        class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium">
                    <p id="messageErrorConfirmPassword" class="text-red-500 text-xs font-bold mt-1.5 empty:hidden"></p>
                </div>

                <div class="pt-4 border-t-2 border-zinc-800 flex justify-end">
                    <button type="submit" class="bg-violet-600 hover:bg-violet-500 text-white font-black uppercase tracking-widest py-3 px-8 rounded-sm transition-colors shadow-lg disabled:opacity-50 disabled:cursor-not-allowed" id="submitBtn">
                        Alterar Senha
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="./assets/js/passwordRequirements.js"></script>
</body>
</html>
