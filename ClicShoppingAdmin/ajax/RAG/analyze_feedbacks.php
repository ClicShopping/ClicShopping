<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;
use ClicShopping\Apps\Configuration\ChatGpt\Classes\ClicShoppingAdmin\Gpt;
use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;

define('CLICSHOPPING_BASE_DIR', realpath(__DIR__ . '/../../../Core/ClicShopping/') . DIRECTORY_SEPARATOR);

require_once(CLICSHOPPING_BASE_DIR . 'OM/CLICSHOPPING.php');
spl_autoload_register('ClicShopping\OM\CLICSHOPPING::autoload');

CLICSHOPPING::initialize();
CLICSHOPPING::loadSite('ClicShoppingAdmin');
AdministratorAdmin::hasUserAccess();

header('Content-Type: application/json');

try {
    $db = Registry::get('Db');

    // Récupérer les paramètres
    $rawInput = file_get_contents('php://input');
    $input = json_decode($rawInput, true);

    $type = $input['type'] ?? 'all'; // 'positive', 'negative', 'all'
    $periodDays = (int) ($input['period_days'] ?? 30);

    // Construire la requête selon le type
    $whereClause = "f.date_added >= DATE_SUB(NOW(), INTERVAL :period_days DAY)";

    if ($type === 'positive') {
        $whereClause .= " AND f.feedback_type = 'positive'";
        $typeLabel = 'Feedbacks Positifs';
    } elseif ($type === 'negative') {
        $whereClause .= " AND f.feedback_type = 'negative'";
        $typeLabel = 'Feedbacks Négatifs';
    } else {
        $typeLabel = 'Analyse Complète';
    }

    // Récupérer les feedbacks avec les interactions
    $feedbackQuery = $db->prepare("
    SELECT 
      f.feedback_type,
      f.feedback_data,
      f.date_added,
      i.question,
      i.response,
      i.request_type
    FROM :table_rag_feedback f
    LEFT JOIN :table_rag_interactions i ON f.interaction_id = i.interaction_id
    WHERE {$whereClause}
    ORDER BY f.date_added DESC
    LIMIT 50
  ");

    $feedbackQuery->bindInt(':period_days', $periodDays);
    $feedbackQuery->execute();

    $feedbacks = [];
    while ($feedbackQuery->fetch()) {
        $feedbackData = json_decode($feedbackQuery->value('feedback_data'), true);

        $feedbacks[] = [
            'type' => $feedbackQuery->value('feedback_type'),
            'question' => $feedbackQuery->value('question'),
            'response' => $feedbackQuery->value('response'),
            'comment' => $feedbackData['feedback_text'] ?? '',
            'rating' => $feedbackData['rating'] ?? null,
            'request_type' => $feedbackQuery->value('request_type'),
            'date' => $feedbackQuery->value('date_added')
        ];
    }

    if (empty($feedbacks)) {
        echo json_encode([
            'success' => false,
            'error' => 'Aucun feedback trouvé pour la période sélectionnée'
        ]);
        exit;
    }

    // Construire le prompt pour l'IA
    $prompt = buildAnalysisPrompt($feedbacks, $type, $typeLabel);

    // Appeler l'API GPT via la méthode getGptResponse
    $gpt = new Gpt();
    $analysis = $gpt->getGptResponse($prompt, 1500, 0.7, null, 1);

    // Parser l'analyse
    $parsedAnalysis = parseAnalysis($analysis);

    // Retourner le résultat
    echo json_encode([
        'success' => true,
        'type' => $type,
        'type_label' => $typeLabel,
        'feedbacks_analyzed' => count($feedbacks),
        'period_days' => $periodDays,
        'summary' => $parsedAnalysis['summary'] ?? '',
        'patterns' => $parsedAnalysis['patterns'] ?? [],
        'actions' => $parsedAnalysis['actions'] ?? [],
        'full_analysis' => $analysis
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

exit;

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function buildAnalysisPrompt(array $feedbacks, string $type, string $typeLabel): string
{
    $prompt = "Tu es un expert en analyse de feedbacks utilisateur pour un système d'IA conversationnelle.\n\n";

    if ($type === 'negative') {
        $prompt .= "Analyse les feedbacks NÉGATIFS suivants et identifie:\n";
        $prompt .= "1. Les problèmes récurrents\n";
        $prompt .= "2. Les patterns d'échec\n";
        $prompt .= "3. Les actions prioritaires pour corriger ces problèmes\n\n";
    } elseif ($type === 'positive') {
        $prompt .= "Analyse les feedbacks POSITIFS suivants et identifie:\n";
        $prompt .= "1. Les bonnes pratiques qui fonctionnent\n";
        $prompt .= "2. Les patterns de succès\n";
        $prompt .= "3. Les fonctionnalités à renforcer\n\n";
    } else {
        $prompt .= "Analyse TOUS les feedbacks suivants et fournis:\n";
        $prompt .= "1. Un résumé global de la satisfaction\n";
        $prompt .= "2. Les points forts et points faibles\n";
        $prompt .= "3. Les actions prioritaires d'amélioration\n\n";
    }

    $prompt .= "FEEDBACKS (" . count($feedbacks) . " au total):\n\n";

    foreach ($feedbacks as $idx => $feedback) {
        $prompt .= "--- Feedback " . ($idx + 1) . " ---\n";
        $prompt .= "Type: " . $feedback['type'] . "\n";
        $prompt .= "Question: " . substr($feedback['question'], 0, 200) . "\n";
        $prompt .= "Réponse: " . substr($feedback['response'], 0, 200) . "\n";

        if (!empty($feedback['comment'])) {
            $prompt .= "Commentaire: " . $feedback['comment'] . "\n";
        }

        if ($feedback['rating']) {
            $prompt .= "Note: " . $feedback['rating'] . "/5\n";
        }

        $prompt .= "\n";
    }

    $prompt .= "\nFournis ton analyse au format suivant:\n\n";
    $prompt .= "RÉSUMÉ:\n[Résumé en 2-3 phrases]\n\n";
    $prompt .= "PATTERNS IDENTIFIÉS:\n- [Pattern 1]\n- [Pattern 2]\n- [Pattern 3]\n\n";
    $prompt .= "ACTIONS PRIORITAIRES:\n";
    $prompt .= "1. [Titre action 1]: [Description]\n";
    $prompt .= "2. [Titre action 2]: [Description]\n";
    $prompt .= "3. [Titre action 3]: [Description]\n";

    return $prompt;
}

function parseAnalysis(string $analysis): array
{
    $result = [
        'summary' => '',
        'patterns' => [],
        'actions' => []
    ];

    // Extraire le résumé
    if (preg_match('/RÉSUMÉ:\s*(.*?)(?=PATTERNS|$)/s', $analysis, $matches)) {
        $result['summary'] = trim($matches[1]);
    }

    // Extraire les patterns
    if (preg_match('/PATTERNS IDENTIFIÉS:\s*(.*?)(?=ACTIONS|$)/s', $analysis, $matches)) {
        $patternsText = trim($matches[1]);
        $patterns = preg_split('/\n-\s*/', $patternsText);
        $result['patterns'] = array_filter(array_map('trim', $patterns));
    }

    // Extraire les actions
    if (preg_match('/ACTIONS PRIORITAIRES:\s*(.*?)$/s', $analysis, $matches)) {
        $actionsText = trim($matches[1]);
        preg_match_all('/\d+\.\s*([^:]+):\s*(.+?)(?=\d+\.|$)/s', $actionsText, $actionMatches, PREG_SET_ORDER);

        foreach ($actionMatches as $match) {
            $result['actions'][] = [
                'title' => trim($match[1]),
                'description' => trim($match[2])
            ];
        }
    }

    return $result;
}
