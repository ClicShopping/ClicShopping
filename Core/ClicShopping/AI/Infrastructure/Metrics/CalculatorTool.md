# 📘 Guide d'Installation Complet - CalculatorTool pour RAGBI

## 🎯 Vue d'ensemble

Le **CalculatorTool** est un outil de calcul mathématique avancé intégré au système RAGBI (Retrieval-Augmented Generation Business Intelligence). Il permet d'effectuer des opérations mathématiques complexes de manière sécurisée dans le contexte de vos analyses métier.

### ✨ Fonctionnalités principales

- ✅ **Calculs mathématiques** : opérations de base, fonctions, constantes
- ✅ **Statistiques** : moyenne, médiane, écart-type, variance
- ✅ **Résolution d'équations** : linéaires et quadratiques
- ✅ **Cache intelligent** : mise en cache des résultats pour optimiser les performances
- ✅ **Logging complet** : traçabilité de tous les calculs effectués
- ✅ **Sécurité renforcée** : validation stricte des expressions
- ✅ **Intégration agents** : fonctionne avec TaskPlanner et PlanExecutor

---

## 📦 Prérequis

- PHP 8.0 ou supérieur
- ClicShopping avec le module ChatGPT installé
- Système RAGBI opérationnel (OrchestratorAgent, TaskPlanner, PlanExecutor)
- Extensions PHP : `bcmath` (recommandé), `json`, `pdo_mysql`
  -# 📘 Guide d'Installation Complet - CalculatorTool pour RAGBI

## 🎯 Vue d'ensemble

Le **CalculatorTool** est un outil de calcul mathématique avancé intégré au système RAGBI (Retrieval-Augmented Generation Business Intelligence). Il permet d'effectuer des opérations mathématiques complexes de manière sécurisée dans le contexte de vos analyses métier.

---

## 📦 Prérequis

- PHP 8.0 ou supérieur
- ClicShopping avec le module ChatGPT installé
- Système RAGBI opérationnel (OrchestratorAgent, TaskPlanner, PlanExecutor)
- Extensions PHP : `bcmath` (recommandé), `json`

---

## 🚀 Installation Étape par Étape

### Étape 1 : Copier les fichiers

```bash
# Copier le fichier CalculatorTool.php
cp CalculatorTool.php \
   Apps/Configuration/ChatGpt/Classes/Tools/Statistics/CalculatorTool.php

# Copier le fichier de configuration
cp calculator_config.php \
   Apps/Configuration/ChatGpt/Config/Statistics/calculator_config.php
```

### Étape 2 : Ajouter les constantes de configuration

Ajouter dans `Apps/Configuration/ChatGpt/configure.php` :

```php
// CalculatorTool Configuration (Admin-level only)
define('CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED', 'True');

// Technical settings are defined as class constants in CalculatorTool.php:
// - MAX_HISTORY_SIZE = 100
// - STRICT_VALIDATION = true
// - MAX_EXECUTION_TIME = 5 seconds
// - CACHE_TTL = 3600 seconds (1 hour)

// CalculatorTool uses global RAG configuration for cache and logging:
// - Cache: CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER
// - Debug/Logging: CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER
```

### Étape 3 : Exécuter l'installation

```bash
cd Apps/Configuration/ChatGpt/Config
php calculator_config.php install
```

Cela va :
- ✅ Créer la table `calculator_cache`
- ✅ Créer la table `calculator_logs`
- ✅ Vérifier les dépendances

### Étape 4 : Modifier PlanExecutor.php

**Ajouter la propriété** :
```php
private ?CalculatorTool $calculatorTool = null;
```

**Modifier le constructeur** :
```php
public function __construct(
  TaskPlanner $planner,
  string $userId = 'system',
  int $languageId = 1
) {
  // ... code existant ...
  
  // Initialiser le CalculatorTool
  if (defined('CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED') 
      && CLICSHOPPING_APP_CHATGPT_CALCULATOR_ENABLED === 'True') {
    $this->calculatorTool = new CalculatorTool();
  }
}
```

**Ajouter le cas 'calculation'** dans `executeStepByType()` :
```php
case 'calculation':
  if (!$this->calculatorTool) {
    throw new \Exception("CalculatorTool not initialized");
  }
  return $this->executeCalculation($step, $context);
```

