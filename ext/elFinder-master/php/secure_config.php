<?php
/**
 * Configuration sécurisée pour elFinder
 * Ce fichier doit être inclus dans la configuration d'elFinder
 */

// Types MIME autorisés (whitelist stricte)
$secure_upload_allow = [
    // Images
    'image/jpeg',
    'image/png', 
    'image/gif',
    'image/webp',
    'image/svg+xml',
    
    // Documents
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    
    // Archives
    'application/zip',
    'application/x-rar-compressed',
    'application/x-7z-compressed',
    
    // Texte
    'text/plain',
    'text/csv',
    'application/json',
    'application/xml',
    'text/xml'
];

// Types MIME interdits (blacklist)
$secure_upload_deny = [
    // Scripts exécutables
    'application/x-php',
    'text/x-php',
    'application/x-httpd-php',
    'application/x-httpd-php-source',
    'application/x-httpd-php3',
    'application/x-httpd-php4',
    'application/x-httpd-php5',
    'application/x-httpd-php7',
    'application/x-httpd-php8',
    
    // Scripts serveur
    'application/x-asp',
    'application/x-aspnet',
    'application/x-jsp',
    'application/x-cgi',
    
    // Exécutables
    'application/x-executable',
    'application/x-msdownload',
    'application/x-msdos-program',
    'application/x-winexe',
    'application/x-msi',
    
    // Scripts shell
    'application/x-sh',
    'application/x-bash',
    'application/x-csh',
    'application/x-ksh',
    'application/x-zsh'
];

// Extensions autorisées (validation supplémentaire)
$secure_extensions_allow = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
    'pdf', 'doc', 'docx', 'xls', 'xlsx',
    'zip', 'rar', '7z',
    'txt', 'csv', 'json', 'xml'
];

// Configuration sécurisée
$secure_elfinder_config = [
    'uploadAllow' => $secure_upload_allow,
    'uploadDeny' => $secure_upload_deny,
    'uploadOrder' => ['deny', 'allow'], // D'abord interdire, puis autoriser
    'uploadMaxSize' => '10M', // Taille maximale 10MB
    'uploadMaxMkdirs' => 5, // Maximum 5 dossiers par upload
    'uploadMaxConn' => 2, // Maximum 2 connexions simultanées
    'acceptedName' => '/^[a-zA-Z0-9._-]+$/', // Noms de fichiers stricts
    'disabled' => ['mkfile', 'edit', 'rename', 'resize'], // Désactiver certaines fonctions
    'attributes' => [
        [
            'pattern' => '/.*/',
            'read' => true,
            'write' => false, // Lecture seule par défaut
            'locked' => true
        ]
    ]
];

// Fonction de validation supplémentaire
function validateUploadedFile($file) {
    global $secure_extensions_allow;
    
    // Vérifier l'extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, $secure_extensions_allow)) {
        return false;
    }
    
    // Vérifier la taille (10MB max)
    if ($file['size'] > 10 * 1024 * 1024) {
        return false;
    }
    
    // Vérifier le nom de fichier
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $file['name'])) {
        return false;
    }
    
    // Vérifier qu'il n'y a pas de double extension
    if (preg_match('/\.(php|phtml|php3|php4|php5|php7|php8|asp|aspx|jsp|cgi|sh|bat|cmd|exe|scr|com|pif|vbs|js|jar|war|ear)$/i', $file['name'])) {
        return false;
    }
    
    return true;
}

return $secure_elfinder_config;
?>
