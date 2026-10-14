<?php
// Test simple pour diagnostiquer elFinder
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== TEST ELFINDER ===\n";
echo "GET parameters: " . print_r($_GET, true) . "\n";
echo "POST parameters: " . print_r($_POST, true) . "\n";
echo "SERVER info: " . print_r($_SERVER, true) . "\n";

// Test de base sans sécurité
require './autoload.php';

$opts = array(
  'debug' => true,
  'roots' => array(
    array(
      'driver' => 'LocalFileSystem',
      'path' => '../../../images/',
      'URL' => '../../../images/',
      'uploadDeny' => array('all'),
      'uploadAllow' => array('image'),
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
?>

