<?php if (!empty($_ENV['GOOGLE_ANALYTICS_ID'])): ?>
    <!-- Global site tag (gtag.js) - Google Analytics -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $_ENV['GOOGLE_ANALYTICS_ID'] ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());

      gtag('config', '<?= $_ENV['GOOGLE_ANALYTICS_ID'] ?>');
    </script>
<?php endif; ?>