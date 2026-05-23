<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

define('TEXT_TITLE_WELCOME', 'Bienvenue sur ClicShoppingAI');
define('TEXT_LICENCE', 'Veuillez accepter la licence avant de continuer');
define('TEXT_ACCEPT_LICENCE', 'J\'accepte les termes et les conditions');
define('TEXT_AGREEMENT', 'Acceptation de la licence');

define('TEXT_WAIT', 'Veuillez Attendre');
define('TEXT_CONTINUE', 'Continuer');
define('TEXT_PREFIX', 'Prefix pour toutes les noms de tables avec cette valeur');

define('TEXT_SKIP_DATABASE', 'ou continuer sans import de la base de données<br />après avoir complété les champs');

define('TEXT_INTRO_WELCOME', '  <p>ClicShoppingAI a été con&ccedil;ue spécialement pour permettre de présenter et de vendre des produits sur Internet. L\'administration vous permettra de gérer vos produits, clients, commandes, campagnes marketing ......</p>
  <p>Nous avons fait particuli&egrave;rement attention au développement de ClicShoppingAI et nous espérons qu\'il vous apportera une enti&egrave;re satisfaction.</p>
  <p>ClicShoppingAI a été développé spécifiquement pour permettre des actions marketing adaptées à vos clients.</p>
  <p>ClicShoppingAI est en version B2B, B2C, B2B & B2C et soit en vente ouverte ou privée.</p>');

define('TEXT_SERVER_CARACTERISTICS', 'Caractéristiques du serveur');
define('TEXT_DIRECTORIES', 'Permissions des répertoires devant etre mis en 777 ou 755 en fonction de serveurs');
define('TEXT_PHP_SETTINGS', '1. Veuillez vérifier les caractéristiques de votre version php');
define('TEXT_PHP_EXTENSION', '2. Veuillez vérifier que les extansions ci-dessous sont bien installées.');


define('TEXT_R_G', 'Register Globals');
define('TEXT_M_Q', 'Magic Quotes');
define('TEXT_F_U', 'Files upload');
define('TEXT_SA_S', 'Session auto start');
define('TEXT_SU_T_S', 'Session use trans sid');


define('TEXT_PHP_VERSION', 'Version PHP');
define('TEXT_PARAMETERS', 'Paramètres de PHP');
define('TEXT_LIBRAIRIES_NO_ACCEPTED', 'Librairie Graphique non configurée.<br />. Vous pouvez ignorer cette erreur lors de l\'installation de ClicShopping mais les graphiques ne fonctionneront pas');
define('TEXT_ACCEPTED', 'Accepté');
define('TEXT_NOT_ACCEPTED', 'Non accepté');
define('TEXT_INSTALLED_VERSION', 'Version installée');
define('TEXT_LIBRAIRIES_NOT_WELL_CONFIGURED', 'Librairie graphique disponible, mais la configuration n\'est pas correcte');
define('TEXT_LIBRAIRIES_NOT_WELL_CONFIGURED_CONTINUE', 'Vous pouvez ignorer cette erreur mais l\'apparition des graphiques ne fonctionnera pas');
define('TEXT_ANTIALIASING', 'avec anti-aliasing!');
define('TEXT_NO_ANTIALIASING', 'sans anti-aliasing!');
define('TEXT_SOAP', 'SOAP (webservice)');
define('TEXT_MANDATORY', 'Obligatoire');
define('TEXT_XML', 'XML');
define('TEXT_XML_RPC', 'XML-RPC (webservice)');
define('TEXT_CURL', 'cURL');
define('TEXT_OPENSSL', 'OpenSSL');
define('TEXT_CHECK_RIGHT', 'Veuillez vérifier si les droits sont corrects (777)');


define('TEXT_NOTES', 'Notes :');
define('TEXT_NOTICE', ' <blockquote>- Veuillez prendre note que certains serveur n\'acceptent pas l\'analyse des droits sur les répertoires.</blockquote>
    <blockquote>- ClicShopping a été testé sur un serveur Linux ubuntu / Debian, Il peut y avoir des incompatibiltés sur d\'autres systèmes d\'exploitation. Les problèmes peuvent survenir aussi de la configuration du serveur.</blockquote>
');
define('TEXT_NEW_INSTALLATION', 'Nouvelle Installation :');
define('TEXT_REGISTER_GLOBAL', 'La compatibilité avec register_globals est supportée depuis PHP 4.3 +. Ce paramètre doit être activé en raison d\'une ancienne version de PHP utilisée');
define('TEXT_MYSQL_EXTANSION', 'L\'extension MySQL est nécessaire mais n\'est pas installée. Ce paramètre doit être activé');
define('TEXT_NOT_SAVE_PARAMETERS', '<p>Le serveur ne peut pas enregistrer vos param&egrave;tres, veuillez rectifier les permissions des fichiers.</p>
<p>les fichiers suivants doivent avoir <strong>une permission 777 (chmod 777):</strong></p>');

define('TEXT_INFO_CUSTOMER', '
      <p>Les fichiers se situant dans l\'administration (ClicShoppingAdmin) et catalogue  doivent avoir <strong>une permission 777 lors du processus d\'installation :</strong></p>
      <blockquote>
        /Core/ClicShopping/Sites/Shop/conf.php (permission 777)<br />
        /Core/cClicShopping/Sites/Shop/conf.php (permission 777)<br />
        /Core/ClicShopping/Conf(permission 777)
      </blockquote>
      <blockquote>
        /ClicShoppingAdmin/images/banners permission 755 ou 777)<br />
        /ClicShoppingAdmin/backups (permission 755 ou 777)<br />
      </blockquote>
      <p>Les répertoires et sous répertoires se situant dans la boutique doivent avoir une <strong>>permission 755 ou 777 (chmod 777) :</strong></p>
      <blockquote>
        /template/images (permission 755 ou 777)<br />
        /template/products (permission 755 ou 777)<br />
        /Core/Work/Cache/ (permission 755 ou 777)<br />
        /Core/Work/Database/ (permission 755 ou 777)<br />
        /Core/Work/Log/ (permission 755 ou 777)<br />
        /Core/Work/Session/ (permission 755 ou 777)<br />
        /Core/Work/Temp/ (permission 755 ou 777)<br />
        /template/images (permission 755 ou 777)<br />
        /template/products  (permission 755 ou 777)<br />
        /template/public/newsletter (permission 755 ou 777)<br />
        /Core/Work/OnlineUpdates  (permission 755 ou 777)<br />
        <br />
      </blockquote>
      <p>Veuillez noter que certains <b><u>fichiers de paiement</u></b> doivent &ecirc;tre transférés en mode binaire et non ASCII pour pouvoir fonctionner correctement ou certains modules bancaires ne fonctionneront que si vous &ecirc;tes sur un serveur dédié:</p>
      <blockquote>
        /atos/atos_response<br />
        /atos/request<br />
      </blockquote>
<strong>Note : </strong> Nous conseillons fortement de mettre votre serveur <strong>register_global sur OFF</strong> dans votre fichier de configuration du php.ini. Des fonctionnalités ultérieures propres à ClicShopping fonctionneront uniquement dans ce mode de configuration.<br />
Vous renforcez aussi la sécurité de votre serveur');
define('TEXT_INFO_CUSTOMER_ALERT', '<blockquote>Veuillez corriger les erreurs suivantes avant de continuer la procédure d\'installation.</blockquote>');

define('TEXT_INFO_CUSTOMER_ALERT_SERVER', '<p><i>Si des changements doivent être effectués sur le serveur, veuillez penser à le redémarrer et à relancer l\'installation de ClicShopping.</i></p>');

define('TEXT_INFO_SERVER_OK', '<p>L\'environnement du serveur a été vérifié et il est conforme au standard de fonctionnement technologique de ClicShopping.</p>
    <p>Si dans la section configuration, vous voyez une croix rouge, veuillez noter qu\'il se peut que des composants ne puissent pas fonctionner correctement ou produisent des dysfonctionnements, certains ne sont pas forcément indispensables mais fortement recommandés pour une utilisation maximale</p>
<strong>Note : </strong> Nous conseillons fortement de mettre votre <strong>register_global sur OFF</strong> dans votre fichier de configuration php.ini et d\'utiliser la technologie PHP5 . Des fonctionnalités ultérieures propres à ClicShopping fonctionneront uniquement dans ce mode de configuration.<br />
    <p>Veuillez continuer la procédure d\'installation.</p>Vous renforcerez aussi la sécurité de votre serveur et de ClicShopping<br /> <br />');


//---------------------------
// Step 3
//--------------------------

define('TEXT_STEP_INTRO', '<strong> Nous vous conseillons fortement d\'être sur une technologie serveur MYSQL 5.23+ ou utiliser une technologie comme Mariadb ...</strong>  <p>Veuillez suivre les instructions qui vous seront demandées au fur et à mesure de l\'installation.</p>  <p>Il s\'agit de configurer correctement ClicShopping afin de le rendre fonctionnel sur votre hébergement.</p>');
define('TEXT_STEP_INTRO_1', '<strong>Etape 1: Configuration du serveur de base de données</strong>');

define('TEXT_STEP_INTRO_2', '<p>Le serveur de base de données permet de stocker l\'ensemble de vos informations que vous allez y incorporer, les produits, les clients, les commandes ....</p>
  <p>Note : Avant de commencer, veuillez créer une base de données via le module phpmyadmin et retenir le mot de passe et votre nom d\'utilisateur....</p>');


define('TEXT_DATABASE_SERVER', 'Serveur base de données<br />');
define('TEXT_DATABASE_SERVER_HELP', 'Indiquer l\'adresse du serveur de votre base de données ou l\'IP');
define('TEXT_USERNAME', 'Nom utilisateur<br />');
define('TEXT_USERNAME_HELP', 'Indiquer le nom d\'utilisateur du serveur de base données');
define('TEXT_PASSWORD', 'Mot de Passe<br />');
define('TEXT_PASSWORD_HELP', 'Indiquer le mot de passe du serveur de base données.');
define('TEXT_DATABASE_NAME', 'Nom  base de données<br />');
define('TEXT_DATABASE_HELP', 'Indiquer le nom de votre base de données (doit étre créée sur le serveur de base de données) ');

//---------------------------
// Step 4
//--------------------------

define('TEXT_STEP_INTRO_STEP4', '<p>L\'installation est entrain de réaliser la configuration des répertoires et chemins pour les fichiers de configuration.</p>
<p>Veuillez suivre les instructions qui vous sont données lors de la procédure d\'installation.</p>');
define('TEXT_STEP_INTRO_3', '<p>Step 2: Serveur Internet</p>');
define('TEXT_STEP_INTRO_4', '<p>Le serveur internet permet d\'afficher les pages de vos produits pour vos clients.</p>');


define('TEXT_STEP_HELP_4', 'Indiquer l\'adresse internet de votre boutique ClicShopping');
define('TEXT_STEP_HELP_5', 'Répertoire de votre boutique ClicShopping sur le serveur.');
define('TEXT_STEP_INTRO_5', 'Répertoire Root du serveur Internet<br />');


//---------------------------
// Step 5
//--------------------------

define('TEXT_END_CONFIGURATION', 'Fin de l\'installation');
define('TEXT_INFO_1', '<p>C\'est la derni&egrave;re étape  configuration de votre boutique en ligne ClicShopping.</p><p>Veuillez prendre soin à remplir l\'ensemble des champs ci-dessous.</p>');
define('TEXT_INFO_2', 'Step 3: Configuration d\'acc&egrave;s à la boutique ClicShopping');


define('TEXT_INFO_3', '<p>Vous pouvez définir le nom de votre boutique ClicShopping, les informations de contact concernant le propriétaire de la boutique.</p>
      <p>Le nom d\'utilisateur et le mot de passe sont une protection pour vous connecter à votre espace d\'administration.</p>
      <p>&nbsp;</p>');

define('TEXT_STORE_NAME', 'Nom de la boutique');
define('TEXT_STORE_HELP', 'Indiquer le nom de la boutique ClicShopping qui sera affichéé vos clients.');
define('TEXT_STORE_OWNER', 'Nom du propriétaire*');
define('TEXT_STORE_OWNER_HELP', 'Indiquer le nom du propriétaire de la boutique.');
Define('TEXT_STORE_NAME_ADMIN', 'Nom de l\'administrateur*');
define('TEXT_STORE_NAME_ADMIN_HELP', 'Indiquer le nom de l\'administrateur.');
define('TEXT_STORE_FIRST_NAME', 'Prénom  de l\'administrateur*');
define('TEXT_STORE_FIRST_NAME_HELP', 'Indiquer le prénom  de l\'administrateur de la boutique.');
define('TEXT_STORE_OWNER_EMAIL', 'L\'adresse email du propriétaire*');
define('TEXT_STORE_OWNER_EMAIL_HELP', 'Indiquer votre adresse email');
define('TEXT_STORE_EMAIL_ADMIN', 'Email de l\'administrateur*');
define('TEXT_STORE_EMAIL_ADMIN_HELP', 'Indiquer votre email de connexion à l\'administration de la boutique.');
define('TEXT_STORE_PASSWORD', 'Mot de passe de l\'administrateur*');
define('TEXT_STORE_PASSWORD_HELP', 'Indiquer le mot de passe de l\'administrateur de la boutique (ex : UiO/J-4). un mot de passe compliqué assurera une meilleure protection');
define('TEXT_STORE_DIRECTORY', 'Nom du répertoire d\'administration');
define('TEXT_STORE_DIRECTORY_HELP', 'Il s\'agit du répertoire où d\'administration sera installé. Vous devriez changer cela pour des raisons de sécurité.');
define('TEXT_STORE_TIME_ZONE', 'Zone horaire<br />');
define('TEXT_STORE_TIME_ZONE_HELP', 'Choisissez votre fuseau horaire');
define('TEXT_STORE_MANDATORY', 'Obligatoire');
define('TEXT_STORE_DONATION', ' Vous pouvez faire un don pour nous encourager à continuer.');
//---------------------------
// Step 6
//--------------------------

define('TEXT_END_INSTALLATION', 'Step 4: Terminée !');
define('TEXT_END_INSTALLATION_1', '
<p>Félicitation, vous avez réussi à configurer correctement votre boutique ClicShopping !</p>
<p>Nous vous souhaitons un excellent succés dans votre projet de commerce électronique.</p>
<br />
<p>Si vous appréciez le travail que nous réalisons, alors n\'hésitez pas à communiquer le projet à vos amis et à votre entourage.
Si vous souhaitez nous aiderà péréniser et développer ce projet, nous vous invitons à souscrire à l\'un de nos abonnements, support, aide forum, hébergement locatif e-commerce mais aussi à nous faire part des retours
 sur vos besoins, vos problémes de gestion au quotidien de ClicShopping, les bugs que vous voyez ou encore les fonctionnalités que vous souhaiteriez voir incluses dans ClicShopping. </p>
<p><br /><strong>
Vos avis et sugestions</strong> nous importent beaucoup dans le cadre du développement de ce projet car nous souhaitons proposer un des meilleures outils de ventes en ligne existant. </p>
<p align="right"> L\'équipe ClicShopping</p>');

define('TEXT_END_INSTALLATION_SUCCESS', 'L\'installation s\'est passée correctement, vous pouvez désormais accéder à votre boutique ClicShopping!<br />Cliquez sur le bouton et veuillez patienter, nous allons mettre en place en cache certains fichiers');

define('TEXT_END_INSTALLATION_2', 'Post-Installation Notes');
define('TEXT_END_INSTALLATION_3', '<p>Il est recommandé de supprimer le répertoire install pour sécuriser ClicShopping</p>');
define('TEXT_END_INSTALLATION_4', 'Changer les droits sur les fichiers ');
define('TEXT_END_INSTALLATION_5', 'Veuillez changer les permissions dans ces répertoire Conf (tous), ClicShoppingAdmin, Shop (conf.php) ');
define('TEXT_END_INSTALLATION_6', ' en 444 (or 644 si le fichier est encore en mode écriture)');

define('TEXT_END_INSTALLATION_7', 'Veuillez sécuriser votre administration');
define('TEXT_END_INSTALLATION_8', 'L\'idéal est de changer le nom du répertoire et d\'y incorper un htacces ou password (tous les serveur n\'acceptent pas forcement cette approche)');

define('TEXT_END_ACCESS_CATALOG', 'Accéder à votre catalogue');
define('TEXT_END_ACCESS_ADMIN', '<p style="color:#f29400; text-align:center;">Accéder à l\'administration </p>');

define('TEXT_TITLE_CONFIGURATION', 'Configuration de la boutique');
define('TEXT_TITLE_SHOP', 'Configuration de votre boutique');
define('TEXT_INTRO_SHOP', 'Cet espace concerne des informations à insérer concernant votre boutique.');
define('TEXT_TITLE_ACCESS', 'Configuration de l\'accès à votre administration');
define('TEXT_TITLE_STMP', 'SMTP Configuration');
define('TEXT_INTRO_SMTP', 'Cet espace concerne la configuration de l\'envoie de vos emails si vous souhaitez configurer. Certaines sociétés bloquent SendMail pour des raisons de sécurité. Le port SMTP sinon par défaut, ce sera Sendmail qui sera utilisé.<br /><p style="color:red">La configuration n\'est pas obligatoire des champs ci-dessous si vous souhaitez utiliser Sendmail');
define('TEXT_SMTP_HOST', 'SMTP Host');
define('TEXT_SMTP_EMAIL_TRANSORT', 'Email transort');
define('TEXT_SMTP_PORT', 'Port SMTP');
define('TEXT_SMTP_PORT_INFO', 'Pour gmail vous avez le choix en le port 25, 465 si vous utilisez SSL, 587 si vous utilisez TLS');
define('TEXT_SMTP_USERNAME', 'Nom Utilisateur (e-mail)');
define('TEXT_SMTP_USERNAME_INFO', 'Veuillez indiquer votre nom d\'utilisateur concernant votre email. Celui-ci n\'est pas forcément en relation avec votre compte d\'administration');
define('TEXT_SMTP_PASSWORD', 'Mot de passe');
define('TEXT_SMTP_PASSWORD_INFO', 'Veuillez indiquer votre mot de passe pour accéder à votre email');
define('TEXT_INFO_DATABASE', 'Installation base de données et démo');
define('TEXT_ALERT_DATABASE', 'Pour continuer, il est recommendé d\'avoir <strong>la version 11.7 de Maria Db sur votre serveur. Vous pouvez utiliser une version inférieure (mariaDb ou Mysql) mais vous ne pourrez pas utiliser la technologie RAG / BI Agent</strong>.');