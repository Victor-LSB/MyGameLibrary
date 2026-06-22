<?php require_once __DIR__ . '/../header.php'; ?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen flex flex-col selection:bg-violet-600 selection:text-white">

    <!-- Top Header Minimalista -->
    <header class="bg-zinc-900 border-b-4 border-violet-600 shadow-md px-6 py-5">
        <div class="max-w-7xl mx-auto flex justify-center">
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase">MyGameLibrary</h1>
        </div>
    </header>

    <main class="flex-1 flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-md bg-zinc-900 p-8 sm:p-10 rounded-sm border-2 border-zinc-800 shadow-2xl">
            <h2 class="text-2xl font-black text-white uppercase tracking-tight mb-8 text-center border-b-2 border-zinc-800 pb-4">Redefinir Senha</h2>
            
            <?php if (isset($error)): ?>
                <div class="bg-red-950 border border-red-800 text-red-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm text-center">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-emerald-950 border border-emerald-800 text-emerald-400 px-4 py-3 rounded-sm mb-6 font-medium text-sm text-center">
                    <?php echo htmlspecialchars($success); ?>
                    <p class="text-xs mt-2 text-emerald-300">Redirecionando para login em 3 segundos...</p>
                    <?php header("refresh:3;url=index.php?action=login");?>
                </div>
            <?php endif; ?>

            <?php if (!isset($success)): ?>
                <form action="index.php?action=reset_password" method="post" id="resetPasswordForm" class="space-y-5">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($_GET['token'] ?? ''); ?>">
                    
                    <div>
                        <label for="new_password" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Nova Senha</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6"
                            class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium"
                            placeholder="Mínimo 6 caracteres">
                        <p id="passwordStrength" class="text-xs text-zinc-400 mt-1.5">
                            <span id="strengthBar" class="inline-block w-12 h-1 bg-zinc-700 rounded-full"></span>
                            <span id="strengthText" class="ml-2">Fraca</span>
                        </p>
                        <p id="messageErrorPassword" class="text-red-500 text-xs font-bold mt-1.5 empty:hidden"></p>
                    </div>

                    <div>
                        <label for="password_confirm" class="block text-xs font-black text-zinc-500 uppercase tracking-widest mb-2">Confirmar Senha</label>
                        <input type="password" id="password_confirm" name="password_confirm" required minlength="6"
                            class="w-full bg-zinc-950 border-2 border-zinc-800 text-white rounded-sm px-4 py-3 focus:outline-none focus:border-violet-500 transition-colors font-medium">
                        <p id="messageErrorConfirmPassword" class="text-red-500 text-xs font-bold mt-1.5 empty:hidden"></p>
                    </div>
                    
                    <button type="submit" class="w-full bg-violet-600 hover:bg-violet-500 text-white font-black uppercase tracking-widest py-4 rounded-sm transition-colors shadow-lg mt-8 disabled:opacity-50 disabled:cursor-not-allowed" id="submitBtn">
                        Redefinir Senha
                    </button>
                    
                    <div class="mt-6 text-center pt-6 border-t border-zinc-800">
                        <p class="text-zinc-500 font-medium text-sm">Lembrou sua senha?</p>
                        <a href="index.php?action=login" class="inline-block mt-1 text-violet-400 hover:text-violet-300 font-bold uppercase tracking-wide transition-colors text-sm">Fazer Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    
    <script>
        // Função para validar força da senha
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('password_confirm');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');
        const messageErrorPassword = document.getElementById('messageErrorPassword');
        const messageErrorConfirmPassword = document.getElementById('messageErrorConfirmPassword');
        const submitBtn = document.getElementById('submitBtn');

        function calculatePasswordStrength(password) {
            let strength = 0;
            
            if (password.length >= 6) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            return strength;
        }

        function updatePasswordStrength() {
            const password = newPasswordInput.value;
            const strength = calculatePasswordStrength(password);
            
            const colors = ['bg-red-700', 'bg-orange-600', 'bg-yellow-500', 'bg-lime-500', 'bg-emerald-500'];
            const labels = ['Muito Fraca', 'Fraca', 'Média', 'Forte', 'Muito Forte'];
            
            strengthBar.className = `inline-block w-12 h-1 rounded-full ${colors[strength - 1] || 'bg-zinc-700'}`;
            strengthText.textContent = strength > 0 ? labels[strength - 1] : 'Fraca';
            
            validatePasswordMatch();
        }

        function validatePasswordMatch() {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword.length > 0 && confirmPassword.length > 0) {
                if (newPassword !== confirmPassword) {
                    messageErrorConfirmPassword.textContent = 'As senhas não correspondem';
                    confirmPasswordInput.classList.add('border-red-600');
                    submitBtn.disabled = true;
                } else {
                    messageErrorConfirmPassword.textContent = '';
                    confirmPasswordInput.classList.remove('border-red-600');
                    submitBtn.disabled = false;
                }
            }
        }

        if (newPasswordInput) {
            newPasswordInput.addEventListener('input', updatePasswordStrength);
        }
        
        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);
        }

        // Validar no envio
        document.getElementById('resetPasswordForm')?.addEventListener('submit', function(e) {
            const newPassword = newPasswordInput.value;
            const confirmPassword = confirmPasswordInput.value;
            
            if (newPassword.length < 6) {
                e.preventDefault();
                messageErrorPassword.textContent = 'A senha deve ter no mínimo 6 caracteres';
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                messageErrorConfirmPassword.textContent = 'As senhas não correspondem';
                return;
            }
        });
    </script>
</body>
</html>
