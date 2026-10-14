# Guide d'implémentation sécurisée d'elFinder

## ⚠️ IMPORTANT : Configuration de sécurité

### 1. Inclure les patches de sécurité

```php
// Au début de votre fichier d'initialisation d'elFinder
require_once 'secure_config.php';
require_once 'security_patch.php';

// Utiliser la configuration sécurisée
$opts = array_merge($opts, $secure_elfinder_config);
```

### 2. Configuration recommandée

```php
$opts = [
    'roots' => [
        [
            'driver' => 'LocalFileSystem',
            'path' => '/path/to/secure/upload/directory',
            'URL' => '/secure/uploads/',
            
            // Configuration sécurisée
            'uploadAllow' => $secure_upload_allow,
            'uploadDeny' => $secure_upload_deny,
            'uploadOrder' => ['deny', 'allow'],
            'uploadMaxSize' => '10M',
            'uploadMaxMkdirs' => 5,
            'uploadMaxConn' => 2,
            
            // Désactiver les fonctions dangereuses
            'disabled' => ['mkfile', 'edit', 'rename', 'resize', 'archive', 'extract'],
            
            // Permissions strictes
            'attributes' => [
                [
                    'pattern' => '/.*/',
                    'read' => true,
                    'write' => false,
                    'locked' => true
                ]
            ],
            
            // Validation des noms
            'acceptedName' => '/^[a-zA-Z0-9._-]+$/',
            
            // Dossier en lecture seule
            'readOnly' => true
        ]
    ],
    
    // Hooks de sécurité
    'hooks' => [
        'upload.presave' => 'elFinderSecurityHook'
    ]
];
```

### 3. Vérifications supplémentaires

#### A. Vérifier les permissions du serveur
```bash
# Le dossier d'upload doit être en lecture seule
chmod 755 /path/to/secure/upload/directory
chown www-data:www-data /path/to/secure/upload/directory
```

#### B. Configuration PHP sécurisée
```ini
; php.ini
file_uploads = On
upload_max_filesize = 10M
max_file_uploads = 5
post_max_size = 12M
max_execution_time = 30
memory_limit = 128M

; Désactiver l'exécution de scripts dans le dossier d'upload
; Ajouter dans .htaccess du dossier d'upload :
; php_flag engine off
; AddType text/plain .php .phtml .php3 .php4 .php5
```

#### C. .htaccess pour le dossier d'upload
```apache
# Désactiver l'exécution de scripts
php_flag engine off
AddType text/plain .php .phtml .php3 .php4 .php5 .php7 .php8

# Interdire l'accès direct aux fichiers
<Files "*.php">
    Order Deny,Allow
    Deny from all
</Files>

# Headers de sécurité
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

### 4. Surveillance et logging

```php
// Ajouter dans votre application
function logUploadAttempt($file, $result) {
    $log_entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'filename' => $file['name'],
        'size' => $file['size'],
        'mime_type' => $file['type'],
        'result' => $result
    ];
    
    error_log('UPLOAD_ATTEMPT: ' . json_encode($log_entry));
}
```

### 5. Tests de sécurité

#### A. Tester l'upload de fichiers malveillants
```php
// Tester ces types de fichiers (doivent être rejetés) :
$malicious_files = [
    'test.php',
    'test.phtml',
    'test.php3',
    'test.asp',
    'test.jsp',
    'test.cgi',
    'test.sh',
    'test.bat',
    'test.exe',
    'test.jpg.php',
    'test.php.jpg',
    'test<script>alert(1)</script>.jpg'
];
```

#### B. Tester les noms de fichiers
```php
// Tester ces noms (doivent être rejetés) :
$malicious_names = [
    '../../../etc/passwd',
    '..\\..\\..\\windows\\system32\\config\\sam',
    'test<script>alert(1)</script>.jpg',
    'test\x00.jpg',
    'CON',
    'PRN',
    'AUX'
];
```

## ✅ Checklist de sécurité

- [ ] - Configuration sécurisée appliquée
- [ ] - Patches de sécurité inclus
- [ ] - Dossier d'upload en lecture seule
- [ ] - .htaccess configuré
- [ ] - Logging activé
- [ ] - Tests de sécurité effectués
- [ ] - Permissions serveur vérifiées
- [ ] - Types MIME validés
- [ ] - Extensions de fichiers validées
- [ ] - Contenu des fichiers vérifié

## 🚨 En cas d'incident

1. **Désactiver immédiatement** elFinder
2. **Scanner** le dossier d'upload pour des fichiers suspects
3. **Vérifier** les logs d'accès
4. **Nettoyer** les fichiers malveillants
5. **Mettre à jour** la configuration de sécurité
6. **Tester** la nouvelle configuration
7. **Réactiver** elFinder avec la nouvelle configuration
