// passwordRequirements.js
// Checklist de requisitos + medidor de força de senha, reutilizado nas
// páginas de Registro, Redefinir Senha e Alterar Senha.

(function () {
    const passwordInput = document.getElementById('password') || document.getElementById('new_password');
    const confirmInput = document.getElementById('password_confirm');
    if (!passwordInput) return;

    const reqLength = document.getElementById('reqLength');
    const reqUpper = document.getElementById('reqUpper');
    const reqLower = document.getElementById('reqLower');
    const reqNumber = document.getElementById('reqNumber');

    const strengthBar = document.getElementById('strengthBar');
    const strengthText = document.getElementById('strengthText');

    const errorPassword = document.getElementById('messageErrorPassword');
    const errorConfirmPassword = document.getElementById('messageErrorConfirmPassword');
    const submitBtn = document.getElementById('submitBtn');

    const MIN_LENGTH = 8;

    function checkRequirements(password) {
        return {
            length: password.length >= MIN_LENGTH,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
        };
    }

    function setRequirementState(el, met) {
        if (!el) return;
        const icon = el.querySelector('.req-icon');
        if (met) {
            el.classList.remove('text-zinc-500');
            el.classList.add('text-emerald-400');
            if (icon) icon.textContent = '✓';
        } else {
            el.classList.remove('text-emerald-400');
            el.classList.add('text-zinc-500');
            if (icon) icon.textContent = '○';
        }
    }

    function allRequirementsMet(reqs) {
        return reqs.length && reqs.upper && reqs.lower && reqs.number;
    }

    function calculatePasswordStrength(password, reqs) {
        let strength = 0;
        if (reqs.length) strength++;
        if (password.length >= 12) strength++;
        if (reqs.upper && reqs.lower) strength++;
        if (reqs.number) strength++;
        if (/[^a-zA-Z0-9]/.test(password)) strength++;
        return strength;
    }

    function validatePasswordMatch() {
        if (!confirmInput) return true;
        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;

        if (password.length > 0 && confirmPassword.length > 0) {
            if (password !== confirmPassword) {
                if (errorConfirmPassword) errorConfirmPassword.textContent = 'As senhas não correspondem';
                confirmInput.classList.add('border-red-600');
                return false;
            }
        }
        if (errorConfirmPassword) errorConfirmPassword.textContent = '';
        confirmInput.classList.remove('border-red-600');
        return true;
    }

    function updateAll() {
        const password = passwordInput.value;
        const reqs = checkRequirements(password);

        setRequirementState(reqLength, reqs.length);
        setRequirementState(reqUpper, reqs.upper);
        setRequirementState(reqLower, reqs.lower);
        setRequirementState(reqNumber, reqs.number);

        if (strengthBar && strengthText) {
            const strength = calculatePasswordStrength(password, reqs);
            const colors = ['bg-red-700', 'bg-orange-600', 'bg-yellow-500', 'bg-lime-500', 'bg-emerald-500'];
            const labels = ['Muito Fraca', 'Fraca', 'Média', 'Forte', 'Muito Forte'];
            strengthBar.className = `inline-block w-12 h-1 rounded-full ${colors[strength - 1] || 'bg-zinc-700'}`;
            strengthText.textContent = strength > 0 ? labels[strength - 1] : 'Fraca';
        }

        const matches = validatePasswordMatch();

        if (submitBtn) {
            submitBtn.disabled = !allRequirementsMet(reqs) || !matches;
        }
    }

    passwordInput.addEventListener('input', updateAll);
    if (confirmInput) {
        confirmInput.addEventListener('input', updateAll);
    }

    // Validação final no envio, para o caso de JS ter sido burlado
    const form = passwordInput.closest('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            const reqs = checkRequirements(passwordInput.value);
            if (!allRequirementsMet(reqs)) {
                e.preventDefault();
                if (errorPassword) errorPassword.textContent = 'A senha não cumpre todos os requisitos abaixo.';
                return;
            }
            if (!validatePasswordMatch()) {
                e.preventDefault();
            }
        });
    }

    // Estado inicial (útil se o navegador preencher os campos automaticamente)
    updateAll();
})();
