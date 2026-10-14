<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
  <title>Download / Téléchargement clicShopping</title>
  <head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <base href="https://www.clicshopping.org/marketplace/">
    <title>Download / Téléchargement clicShopping Solution Social E-Commerce B2B/B2C, ClicShopping Market Place</title>
    <meta name="description"
          content="Créer votre site de social e-commerce gratuitement. Choisissez la meilleure solution de social e-commerce OpenSource B2B-B2C, ">
    <meta name="keywords"
          content="boutique en ligne, site e-commerce, eboutique, social, creation e-commerce, creer site ecommerce, creation site marchand, creer site marchand, solution ecommerce, solution e-commerce, logiciel ecommerce, logiciel e-commerce, ecommerce gratuit, e-commerce gratuit, creation boutique en ligne, ">
    <meta name="news_keywords"
          content="boutique en ligne, site e-commerce, eboutique, social, creation e-commerce, creer site ecommerce, creation site marchand, creer site marchand, solution ecommerce, solution e-commerce, logiciel ecommerce, logiciel e-commerce, ecommerce gratuit, e-commerce gratuit, creation boutique en ligne, ">
    <meta name="no-email-collection" content="https://www.clicshopping.org">
    <meta name="generator" content="ClicShopping">
    <meta name="author" content="Innov Concept">

    <style type="text/css">
      body {
        background: #f0edec;
        color: #4F4F4F;
        margin: 0px;
        font-size: 12px;
        font-family: Open Sans, Rokkitt, Verdana, Arial, sans-serif;
      }

      a img {
        border: 0;
      }
    </style>
  </head>
<body>
<p align="center"><a href="<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>"><img
      src="<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>/shop/images/logo_clicshopping_1.png" alt="ClicShopping AI"></a></p>
<h2 align="center">Download / Téléchargement clicShopping OpenSource</h2>
<div style="padding-top: 100px;">
  <blockquote><a href="<?php echo 'https://' . $_SERVER['HTTP_HOST']; ?>">Accueil / home Page</a></blockquote>
  <?php

  $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];

  $iter = new DirectoryIterator(__DIR__);
  $i = 0;

  foreach ($iter as $file) {
    if (!$file->isDot()) {
      if ($file != 'index.php') {
        if ($file != '.htaccess') {
          $file = $file->getFilename();
          echo '<div style="padding-left:50px;">' . $i . '- <a href="' . $url . $file . '">' . $file . '</a></blocquote></div>';
        }
      }
    }

    $i = $i++;
  }
  ?>
</div>
</body>
</html>