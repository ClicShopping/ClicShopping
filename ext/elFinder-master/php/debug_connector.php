<?php
// Debug connector pour elFinder
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== DEBUG ELFINDER ===\n";
echo "GET: " . print_r($_GET, true) . "\n";
echo "POST: " . print_r($_POST, true) . "\n";
echo "REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? 'none') . "\n";

// Si c'est une requête AJAX, retourner JSON
if (isset($_GET['cmd']) || isset($_POST['cmd'])) {
    require './autoload.php';
    
    $opts = array(
        'debug' => true,
        'roots' => array(
            array(
                'driver' => 'LocalFileSystem',
                'path' => '../../../images/',
                'URL' => '../../../images/',
                'uploadDeny' => array('all'),
                'uploadAllow' => array('image', 'text/plain'),
                'uploadOrder' => array('deny', 'allow'),
                'uploadMaxSize' => '10M'
            )
        )
    );
    
    try {
        $connector = new elFinderConnector(new elFinder($opts));
        $connector->run();
    } catch (Exception $e) {
        echo "ERREUR: " . $e->getMessage() . "\n";
        echo "Trace: " . $e->getTraceAsString() . "\n";
    }
} else {
    echo "Pas de commande elFinder détectée\n";
}
?>



