# Atualização: página do jogo, Quero jogar, retrospectiva e melhorias

Faça nesta ordem, no servidor, a partir da raiz do projeto (`/var/www/html`).

## 1. Backup do banco

```
mysqldump -u USER -p mygamelibrary > backup-antes-da-atualizacao.sql
```

## 2. Suba os arquivos novos

Pelo deploy do GitHub ou copiando os arquivos. O `public/assets/css/output.css` já vem
compilado, então não precisa rodar `npm run build` no servidor.

## 3. Migração do status "Quero jogar"

```
mysql -u USER -p mygamelibrary < database/migration_quero_jogar.sql
```

Sem ela, adicionar um jogo como "Quero jogar" dá erro se a coluna de status for um ENUM.

## 4. Unir jogos duplicados

Primeiro a simulação (não grava nada, só lista os grupos):

```
php scripts/merge_duplicate_games.php
```

Confira a lista. Se estiver certa:

```
php scripts/merge_duplicate_games.php --apply
```

Daqui para frente, um jogo que chega por outra fonte (ex.: fallback da Steam) com o mesmo
nome e ano de um que já existe reaproveita o registro em vez de criar outro.

## 5. (Opcional) Termos bloqueados na busca

O conteúdo adulto já é filtrado automaticamente (tema "Erotic" da IGDB e classificação adulta
da Steam). Para esconder também jogos ofensivos que as APIs não marcam, adicione ao `.env`:

```
BLOCKED_GAME_TERMS=hitler,goy
```

Os termos são procurados dentro do nome do jogo, sem diferenciar maiúsculas. Isso só afeta a
busca; jogos que já estão em bibliotecas continuam lá (dá para removê-los pelo painel do banco).

## 6. Extensão GD do PHP (card de compartilhar o perfil)

O card de compartilhamento do perfil é uma imagem gerada pelo PHP com a extensão GD.
Confira se ela está instalada:

```
php -m | grep -i gd
```

Se não aparecer nada, instale e reinicie o Apache:

```
sudo apt install php-gd
sudo systemctl restart apache2
```

Sem a GD o site continua funcionando: a prévia do link usa o avatar e a janela de
compartilhar mostra só o link.

## O que mudou

- **Página pública do jogo** (`index.php?action=game&id=…` ou `/jogos/…`): nota média,
  distribuição de status e notas, horas médias para zerar, quem você segue que tem o jogo e
  todas as análises. O título na tela de detalhes leva para ela.
- **Quero jogar**: novo status, com botão próprio na busca e na página do jogo. Aparece no
  filtro da biblioteca, no dashboard e no feed ("quer jogar").
- **Ordenação na biblioteca**: recentes, antigos, nome, nota, horas jogadas, zerados recentes.
- **Feed** com "Carregar mais" (de 60 em 60 atividades, até 600).
- **Retrospectiva do ano** (menu Perfil → Retrospectiva): zerados, horas, platinas, gênero do
  ano, favoritos, gráfico por mês. Pública e com botão de compartilhar.
- **Exportar biblioteca em CSV** (menu Perfil). Abre direto no Excel.
- **Notificações**: o contador agora é consultado a cada 25 s com a aba aberta, em vez de manter
  uma conexão aberta por aba (que prendia um processo do PHP por até 55 s).
- **Visual**: página 404 no tema do site, menu recolhível no celular, biblioteca e busca com 2
  colunas no celular, capas com carregamento preguiçoso, prévia de links (Open Graph) com capa
  do jogo ou avatar, perfil usando o CSS compilado (antes carregava o Tailwind pelo CDN).
- Textos que ainda estavam em português de Portugal foram trocados.

Para a prévia de links mostrar o endereço e a imagem certos, o `APP_URL` do `.env` precisa
estar preenchido com o endereço público do site (ex.: `https://seusite.com.br`).

### Segunda leva

- **Layout na largura da navbar**: biblioteca, busca, perfil, detalhes, feed, página do jogo e
  retrospectiva usam a mesma largura (e o mesmo alinhamento) da navbar e do dashboard, em vez de
  uma coluna estreita no meio da tela.
  - Página do jogo: capa, ações e números da comunidade numa coluna fixa à esquerda.
  - Retrospectiva: números, gráfico e favoritos à esquerda; lista de zerados à direita.
  - Feed: três colunas (seu resumo | feed | quem seguir).
- **Selects estilizados** no ano da retrospectiva e na ordenação da biblioteca do perfil,
  também para visitantes sem login.
- **Compartilhar perfil**: o botão abre uma janela com a prévia do card (avatar, nome, números
  e Top 5), copiar link, baixar a imagem e compartilhar pelo celular. O mesmo card vira a prévia
  do link no WhatsApp/Discord. As imagens ficam em cache em `storage/cache/cards`.
- A fonte do card (Inter, licença livre OFL) fica em `resources/fonts`.


### Terceira leva: visual dos botões (estilo D)

- Navbar com abas encaixadas (a página atual acende em roxo), busca menor, "Adicionar jogo"
  em branco, sino com contador e avatar + nome no lugar de "Perfil". Abaixo de 1024px vira o
  menu recolhível.
- Botões do site todo seguem a mesma regra (classes em `public/assets/css/input.css`):
  `btn btn-primary` (branco, ação principal), `btn btn-secondary` (contorno, ação secundária),
  `btn btn-accent` (roxo, ex.: Seguir), `btn-link` (texto pequeno, ex.: Apagar) e `seg` /
  `seg-item` para escolhas lado a lado (status, nota, abas).
- Status e nota viraram barras encaixadas na biblioteca e nos detalhes; o ativo acende na cor
  do status.
- Corrigido: o rótulo "Zerado" na tela de detalhes nunca ficava verde.

Lembrete: depois de mexer em classes nas views, rode `npm run build` para recompilar o
`public/assets/css/output.css` (o pacote já vem com ele compilado).
