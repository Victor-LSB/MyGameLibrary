# Checklist de segurança (DigitalOcean + Cloudflare)

## 1. Turnstile (CAPTCHA)
1. Cloudflare > Turnstile > Add widget (domínio do site, modo *Managed*).
2. Coloque no `.env` do servidor:
   ```
   TURNSTILE_SITE_KEY=...
   TURNSTILE_SECRET_KEY=...
   ```
3. Sem essas chaves, o cadastro e a recuperação de senha **ficam bloqueados em produção**
   (de propósito: falha segura). Para desenvolvimento local use `APP_ENV=local` e as chaves
   de teste da Cloudflare (veja `.env.example`).

## 2. Só o Cloudflare pode falar com o servidor
Se o IP do droplet vazar, o bot ignora o Cloudflare inteiro.
- Painel DigitalOcean > Networking > Firewalls > Create Firewall
  - Inbound 80 e 443: origem = faixas de https://www.cloudflare.com/ips-v4 e /ips-v6
  - Inbound 22 (SSH): ver observação do deploy abaixo
  - Aplique ao droplet.
- Cloudflare > SSL/TLS: modo **Full (strict)** (use um Origin Certificate no Apache).
- Teste: abrir `http://IP-DO-DROPLET` no navegador **não** pode mostrar o site.

**Observação sobre o deploy:** o GitHub Actions (`deploy.yml`) entra por SSH com IP variável.
Se restringir a porta 22 ao seu IP, o deploy quebra. Opções: manter a 22 aberta, mas só com
chave SSH (`PasswordAuthentication no`) + `fail2ban`; ou usar um runner com IP fixo.

## 3. IP real do visitante
O código já resolve isso sozinho (`src/Services/ClientIp.php`: só confia em `CF-Connecting-IP`
quando a conexão vem de IP do Cloudflare). Opcionalmente, para o Apache também registrar o IP
real nos logs:
```
sudo bash deploy/update-cloudflare-ips.sh
```

## 4. Regras no painel do Cloudflare
- Security > Bots > **Bot Fight Mode**: ligado.
- Security > WAF > Rate limiting rule (plano gratuito permite 1):
  `(http.request.uri.query contains "action=register" and http.request.method eq "POST")`
  -> ~5 requisições / 1 minuto por IP -> Managed Challenge.
- DNS: registro do site com a nuvem **laranja** (Proxied).

## 5. Limpar contas-bot que já existem
```
php scripts/cleanup_unverified.php            # simulação
php scripts/cleanup_unverified.php --apply    # apaga contas nunca confirmadas há 7+ dias
```
Agende no cron (exemplo no cabeçalho do script).

## 6. Verifique se arquivos sensíveis estão expostos
Se o Apache aponta para `/var/www/html` (raiz do repositório) em vez de `/var/www/html/public`,
o `.env` e o código ficam acessíveis pela web. Teste no navegador:
`https://seusite/.env` e `https://seusite/composer.json` — devem dar 404.
Melhor solução: `DocumentRoot /var/www/html/public`. O `.htaccess` da raiz é uma segunda
camada (exige `AllowOverride All`).

## 7. Se você usa o `.env` antigo
Troque a `RESEND_API_KEY` e a senha do banco se algum dia o `.env` esteve acessível pela web.
