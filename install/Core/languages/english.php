<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

define('TEXT_TITLE_WELCOME', 'Welcome on ClicShoppingAI');
define('TEXT_LICENCE', 'Please, accept the licence before to continue');
define('TEXT_ACCEPT_LICENCE', 'I accept the terms and conditions');
define('TEXT_AGREEMENT', 'License agreement');
define('TEXT_INSTALLED_VERSION', 'Version installed');

define('TEXT_WAIT', 'Please wait..');
define('TEXT_CONTINUE', 'Continue');
define('TEXT_PREFIX', 'Prefix all table names in the database with this value');

define('TEXT_SKIP_DATABASE', 'or continue and skip database import <br />after filling the fields');

define('TEXT_INTRO_WELCOME', '  <p>ClicShoppingAI has been specially designed to sell products on the internet. The administration will allow you to manage your products, customers, orders, marketing campaigns ......</p>
  <p>We paid particular attention to developing ClicShoppingAI and we hope that you will be fully satisfied.</p>
   <p>ClicShoppingAI is GenAI solution B2B, B2C, B2B & B2C and open or private sale.</p>');

define('TEXT_SERVER_CARACTERISTICS', 'Server caracteristic');
define('TEXT_DIRECTORIES', 'Directories permissions (777 or 755 depending the server');
define('TEXT_PHP_SETTINGS', '1. Please check your PHP settings to match requirements listed below');
define('TEXT_PHP_EXTENSION', '2. Please make sure the PHP extensions listed below are installed.');

define('TEXT_R_G', 'Register Globals');
define('TEXT_M_Q', 'Magic Quotes');
define('TEXT_F_U', 'Files upload');
define('TEXT_SA_S', 'Session auto start');
define('TEXT_SU_T_S', 'Session use trans sid');

define('TEXT_PHP_VERSION', 'PHP Version');
define('TEXT_PARAMETERS', 'PHP Parameters');
define('TEXT_LIBRAIRIES_NOT_CONFIGURED', 'Graphic library not configured.<br />Please check and implement. You can ignore this error when installing ClicShopping but the graphics do not work');
define('TEXT_ACCEPTED', 'Accept');
define('TEXT_NOT_ACCEPTED', 'Not accepted');
define('TEXT_LIBRAIRIES_NOT_WELL_CONFIGURED', 'Graphics library available, but the configuration is not correct');
define('TEXT_LIBRAIRIES_NOT_WELL_CONFIGURED_CONTINUE', 'You can ignore this error, but the appearance of the graphics do not work');
define('TEXT_ANTIALIASING', 'with anti-aliasing!');
define('TEXT_NO_ANTIALIASING', 'without anti-aliasing!');
define('TEXT_SOAP', 'SOAP (webservice)');
define('TEXT_MANDATORY', 'Mandatory');
define('TEXT_XML', 'XML');
define('TEXT_XML_RPC', 'XML-RPC (webservice)');
define('TEXT_CURL', 'cURL');
define('TEXT_OPENSSL', 'OpenSSL');
define('TEXT_CHECK_RIGHT', 'Please, check if the rights are correct (777)');


define('TEXT_NOTES', 'Notes :');
define('TEXT_NOTICE', '<blockquote>- Please note that some servers do not accept human rights analysis on directories.</blockquote>
    <blockquote>-The tool has been tested on Linux Ubuntu / Debian server, it may be incompatibiltés on other operating systems. Problems can also arise from the configuration server.</blockquote>
');
define('TEXT_NEW_INSTALLATION', 'New installation :');
define('TEXT_MYSQL_EXTANSION', 'The MySQL extension is required but is not installed. Please enable it to continue installation');
define('TEXT_NOT_SAVE_PARAMETERS', '<p>The server can not save your settings, please correct file permissions.</p>
<p>the following files must be <strong>a permission 777 (chmod 777):</strong></p>');


define('TEXT_INFO_CUSTOMER', '

      <p>Files must have permission <strong> a permission777 (chmod 777) to continue the installation</strong></p>
      <blockquote>
        /Core/ClicShopping/Sites/Shop/conf.php (permission 777)<br />
        /Core/ClicShopping/Sites/Shop/conf.php (permission 777)<br />
        /Core/ClicShopping/Conf(permission 777)
      </blockquote>
      <blockquote>
        /ClicShoppingAdmin/images/banners permission 755 ou 777)<br />
        /ClicShoppingAdmin/backups (permission 755 ou 777)<br />
      </blockquote>
      <p>Directories and subdirectories lying in the store must have a <strong>permission 777 (chmod 777) or 755 (chmod 755):</strong></p>
      <blockquote>
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
      <p>Please note that some <b>payment module</b> must be transferred in binary mode, not ASCII to function properly or some bank modules only work if you are on a dedicated server:</p>
      <blockquote>
        /atos/atos_response<br />
        /atos/request<br />
      </blockquote>
<strong>Note : </strong> We strongly recommend to your server <strong>register_global sur OFF</strong> in your php.ini configuration file. Specific to ClicShoppingAI subsequent features only work in this configuration mode. <br />
Also strengthen the security of your server
');

define('TEXT_INFO_CUSTOMER_ALERT', '<blockquote>Please correct the following errors before continuing the installation process.</blockquote>');
define('TEXT_INFO_CUSTOMER_ALERT_SERVER', '<p><i>If a modification must be made on the server, please remember to reboot and restart installation ClicShoppingAI.</i></p>');

define('TEXT_INFO_SERVER_OK', '<p>Server environment has been tested and conforms to the standard technological tool operation. </p>
     <p> If in the configuration section, you see a red cross, please note that there may be components may not operate properly or malfunctions occur, some are not necessarily essential but highly recommended for maximum use </p>
<strong> Note: </strong> We strongly recommend putting your register_global <strong> OFF </strong> in your php.ini configuration file and use PHP5 technology. Specific to ClicShoppingAI subsequent features only work in this configuration mode. <br />
You also will strengthen the security of your server and tool <br /></strong>
     <p> Please continue the installation procedure. </p>
');

//---------------------------
// Step 3
//---------------------------

define('TEXT_STEP_INTRO', '<strong> We strongly advise you to be on a MYSQL 5.3+ or you can use also other database such MariaDB ... </strong> server technology <p>');
define('TEXT_STEP_INTRO_1', 'Step 1: Configure the server database');

define('TEXT_STEP_INTRO_2', '
     <p>Server database allows you to store all of your information you will incorporate, products, customers, orders ....</p>
     <p>Note : Before to start, please create a database via phpmyadmin module and retain the password and username.</p>
 ');


define('TEXT_DATABASE_SERVER', 'Database Server<br />');
define('TEXT_DATABASE_SERVER_HELP', 'Enter the server address of your database or IP');
define('TEXT_USERNAME', 'User Name<br />');
define('TEXT_USERNAME_HELP', 'Enter the user name of database server');
define('TEXT_PASSWORD', 'Password<br />');
define('TEXT_PASSWORD_HELP', 'Enter the password for the database server.');
define('TEXT_DATABASE_NAME', 'Database names<br />');
define('TEXT_DATABASE_HELP', 'Indicate the name of your database (must be created on the server database)');


//---------------------------
// Step 4
//--------------------------

define('TEXT_STEP_INTRO_STEP4', '<p>The installation process achieve the setting directories and paths for setting files. </p>
<p>Please follow the instructions given to you during the installation process. </p>');
define('TEXT_STEP_INTRO_3', 'Step 2: Internet Server');
define('TEXT_STEP_INTRO_4', '<p>The web server allows you to view the pages of your products to your customers.</p>');
define('TEXT_STEP_HELP_4', 'Indicate the internet address of your ClicShoppingAI Store');
define('TEXT_STEP_HELP_5', 'Directory of your ClicShoppingAI store on the server.');
define('TEXT_STEP_INTRO_5', 'Directory Root of your internet server<br />');


//---------------------------
// Step 5
//--------------------------

define('TEXT_END_CONFIGURATION', 'Last step of setting');
define('TEXT_INFO_1', '<p>This is the last step to setup your online store ClicShoppingAI.</p><p> Please take care to fill in all the fields below.</p>');
define('TEXT_INFO_2', 'Step 3: setting access to ClicShopping store');
define('TEXT_INFO_3', '<p>You can set the name of your shop ClicShoppingAI, contact information for the owner of the shop. </p> <p> The username and password are protected to login to your admin area.. </p>');

define('TEXT_STORE_NAME', 'Store name*');
define('TEXT_STORE_HELP', 'Indicate the name of the shop that will be displayed ClicShopping your customers.');
define('TEXT_STORE_NAME_ADMIN', 'Administrator name*');
define('TEXT_STORE_NAME_ADMIN_HELP', 'Enter the  name of the administrator of the store.');
define('TEXT_STORE_FIRST_NAME', 'Administrator first name*');
define('TEXT_STORE_FIRST_NAME_HELP', 'Enter the first name of the administrator of the store.');
define('TEXT_STORE_OWNER', 'Owner Name*');
define('TEXT_STORE_OWNER_HELP', 'Enter the name of the store owner.');
define('TEXT_STORE_OWNER_EMAIL', 'The email address of the owner*');
define('TEXT_STORE_OWNER_EMAIL_HELP', 'Enter your email address');
define('TEXT_STORE_EMAIL_ADMIN', 'Access email administrator/');
define('TEXT_STORE_EMAIL_ADMIN_HELP', 'Enter your email to access at ClicShopping administration.');
define('TEXT_STORE_PASSWORD', 'Administrator password*');
define('TEXT_STORE_PASSWORD_HELP', 'The password must be strong (ex : UiO/J-4). Better the administration security will be it');
define('TEXT_STORE_ADMIN_EMAIL_HELP', 'Enter the email of the administrator of the store to connect in the administration panel.');
define('TEXT_STORE_DIRECTORY', 'Administration Directory Name');
define('TEXT_STORE_DIRECTORY_HELP', 'This is the directory where the administration section will be installed. You should change this for security reasons.');
define('TEXT_STORE_TIME_ZONE', 'Time Zone<br />');
define('TEXT_STORE_TIME_ZONE_HELP', 'The time zone to base the date and time on');
define('TEXT_STORE_MANDATORY', 'Mandatory');
define('TEXT_STORE_DONATION', ' If you like the work developped on this software, you can realize a donation for us by e-Imaginis french association to encourage us');

//---------------------------
// Step 6
//--------------------------

define('TEXT_END_INSTALLATION', 'Step 4: Finish !');
define('TEXT_END_INSTALLATION_1', '
<p> Congratulations , you have successfully configure your ClicShopping Store! </p>
<p> We wish you great success with your e-commerce project . </p>
<p> If you enjoy the work we do , so do not hesitate to communicate the project to your friends and your environment.
If you want to help us to continue to  develop this project , please , subscribe to one of our subscriptions, e-commerce. You have also access at our forum to share with the community your experience.</p>
<p> <br /> <strong>Your comments and suggestions </strong > are important for us for the futur development of this project because we want a to develop one of best tools existing on the market. </p>
<p align="right">ClicShopping Team</p>
');

define('TEXT_END_INSTALLATION_SUCCESS', 'The installation is a success, you can now access at your ClicShopping store!<br />Click the button and please wait, we will cache some files');

define('TEXT_END_INSTALLATION_2', 'Post-Installation Notes');
define('TEXT_END_INSTALLATION_3', '<p>It is recommended to delete the install directory to secure ClicShopping</p>');
define('TEXT_END_INSTALLATION_4', 'Directories  to change');
define('TEXT_END_INSTALLATION_5', 'Please turn change the permissions of this file in Conf (all), Shop and ClicShoppingAdmin (conf.php)');
define('TEXT_END_INSTALLATION_6', ' to 444 (or 644 if this file is still writable)');

define('TEXT_END_INSTALLATION_7', 'Increase the security ');
define('TEXT_END_INSTALLATION_8', 'The Best is to change the clickShoppingadmin Directory and to incorporate a htacces or Password (all servers do not necessarily accept this approach)');

define('TEXT_END_ACCESS_CATALOG', 'Go to your catalog');
define('TEXT_END_ACCESS_ADMIN', 'Go to your administration');


define('TEXT_TITLE_CONFIGURATION', 'Online Store Settings');
define('TEXT_TITLE_SHOP', 'Configure your store');
define('TEXT_INTRO_SHOP', 'This space concerns information to insert concerning your shop.');
define('TEXT_TITLE_ACCESS', 'Setting up access to your administration');
define('TEXT_TITLE_STMP', 'SMTP Configuration');
define('TEXT_INTRO_SMTP', 'This space concerns the configuration of sending your emails if you want to configure it. Some companies block SendMail for security reasons. The default SMTP port is Sendmail which will be used. <br /><p style="color:red">It is not mandatory to fill the fields below if you want to use Sendmail</p>');
define('TEXT_SMTP_EMAIL_TRANSORT', 'Email transort');
define('TEXT_SMTP_HOST', 'SMTP Host');
define('TEXT_SMTP_PORT', 'SMTP Port');
define('TEXT_SMTP_PORT_INFO', 'For gmail you have the choice in port 25, 465 if you use SSL, 587 if you use TLS');
define('TEXT_SMTP_USERNAME', 'User Name (e-mail)');
define('TEXT_SMTP_USERNAME_INFO', 'Please indicate your user name regarding your email. This is not necessarily related to your admin account');
define('TEXT_SMTP_PASSWORD', 'Password');
define('TEXT_SMTP_PASSWORD_INFO', 'Please enter your password to access your email');
define('TEXT_INFO_DATABASE', 'Install the Database and demo');
define('TEXT_ALERT_DATABASE', 'To continue, it is recommended to have <strong>version 11.7 of MariaDB on your server. You can use a lower version (mariaDb / MySql) but you will not be able to use the RAG technology</strong>.');