<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php echo mgl_base_tag(); ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(\Victi\MyGameLibrary\Services\Csrf::token()); ?>">
    <title><?php echo htmlspecialchars($pageTitle ?? 'MyGameLibrary'); ?></title>
    <?php if (!empty($metaDescription)): ?><meta name="description" content="<?php echo htmlspecialchars($metaDescription); ?>"><?php endif; ?>
    <?php
        // Prévia de link (WhatsApp, Discord, X...). As páginas podem definir $ogImage e $ogType.
        $ogBase = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $ogUrl = $ogBase !== '' ? $ogBase . ($_SERVER['REQUEST_URI'] ?? '/') : '';
        $ogImageUrl = $ogImage ?? null;
        if ($ogImageUrl && !preg_match('#^https?://#i', $ogImageUrl)) {
            // caminho relativo (ex.: ./uploads/profile/x.jpg) precisa virar endereço completo
            $ogImageUrl = $ogBase !== '' ? $ogBase . '/' . ltrim($ogImageUrl, './') : null;
        }
        if (!$ogImageUrl && $ogBase !== '') {
            $ogImageUrl = $ogBase . '/assets/icon/icong.png';
        }
        $ogImageUrl = $ogImageUrl ? preg_replace('#^http://(?!localhost|127\.)#i', 'https://', $ogImageUrl) : null;
    ?>
    <meta property="og:site_name" content="MyGameLibrary">
    <meta property="og:locale" content="pt_BR">
    <meta property="og:type" content="<?php echo htmlspecialchars($ogType ?? 'website'); ?>">
    <meta property="og:title" content="<?php echo htmlspecialchars(preg_replace('/ - MyGameLibrary$/', '', $pageTitle ?? 'MyGameLibrary')); ?>">
    <?php if (!empty($metaDescription)): ?><meta property="og:description" content="<?php echo htmlspecialchars($metaDescription); ?>"><?php endif; ?>
    <?php if ($ogUrl): ?><meta property="og:url" content="<?php echo htmlspecialchars($ogUrl); ?>"><?php endif; ?>
    <?php if ($ogImageUrl): ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($ogImageUrl); ?>">
    <meta name="twitter:card" content="<?php echo !empty($ogImage) ? 'summary_large_image' : 'summary'; ?>">
    <?php endif; ?>
    <meta name="theme-color" content="#7c3aed">
    <link rel="stylesheet" href="<?php echo mgl_asset('assets/css/output.css'); ?>">
    <link rel="stylesheet" href="<?php echo mgl_asset('assets/css/scrollbar.css'); ?>">
    <link rel="stylesheet" href="<?php echo mgl_asset('assets/css/toast.css'); ?>">
    <?php foreach (($extraStyles ?? []) as $extraCss): ?>
    <link rel="stylesheet" href="<?php echo mgl_asset($extraCss); ?>">
    <?php endforeach; ?>
    <link rel="icon" type="image/png" href="assets/icon/icong.png?v=1">
    <!-- Google tag (gtag.js) -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=G-30Z4M9S4PC"></script>
    <script>
    window.dataLayer = window.dataLayer || [];
    function gtag(){dataLayer.push(arguments);}
    gtag('js', new Date());

    gtag('config', 'G-30Z4M9S4PC');
    </script>
</head>
 