**Ajouter la méthode** `executeCalculation()` (voir le fichier d'intégration)

### Étape 5 : Modifier PromptSystem.php

**Ajouter 'calculation'** dans `ALLOWED_STEP_TYPES` :
```php
private const ALLOWED_STEP_TYPES = [
  'semantic_search',
  'analytics_query',
  'calculation',        // 🆕 NOUVEAU
  'validation_check',
  // ... autres types ...
];
```

**Mettre à jour** `getTaskPlannerSystemPrompt()` pour inclure les exemples de calcul (voir fichier de configuration)

### Étape 6 : Tester l'installation

```bash
php calculator_config.php test
```

Résultat attendu :
```
╔═══════════════════════════════════════════════════════╗
║   TESTS POST-INSTALLATION                           ║
╚═══════════════════════════════════════════════════════╝

Test 1: Classe CalculatorTool... ✅ PASS
Test 2: Configurations... ✅ PASS
Test 3: Instanciation... ✅ PASS
Test 4: Calcul simple (2 + 2)... ✅ PASS
Test 5: Fonction sqrt(16)... ✅ PASS
Test 6: Variables (x * 2, x=5)... ✅ PASS
Test 7: Statistiques (moyenne)... ✅ PASS
Test 8: Sécurité (eval bloqué)... ✅ PASS

==================================================
✅ TOUS LES TESTS SONT PASSÉS!
Le CalculatorTool est prêt à être utilisé.
==================================================
```

---

## [INFO] Structure des Tables Créées

### Table `calculator_cache`
```sql
CREATE TABLE :table_rag_calculator_cache (
  cache_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  expression VARCHAR(500) NOT NULL,
  expression_hash VARCHAR(64) NOT NULL,
  result DOUBLE NOT NULL,
  result_type VARCHAR(50) NOT NULL,
  variables JSON DEFAULT NULL,
  execution_time FLOAT NOT NULL,
  created_at DATETIME NOT NULL,
  last_accessed DATETIME NOT NULL,
  access_count INT(11) UNSIGNED DEFAULT 0,
  PRIMARY KEY (cache_id),
  UNIQUE KEY idx_expression_hash (expression_hash)
);
```

### Table `calculator_logs`
```sql
CREATE TABLE :table_rag_calculator_logs (
  log_id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id VARCHAR(100) NOT NULL,
  expression TEXT NOT NULL,
  result DOUBLE DEFAULT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  error_message TEXT DEFAULT NULL,
  execution_time FLOAT NOT NULL,
  step_id VARCHAR(100) DEFAULT NULL,
  plan_id VARCHAR(100) DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (log_id)
);
```

---

## 🧪 Tests d'Intégration

### Test manuel rapide

```php
use ClicShopping\AI\Tools\CalculatorTool;

$calc = new CalculatorTool();

// Test 1 : Calcul simple
$result = $calc->calculate('2 + 2 * 3');
echo $result['result']; // 8

// Test 2 : Avec variables
$result = $calc->calculate('(price - cost) / price * 100', [
    'price' => 100,
    'cost' => 60
]);
echo $result['result']; // 40

// Test 3 : Statistiques
$result = $calc->calculateStatistic([10, 20, 30, 40, 50], 'avg');
echo $result['result']; // 30
```

### Test avec l'orchestrateur

```php
$orchestrator = new OrchestratorAgent('test_user', 1);

$result = $orchestrator->processWithValidation(
    "Calcule la marge bénéficiaire moyenne si les prix sont 100, 200, 50 et les coûts 60, 150, 30"
);

print_r($result);
```

---

## 📖 Exemples d'Utilisation

### Exemple 1 : Calcul de Marge Bénéficiaire

**Question utilisateur** :
> "Quelle est la marge bénéficiaire moyenne des produits électroniques ?"

**Plan généré** :
```json
{
  "steps": [
    {
      "id": "step_1",
      "type": "analytics_query",
      "description": "Récupérer prix et coûts"
    },
    {
      "id": "step_2",
      "type": "calculation",
      "metadata": {
        "calculation_type