<?php
$pageTitle = 'MyGameLibrary — o diário da sua biblioteca de jogos';
$metaDescription = 'Organize seu backlog, registre o que zerou, avalie seus jogos e acompanhe o que seus amigos estão jogando.';
$demoGame = !empty($popularGames) ? $popularGames[0] : null;
$demoTitle = $demoGame['title'] ?? 'Hollow Knight';
require_once __DIR__ . '/header.php';
?>
<body class="bg-zinc-950 text-zinc-200 font-sans min-h-screen selection:bg-violet-600 selection:text-white">

    <header class="border-b border-zinc-800">
        <div class="max-w-6xl mx-auto px-6 py-4 flex items-center justify-between">
            <a href="index.php" class="text-xl font-black text-white tracking-tighter uppercase">MyGameLibrary</a>
            <nav class="flex items-center gap-5">
                <a href="index.php?action=login" class="text-sm font-bold text-zinc-300 hover:text-white transition-colors">Entrar</a>
                <a href="index.php?action=register" class="btn-primary">Criar conta</a>
            </nav>
        </div>
    </header>

    <main>
        <!-- Hero -->
        <section class="max-w-6xl mx-auto px-6 pt-16 pb-20 sm:pt-24 sm:pb-28 grid lg:grid-cols-12 gap-12 items-center">
            <div class="lg:col-span-7">
                <h1 class="text-4xl sm:text-6xl font-black text-white tracking-tighter leading-[1.05]">
                    Pare de perder o fio do que você jogou.
                </h1>
                <p class="mt-6 text-lg text-zinc-400 max-w-xl leading-relaxed">
                    Monte sua biblioteca, marque o que está no backlog, o que você está jogando e o que já zerou.
                    Dê nota, escreva a review e veja o que seus amigos andam jogando.
                </p>
                <div class="mt-9 flex flex-wrap items-center gap-4">
                    <a href="index.php?action=register" class="bg-violet-600 hover:bg-violet-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white text-white font-black px-7 py-4 rounded-sm border-b-4 border-violet-900 transition-colors">Criar minha biblioteca</a>
                    <a href="index.php?action=login" class="text-zinc-300 hover:text-white font-bold underline underline-offset-4 decoration-zinc-600 hover:decoration-violet-500 transition-colors">Já tenho conta</a>
                </div>
                <p class="mt-5 text-sm text-zinc-500">Grátis. Você só precisa confirmar seu e-mail.</p>
            </div>

            <!-- Demo interativa: o mesmo controle de status usado no app -->
            <div class="lg:col-span-5">
                <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm shadow-2xl p-5" id="demo">
                    <div class="flex gap-4">
                        <div id="demoCover" class="relative w-28 sm:w-32 shrink-0 aspect-[3/4] rounded-sm bg-gradient-to-br from-violet-600 via-violet-900 to-zinc-950 border border-zinc-700 flex items-end p-3 transition motion-reduce:transition-none">
                            <?php if ($demoGame): ?>
                                <img src="<?php echo htmlspecialchars($demoGame['cover_image']); ?>" alt="Capa de <?php echo htmlspecialchars($demoTitle); ?>" width="300" height="400" referrerpolicy="no-referrer" class="absolute inset-0 w-full h-full object-cover rounded-sm">
                            <?php else: ?>
                                <span class="text-white/90 font-black text-3xl leading-none tracking-tighter">HK</span>
                            <?php endif; ?>
                            <span id="demoPlat" class="hidden absolute -top-2 -right-2 bg-amber-400 text-zinc-950 text-xs font-black px-2 py-1 rounded-sm shadow-lg">Platina</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold text-zinc-500">Exemplo</p>
                            <h2 class="text-xl font-black text-white tracking-tight leading-tight"><?php echo htmlspecialchars($demoTitle); ?></h2>
                            <p id="demoStatus" class="mt-2 inline-block text-xs font-black px-2 py-1 rounded-sm bg-zinc-700 text-zinc-100">Backlog</p>
                            <p id="demoHint" class="mt-3 text-sm text-zinc-400 leading-snug">Está na fila. Escolha um status ao lado para ver o que muda.</p>
                        </div>
                    </div>

                    <div class="mt-5 grid grid-cols-2 gap-2" role="group" aria-label="Status do jogo">
                        <button type="button" data-status="Backlog" class="demo-btn is-on">Backlog</button>
                        <button type="button" data-status="Jogando" class="demo-btn">Jogando</button>
                        <button type="button" data-status="Zerado" class="demo-btn">Zerado</button>
                        <button type="button" data-status="Dropado" class="demo-btn">Dropado</button>
                    </div>
                    <button type="button" id="demoPlatBtn" class="hidden mt-2 w-full text-sm font-black px-3 py-2 rounded-sm border-2 border-amber-400/60 text-amber-300 hover:bg-amber-400/10 transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-amber-300">Marcar como platinado</button>
                </div>
                <p class="mt-3 text-xs text-zinc-500">Demonstração. No app, isso vale para cada jogo da sua biblioteca.</p>
            </div>
        </section>

        <?php if (!empty($popularGames) && count($popularGames) >= 3): ?>
        <!-- Capas: jogos mais registrados nas bibliotecas -->
        <section class="max-w-6xl mx-auto px-6 pb-20">
            <h2 class="text-sm font-bold text-zinc-500 mb-4">Os jogos que mais aparecem nas bibliotecas</h2>
            <ul class="grid grid-cols-3 sm:grid-cols-6 gap-3">
                <?php foreach ($popularGames as $g): ?>
                    <li class="aspect-[3/4] bg-zinc-900 border border-zinc-800 rounded-sm overflow-hidden">
                        <img src="<?php echo htmlspecialchars($g['cover_image']); ?>" alt="Capa de <?php echo htmlspecialchars($g['title']); ?>" title="<?php echo htmlspecialchars($g['title']); ?>" width="300" height="400" loading="lazy" referrerpolicy="no-referrer" class="w-full h-full object-cover">
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <!-- O que você faz -->
        <section class="border-t border-zinc-800 bg-zinc-900/40">
            <div class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-12 gap-12">
                <div class="lg:col-span-4">
                    <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tighter leading-tight lg:sticky lg:top-8">Tudo sobre cada jogo, num lugar só.</h2>
                </div>
                <div class="lg:col-span-8 divide-y divide-zinc-800 border-y border-zinc-800">
                    <div class="py-7 grid sm:grid-cols-3 gap-3">
                        <h3 class="font-black text-white text-lg">Backlog sob controle</h3>
                        <p class="sm:col-span-2 text-zinc-400 leading-relaxed">Busque qualquer jogo, adicione com capa e informações e separe em Backlog, Jogando, Zerado ou Dropado. Crie tags suas para organizar do jeito que fizer sentido.</p>
                    </div>
                    <div class="py-7 grid sm:grid-cols-3 gap-3">
                        <h3 class="font-black text-white text-lg">Seu diário de jogatina</h3>
                        <p class="sm:col-span-2 text-zinc-400 leading-relaxed">Registre nota, review, horas jogadas e a data em que terminou. Zerou tudo? Marque a platina.</p>
                    </div>
                    <div class="py-7 grid sm:grid-cols-3 gap-3">
                        <h3 class="font-black text-white text-lg">Números da sua biblioteca</h3>
                        <p class="sm:col-span-2 text-zinc-400 leading-relaxed">Um painel mostra quanto você já zerou, quanto ainda falta e como anda sua rotina de jogos.</p>
                    </div>
                    <div class="py-7 grid sm:grid-cols-3 gap-3">
                        <h3 class="font-black text-white text-lg">Jogar é melhor com gente</h3>
                        <p class="sm:col-span-2 text-zinc-400 leading-relaxed">Siga outros jogadores, acompanhe o que eles adicionam e zeram no feed e comente reviews e perfis.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Feed de exemplo -->
        <section class="max-w-6xl mx-auto px-6 py-20 grid lg:grid-cols-12 gap-12 items-center">
            <div class="lg:col-span-5">
                <h2 class="text-3xl sm:text-4xl font-black text-white tracking-tighter leading-tight">Descubra o próximo jogo pelo que seus amigos zeram.</h2>
                <p class="mt-5 text-zinc-400 leading-relaxed max-w-md">O feed mostra a atividade de quem você segue, com curtidas e comentários nas reviews.</p>
            </div>
            <div class="lg:col-span-7">
                <div class="bg-zinc-900 border-2 border-zinc-800 rounded-sm divide-y divide-zinc-800">
                    <p class="px-5 py-3 text-xs font-bold text-zinc-500">Exemplo de feed</p>
                    <div class="px-5 py-4 flex items-center gap-4">
                        <span class="w-9 h-9 shrink-0 rounded-sm bg-violet-700 text-white font-black flex items-center justify-center">A</span>
                        <p class="text-sm"><span class="font-black text-white">ana</span> zerou <span class="font-bold text-violet-300">Celeste</span> e deu nota 10</p>
                    </div>
                    <div class="px-5 py-4 flex items-center gap-4">
                        <span class="w-9 h-9 shrink-0 rounded-sm bg-emerald-700 text-white font-black flex items-center justify-center">C</span>
                        <p class="text-sm"><span class="font-black text-white">caio</span> começou a jogar <span class="font-bold text-violet-300">Hades</span></p>
                    </div>
                    <div class="px-5 py-4 flex items-center gap-4">
                        <span class="w-9 h-9 shrink-0 rounded-sm bg-amber-600 text-white font-black flex items-center justify-center">L</span>
                        <p class="text-sm"><span class="font-black text-white">lu</span> escreveu uma review de <span class="font-bold text-violet-300">Stardew Valley</span></p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA final -->
        <section class="border-t-4 border-violet-600 bg-zinc-900">
            <div class="max-w-6xl mx-auto px-6 py-16 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <h2 class="text-3xl font-black text-white tracking-tighter">Comece pelo backlog.</h2>
                <a href="index.php?action=register" class="self-start sm:self-auto bg-violet-600 hover:bg-violet-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white text-white font-black px-7 py-4 rounded-sm border-b-4 border-violet-900 transition-colors">Criar minha biblioteca</a>
            </div>
        </section>
    </main>

    <footer class="border-t border-zinc-800">
        <div class="max-w-6xl mx-auto px-6 py-6 flex items-center justify-between text-sm text-zinc-500">
            <span>MyGameLibrary</span>
            <a href="index.php?action=login" class="hover:text-white transition-colors">Entrar</a>
        </div>
    </footer>

    <style>
        .demo-btn{background:#09090b;border:2px solid #27272a;color:#d4d4d8;font-weight:900;font-size:.875rem;padding:.6rem .75rem;border-radius:2px;transition:background-color .15s,border-color .15s,color .15s}
        .demo-btn:hover{border-color:#52525b;color:#fff}
        .demo-btn:focus-visible{outline:2px solid #fff;outline-offset:2px}
        .demo-btn.is-on{background:#7c3aed;border-color:#a78bfa;color:#fff}
        @media (prefers-reduced-motion:reduce){.demo-btn{transition:none}}
    </style>
    <script>
    (function () {
        var info = {
            'Backlog': ['bg-zinc-700 text-zinc-100',  'Está na fila. Escolha um status ao lado para ver o que muda.'],
            'Jogando': ['bg-sky-600 text-white',      'Em andamento. Seus amigos veem que você começou.'],
            'Zerado':  ['bg-emerald-600 text-white',  'Terminou! Agora dá para dar nota, escrever a review e platinar.'],
            'Dropado': ['bg-rose-700 text-white',     'Ficou pelo caminho. Sem culpa, o histórico continua salvo.']
        };
        var base = 'mt-2 inline-block text-xs font-black px-2 py-1 rounded-sm ';
        var badge = document.getElementById('demoStatus'), hint = document.getElementById('demoHint');
        var cover = document.getElementById('demoCover'), plat = document.getElementById('demoPlat');
        var platBtn = document.getElementById('demoPlatBtn'), btns = document.querySelectorAll('.demo-btn');
        var platinum = false;

        function render(status) {
            btns.forEach(function (b) { b.classList.toggle('is-on', b.dataset.status === status); });
            badge.className = base + info[status][0];
            badge.textContent = status;
            hint.textContent = info[status][1];
            cover.classList.toggle('grayscale', status === 'Dropado');
            cover.classList.toggle('opacity-60', status === 'Dropado');
            if (status !== 'Zerado') { platinum = false; }
            platBtn.classList.toggle('hidden', status !== 'Zerado');
            plat.classList.toggle('hidden', !platinum);
            platBtn.textContent = platinum ? 'Remover platina' : 'Marcar como platinado';
        }
        btns.forEach(function (b) { b.addEventListener('click', function () { render(b.dataset.status); }); });
        platBtn.addEventListener('click', function () {
            platinum = !platinum;
            plat.classList.toggle('hidden', !platinum);
            platBtn.textContent = platinum ? 'Remover platina' : 'Marcar como platinado';
        });
    })();
    </script>
<?php require __DIR__ . '/partials/rawg_credit.php'; ?>
</body>
</html>
