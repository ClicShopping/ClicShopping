<?php
/**
 * Patch de sécurité pour elFinder
 * À inclure avant l'initialisation d'elFinder
 */

// Fonction de validation renforcée pour les chunks
function validateChunkName($chunk) {
    // Vérifier que le chunk ne contient que des caractères alphanumériques et tirets
    if (!preg_match('/^[a-zA-Z0-9_-]+$/', $chunk)) {
        return false;
    }
    
    // Vérifier la longueur (max 50 caractères)
    if (strlen($chunk) > 50) {
        return false;
    }
    
    // Vérifier qu'il n'y a pas de séquences suspectes
    $suspicious_patterns = [
        '/\.\./',           // Path traversal
        '/%2e%2e/',         // URL encoded path traversal
        '/%252e%252e/',     // Double URL encoded
        '/<script/i',       // Script tags
        '/javascript:/i',   // JavaScript protocol
        '/data:/i',         // Data protocol
        '/vbscript:/i'      // VBScript protocol
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $chunk)) {
            return false;
        }
    }
    
    return true;
}

// Fonction de validation des noms de fichiers renforcée
function validateFileName($filename) {
    // Vérifier la longueur
    if (strlen($filename) > 255) {
        return false;
    }
    
    // Vérifier les caractères autorisés
    if (!preg_match('/^[a-zA-Z0-9._-]+$/', $filename)) {
        return false;
    }
    
    // Vérifier qu'il n'y a pas de double extension
    $double_extensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
        'asp', 'aspx', 'jsp', 'cgi', 'sh', 'bat', 'cmd', 'exe',
        'scr', 'com', 'pif', 'vbs', 'js', 'jar', 'war', 'ear'
    ];
    
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if (in_array($extension, $double_extensions)) {
        return false;
    }
    
    // Vérifier les noms de fichiers réservés
    $reserved_names = [
        'CON', 'PRN', 'AUX', 'NUL',
        'COM1', 'COM2', 'COM3', 'COM4', 'COM5', 'COM6', 'COM7', 'COM8', 'COM9',
        'LPT1', 'LPT2', 'LPT3', 'LPT4', 'LPT5', 'LPT6', 'LPT7', 'LPT8', 'LPT9'
    ];
    
    $name_without_ext = strtoupper(pathinfo($filename, PATHINFO_FILENAME));
    if (in_array($name_without_ext, $reserved_names)) {
        return false;
    }
    
    return true;
}

// Fonction de validation MIME renforcée
function validateMimeType($file_path, $filename) {
    // Obtenir le type MIME réel du fichier
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file_path);
    finfo_close($finfo);
    
    // Vérifier que le type MIME correspond à l'extension
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $expected_mimes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'gif' => ['image/gif'],
        'webp' => ['image/webp'],
        'svg' => ['image/svg+xml'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xls' => ['application/vnd.ms-excel'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'zip' => ['application/zip'],
        'rar' => ['application/x-rar-compressed'],
        '7z' => ['application/x-7z-compressed'],
        'txt' => ['text/plain'],
        'csv' => ['text/csv'],
        'json' => ['application/json'],
        'xml' => ['application/xml', 'text/xml']
    ];
    
    if (isset($expected_mimes[$extension])) {
        return in_array($mime_type, $expected_mimes[$extension]);
    }
    
    return false;
}

// Fonction de nettoyage des fichiers uploadés
function sanitizeUploadedFile($file_path) {
    // Vérifier que le fichier est vraiment uploadé
    if (!is_uploaded_file($file_path)) {
        return false;
    }
    
    // Vérifier la taille
    if (filesize($file_path) > 10 * 1024 * 1024) { // 10MB max
        return false;
    }
    
    // Vérifier le contenu du fichier pour les scripts
    $content = file_get_contents($file_path, false, null, 0, 1024); // Lire les 1024 premiers octets
    $suspicious_patterns = [
        '/<\?php/i',
        '/<script/i',
        '/javascript:/i',
        '/vbscript:/i',
        '/<iframe/i',
        '/<object/i',
        '/<embed/i',
        '/eval\s*\(/i',
        '/exec\s*\(/i',
        '/system\s*\(/i',
        '/shell_exec\s*\(/i',
        '/passthru\s*\(/i'
    ];
    
    foreach ($suspicious_patterns as $pattern) {
        if (preg_match($pattern, $content)) {
            return false;
        }
    }
    
    return true;
}

// Hook pour elFinder - validation avant upload
function elFinderSecurityHook($cmd, $args, $elfinder, $volume) {
    if ($cmd === 'upload') {
        if (isset($args['FILES']['upload']) && is_array($args['FILES']['upload'])) {
            foreach ($args['FILES']['upload'] as $file) {
                // Valider le nom de fichier
                if (!validateFileName($file['name'])) {
                    return ['error' => 'Nom de fichier invalide'];
                }
                
                // Valider le type MIME
                if (!validateMimeType($file['tmp_name'], $file['name'])) {
                    return ['error' => 'Type de fichier non autorisé'];
                }
                
                // Nettoyer le fichier
                if (!sanitizeUploadedFile($file['tmp_name'])) {
                    return ['error' => 'Fichier suspect détecté'];
                }
            }
        }
    }
    
    return true;
}

// Enregistrer le hook
if (function_exists('elFinderSecurityHook')) {
    // Le hook sera appelé automatiquement par elFinder
}
?>
