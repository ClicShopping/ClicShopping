<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Infrastructure\Metrics;


use ClicShopping\Apps\Configuration\Administrators\Classes\ClicShoppingAdmin\AdministratorAdmin;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\Registry;

use ClicShopping\AI\Security\SecurityLogger;
use ClicShopping\AI\Security\InputValidator;
use ClicShopping\AI\Infrastructure\Metrics\SubMetrics\CalculatorCache;
use ClicShopping\AI\Infrastructure\Metrics\SubMetrics\CalculatorLogger;

/**
 * CalculatorTool Class
 * Advanced calculation tool for RAGBI system
 * Performs secure mathematical operations
 * 
 * Features:
 * - Basic operations (+, -, *, /, %, **)
 * - Mathematical functions (sin, cos, tan, sqrt, log, etc.)
 * - Constants (pi, e)
 * - Variables and expressions
 * - Strict validation and security
 * - Calculation history
 * - Decimal and scientific number support
 */

class CalculatorTool
{
  private SecurityLogger $securityLogger;
  private bool $debug;
  private array $calculationHistory = [];
  private int $maxHistorySize = 100;
  private mixed $db = null;
  private bool $enableCache = false;
  private bool $enableLogging = false;
  private CalculatorCache $calculatorCache;
  private CalculatorLogger $calculatorLogger;
  private int $cacheTTL = 3600;

  /**
   * Calculator Configuration Constants (2026-01-09)
   * 
   * Internal configuration values defined as class constants
   * Only CALCULATOR_ENABLED should be in global config for admin control
   */
  private const MAX_HISTORY_SIZE = 100;
  private const CACHE_TTL = 3600;

  private array $variables = [];

  private array $allowedFunctions = [
    // Trigonométrie
    'sin',
    'cos',
    'tan',
    'asin',
    'acos',
    'atan',
    'atan2',
    'sinh',
    'cosh',
    'tanh',
    'asinh',
    'acosh',
    'atanh',

    // Exponentielles et logarithmes
    'exp',
    'log',
    'log10',
    'log1p',
    'expm1',

    // Racines et puissances
    'sqrt',
    'pow',
    'hypot',

    // Arrondis
    'abs',
    'ceil',
    'floor',
    'round',

    // Comparaisons
    'min',
    'max',

    // Autres
    'deg2rad',
    'rad2deg',
    'fmod',
    'pi',
    'M_PI',
    'M_E'
  ];

  private array $constants = [
    'pi' => M_PI,
    'e' => M_E,
    'phi' => 1.618033988749895, // Nombre d'or
    'sqrt2' => M_SQRT2,
    'sqrt3' => 1.7320508075688772,
    'ln2' => M_LN2,
    'ln10' => M_LN10,
  ];

  /**
   * Constructor
   * Uses global RAG configuration for cache and debug settings
   * Technical settings defined as class constants
   * 
   * @return void
   */
  public function __construct()
  {
    $this->securityLogger = new SecurityLogger();
    $this->debug = defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER') && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

    if (Registry::exists('Db')) {
      $this->db = Registry::get('Db');
    }

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_CACHE_RAG_MANAGER === 'True') {
      $this->enableCache = true;
    }

    if (defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
      && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True') {
      $this->enableLogging = true;
    }

    $this->cacheTTL = self::CACHE_TTL;
    $this->maxHistorySize = self::MAX_HISTORY_SIZE;
    $this->calculatorCache = new CalculatorCache($this->db, $this->securityLogger, $this->debug, $this->cacheTTL);
    $this->calculatorLogger = new CalculatorLogger($this->db, $this->securityLogger, $this->debug, $this->enableLogging);

    if ($this->debug) {
      $this->securityLogger->logSecurityEvent(
        "CalculatorTool initialized (cache: " . ($this->enableCache ? 'ON' : 'OFF') .
        ", logging: " . ($this->enableLogging ? 'ON' : 'OFF') . 
        ", history: {$this->maxHistorySize}, cacheTTL: {$this->cacheTTL}s)",
        'info'
      );
    }
  }

  /**
   * Execute mathematical calculation
   *
   * @param string $expression Mathematical expression to evaluate
   * @param array $variables Optional variables (e.g. ['x' => 5, 'y' => 10])
   * @return array Calculation result
   */
  public function calculate(string $expression, array $variables = []): array
  {
    $startTime = microtime(true);

    try {
      // Validation de l'entrée
      $safeExpression = InputValidator::validateParameter($expression, 'string');

      if ($safeExpression !== $expression) {
        $this->securityLogger->logSecurityEvent(
          "Expression sanitized in calculate",
          'warning'
        );
        $expression = $safeExpression;
      }

      // Vérifier que l'expression n'est pas vide
      if (empty(trim($expression))) {
        return [
          'success' => false,
          'error' => 'Empty expression',
          'expression' => $expression,
        ];
      }

      $this->variables = array_merge($this->variables, $variables);

      if ($this->enableCache) {
        $cachedResult = $this->calculatorCache->getCachedResult($expression, $this->variables);
        if ($cachedResult !== null) {
          if ($this->debug) {
            $this->securityLogger->logSecurityEvent(
              "Cache hit for expression: " . substr($expression, 0, 50),
              'info'
            );
          }
          return $cachedResult;
        }
      }

      $preparedExpression = $this->prepareExpression($expression);

      if (!$this->validateSecurity($preparedExpression)) {
        throw new \Exception('Expression contains unsafe patterns');
      }

      $result = $this->evaluateExpression($preparedExpression);

      $response = [
        'success' => true,
        'result' => $result,
        'expression' => $expression,
        'prepared_expression' => $preparedExpression,
        'execution_time' => microtime(true) - $startTime,
        'type' => $this->detectResultType($result),
      ];

      if ($this->enableCache) {
        $this->calculatorCache->cacheResult($expression, $this->variables, $response);
      }

      if ($this->enableLogging) {
        $this->calculatorLogger->logCalculation($expression, $result, true, null, $response['execution_time']);
      }

      $this->addToHistory($expression, $result, $response['execution_time']);

      return $response;
    } catch (\Exception $e) {
      $executionTime = microtime(true) - $startTime;

      $this->securityLogger->logSecurityEvent(
        "Calculation error: " . $e->getMessage(),
        'error',
        ['expression' => $expression]
      );

      if ($this->enableLogging) {
        $this->calculatorLogger->logCalculation($expression, null, false, $e->getMessage(), $executionTime);
      }

      return [
        'success' => false,
        'error' => $e->getMessage(),
        'expression' => $expression,
        'execution_time' => $executionTime,
      ];
    }
  }

  /**
   * Prepare expression for evaluation
   * 
   * @param string $expression Expression to prepare
   * @return string Prepared expression
   */
  private function prepareExpression(string $expression): string
  {
    $expression = trim($expression);

    foreach ($this->constants as $name => $value) {
      $expression = preg_replace('/\b' . preg_quote($name, '/') . '\b/i', (string)$value, $expression);
    }

    foreach ($this->variables as $name => $value) {
      $expression = preg_replace('/\b' . preg_quote($name, '/') . '\b/', (string)$value, $expression);
    }

    $expression = str_replace('^', '**', $expression);

    $expression = $this->replaceFunctions($expression);

    return $expression;
  }

  /**
   * Replace mathematical functions with PHP equivalents
   * 
   * @param string $expression Expression to process
   * @return string Processed expression
   */
  private function replaceFunctions(string $expression): string
  {
    // Fonctions trigonométriques
    $replacements = [
      '/\bsin\s*\(/i' => 'sin(',
      '/\bcos\s*\(/i' => 'cos(',
      '/\btan\s*\(/i' => 'tan(',
      '/\basin\s*\(/i' => 'asin(',
      '/\bacos\s*\(/i' => 'acos(',
      '/\batan\s*\(/i' => 'atan(',

      // Racines et puissances
      '/\bsqrt\s*\(/i' => 'sqrt(',
      '/\bpow\s*\(/i' => 'pow(',

      // Logarithmes
      '/\blog\s*\(/i' => 'log(',
      '/\blog10\s*\(/i' => 'log10(',
      '/\bln\s*\(/i' => 'log(',

      // Exponentielles
      '/\bexp\s*\(/i' => 'exp(',

      // Arrondis
      '/\babs\s*\(/i' => 'abs(',
      '/\bceil\s*\(/i' => 'ceil(',
      '/\bfloor\s*\(/i' => 'floor(',
      '/\bround\s*\(/i' => 'round(',

      // Min/Max
      '/\bmin\s*\(/i' => 'min(',
      '/\bmax\s*\(/i' => 'max(',
    ];

    foreach ($replacements as $pattern => $replacement) {
      $expression = preg_replace($pattern, $replacement, $expression);
    }

    return $expression;
  }

  /**
   * Validate expression security
   * 
   * @param string $expression Expression to validate
   * @return bool Is secure
   */
  private function validateSecurity(string $expression): bool
  {
    $dangerousPatterns = [
      '/\$/', // Variables PHP
      '/\beval\b/i',
      '/\bexec\b/i',
      '/\bsystem\b/i',
      '/\bpassthru\b/i',
      '/\bshell_exec\b/i',
      '/\bfile\b/i',
      '/\bfopen\b/i',
      '/\binclude\b/i',
      '/\brequire\b/i',
      '/\bunlink\b/i',
      '/\bphpinfo\b/i',
      '/\bvar_dump\b/i',
      '/\bprint_r\b/i',
      '/\bdie\b/i',
      '/\bexit\b/i',
      '/function\s*\(/i',
      '/class\s+\w+/i',
      '/new\s+\w+/i',
      '/::/', // Appels statiques
      '/->/', // Appels de méthodes
      '/;/', // Multiple instructions
      '/`/', // Backticks
    ];

    foreach ($dangerousPatterns as $pattern) {
      if (preg_match($pattern, $expression)) {
        $this->securityLogger->logSecurityEvent(
          "Dangerous pattern detected in expression",
          'warning',
          ['pattern' => $pattern, 'expression' => $expression]
        );
        return false;
      }
    }

    if (!preg_match('/^[0-9+\-*\/%().a-z_,\s]+$/i', $expression)) {
      $this->securityLogger->logSecurityEvent(
        "Invalid characters in expression",
        'warning',
        ['expression' => $expression]
      );
      return false;
    }

    if (substr_count($expression, '(') !== substr_count($expression, ')')) {
      return false;
    }

    return true;
  }

  /**
   * Evaluate mathematical expression securely
   * Uses safe token-based parser instead of eval()
   * 
   * @param string $expression Expression to evaluate
   * @return float|int Result
   */
  private function evaluateExpression(string $expression): float|int
  {
    try {
      $result = $this->parseExpression($expression);

      if (!is_numeric($result)) {
        throw new \Exception('Result is not a number');
      }

      return $result;
    } catch (\Throwable $e) {
      throw new \Exception('Evaluation error: ' . $e->getMessage());
    }
  }

  /**
   * Safe expression parser using recursive descent
   * 
   * @param string $expr Expression to parse
   * @return float|int Parsed result
   */
  private function parseExpression(string $expr): float|int
  {
    $expr = str_replace(' ', '', $expr);
    $pos = 0;
    
    $parseNumber = function() use ($expr, &$pos) {
      $start = $pos;
      if ($pos < strlen($expr) && ($expr[$pos] === '-' || $expr[$pos] === '+')) {
        $pos++;
      }
      while ($pos < strlen($expr) && (ctype_digit($expr[$pos]) || $expr[$pos] === '.')) {
        $pos++;
      }
      if ($start === $pos) {
        throw new \Exception('Expected number at position ' . $pos);
      }
      return (float)substr($expr, $start, $pos - $start);
    };

    $parseFunction = function() use ($expr, &$pos, &$parseAddSub) {
      $funcStart = $pos;
      while ($pos < strlen($expr) && ctype_alpha($expr[$pos])) {
        $pos++;
      }
      $funcName = substr($expr, $funcStart, $pos - $funcStart);
      
      if ($pos >= strlen($expr) || $expr[$pos] !== '(') {
        throw new \Exception('Expected ( after function name');
      }
      $pos++; // skip (
      
      $args = [];
      while (true) {
        $args[] = $parseAddSub();
        if ($pos >= strlen($expr)) {
          throw new \Exception('Unexpected end of expression');
        }
        if ($expr[$pos] === ')') {
          $pos++;
          break;
        }
        if ($expr[$pos] === ',') {
          $pos++;
          continue;
        }
        throw new \Exception('Expected , or ) in function arguments');
      }
      
      return match($funcName) {
        'sin' => sin($args[0]),
        'cos' => cos($args[0]),
        'tan' => tan($args[0]),
        'asin' => asin($args[0]),
        'acos' => acos($args[0]),
        'atan' => atan($args[0]),
        'sinh' => sinh($args[0]),
        'cosh' => cosh($args[0]),
        'tanh' => tanh($args[0]),
        'sqrt' => sqrt($args[0]),
        'abs' => abs($args[0]),
        'ceil' => ceil($args[0]),
        'floor' => floor($args[0]),
        'round' => round($args[0]),
        'exp' => exp($args[0]),
        'log' => log($args[0]),
        'log10' => log10($args[0]),
        'pow' => pow($args[0], $args[1] ?? 1),
        'min' => min(...$args),
        'max' => max(...$args),
        'atan2' => atan2($args[0], $args[1] ?? 0),
        'hypot' => hypot($args[0], $args[1] ?? 0),
        default => throw new \Exception('Unknown function: ' . $funcName)
      };
    };

    $parsePrimary = function() use ($expr, &$pos, $parseNumber, $parseFunction, &$parseAddSub) {
      if ($pos >= strlen($expr)) {
        throw new \Exception('Unexpected end of expression');
      }
      
      // Check for function
      if (ctype_alpha($expr[$pos])) {
        return $parseFunction();
      }
      
      // Check for parentheses
      if ($expr[$pos] === '(') {
        $pos++;
        $result = $parseAddSub();
        if ($pos >= strlen($expr) || $expr[$pos] !== ')') {
          throw new \Exception('Expected closing parenthesis');
        }
        $pos++;
        return $result;
      }
      
      // Parse number
      return $parseNumber();
    };

    $parsePower = function() use (&$parsePrimary, $expr, &$pos) {
      $left = $parsePrimary();
      while ($pos < strlen($expr) && substr($expr, $pos, 2) === '**') {
        $pos += 2;
        $right = $parsePrimary();
        $left = pow($left, $right);
      }
      return $left;
    };

    $parseMulDiv = function() use (&$parsePower, $expr, &$pos) {
      $left = $parsePower();
      while ($pos < strlen($expr) && in_array($expr[$pos], ['*', '/', '%'], true)) {
        $op = $expr[$pos++];
        $right = $parsePower();
        $left = match($op) {
          '*' => $left * $right,
          '/' => $right != 0 ? $left / $right : throw new \Exception('Division by zero'),
          '%' => $left % $right,
        };
      }
      return $left;
    };

    $parseAddSub = function() use (&$parseMulDiv, $expr, &$pos) {
      $left = $parseMulDiv();
      while ($pos < strlen($expr) && in_array($expr[$pos], ['+', '-'], true)) {
        $op = $expr[$pos++];
        $right = $parseMulDiv();
        $left = $op === '+' ? $left + $right : $left - $right;
      }
      return $left;
    };

    $result = $parseAddSub();
    
    if ($pos < strlen($expr)) {
      throw new \Exception('Unexpected characters at position ' . $pos);
    }
    
    return $result;
  }

  /**
   * Detect result type
   * 
   * @param mixed $result Result to analyze
   * @return string Result type
   */
  private function detectResultType($result): string
  {
    if (is_int($result)) {
      return 'integer';
    }

    if (is_float($result)) {
      if (floor($result) == $result) {
        return 'integer_as_float';
      }
      return 'float';
    }

    return 'unknown';
  }

  /**
   * Add calculation to history
   * 
   * @param string $expression Expression
   * @param mixed $result Result
   * @param float $executionTime Execution time
   * @return void
   */
  private function addToHistory(string $expression, $result, float $executionTime): void
  {
    $entry = [
      'expression' => $expression,
      'result' => $result,
      'execution_time' => $executionTime,
      'timestamp' => microtime(true),
    ];

    $this->calculationHistory[] = $entry;

    if (count($this->calculationHistory) > $this->maxHistorySize) {
      array_shift($this->calculationHistory);
    }
  }

  /**
   * Get calculation history
   * 
   * @param int $limit Number of entries
   * @return array History entries
   */
  public function getHistory(int $limit = 10): array
  {
    return array_slice($this->calculationHistory, -$limit);
  }

  /**
   * Clear history
   * 
   * @return void
   */
  public function clearHistory(): void
  {
    $this->calculationHistory = [];
  }

  /**
   * Set variable
   * 
   * @param string $name Variable name
   * @param float|int $value Variable value
   * @return bool Success
   */
  public function setVariable(string $name, float|int $value): bool
  {
    if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $name)) {
      return false;
    }

    $this->variables[$name] = $value;
    return true;
  }

  /**
   * Get variable
   * 
   * @param string $name Variable name
   * @return float|int|null Variable value
   */
  public function getVariable(string $name): float|int|null
  {
    return $this->variables[$name] ?? null;
  }

  /**
   * Get all variables
   * 
   * @return array Variables
   */
  public function getVariables(): array
  {
    return $this->variables;
  }

  /**
   * Clear all variables
   * 
   * @return void
   */
  public function clearVariables(): void
  {
    $this->variables = [];
  }

  /**
   * Calculate series of values
   *
   * @param string $expression Expression with variable $x
   * @param float $start Start of interval
   * @param float $end End of interval
   * @param int $steps Number of points
   * @return array Array of [x, y]
   */
  public function calculateSeries(string $expression, float $start, float $end, int $steps = 10): array
  {
    $results = [];
    $step = ($end - $start) / ($steps - 1);

    for ($i = 0; $i < $steps; $i++) {
      $x = $start + ($i * $step);
      $this->setVariable('x', $x);

      $result = $this->calculate($expression);

      if ($result['success']) {
        $results[] = [
          'x' => $x,
          'y' => $result['result'],
        ];
      }
    }

    return $results;
  }

  /**
   * Solve simple linear equation (ax + b = 0)
   *
   * @param float $a Coefficient a
   * @param float $b Coefficient b
   * @return array Solution
   */
  public function solveLinear(float $a, float $b): array
  {
    if ($a == 0) {
      return [
        'success' => false,
        'error' => 'Coefficient a cannot be zero',
      ];
    }

    $x = -$b / $a;

    return [
      'success' => true,
      'solution' => $x,
      'equation' => "{$a}x + {$b} = 0",
    ];
  }

  /**
   * Solve quadratic equation (ax² + bx + c = 0)
   *
   * @param float $a Coefficient a
   * @param float $b Coefficient b
   * @param float $c Coefficient c
   * @return array Solutions
   */
  public function solveQuadratic(float $a, float $b, float $c): array
  {
    if ($a == 0) {
      return $this->solveLinear($b, $c);
    }

    $discriminant = ($b * $b) - (4 * $a * $c);

    if ($discriminant < 0) {
      return [
        'success' => true,
        'solutions' => 'complex',
        'equation' => "{$a}x² + {$b}x + {$c} = 0",
        'discriminant' => $discriminant,
      ];
    }

    $sqrtDiscriminant = sqrt($discriminant);

    $x1 = (-$b + $sqrtDiscriminant) / (2 * $a);
    $x2 = (-$b - $sqrtDiscriminant) / (2 * $a);

    return [
      'success' => true,
      'solutions' => [$x1, $x2],
      'equation' => "{$a}x² + {$b}x + {$c} = 0",
      'discriminant' => $discriminant,
    ];
  }

  /**
   * Calculate statistic on value set
   *
   * @param array $values Values
   * @param string $operation Statistic type (sum, avg, min, max, stddev)
   * @return array Result
   */
  public function calculateStatistic(array $values, string $operation = 'avg'): array
  {
    if (empty($values)) {
      return [
        'success' => false,
        'error' => 'Empty values array',
      ];
    }

    $result = match ($operation) {
      'sum' => array_sum($values),
      'avg' => array_sum($values) / count($values),
      'min' => min($values),
      'max' => max($values),
      'stddev' => $this->standardDeviation($values),
      'variance' => $this->variance($values),
      'median' => $this->median($values),
      default => throw new \Exception("Unknown operation: {$operation}"),
    };

    return [
      'success' => true,
      'result' => $result,
      'operation' => $operation,
      'count' => count($values),
    ];
  }

  /**
   * Calculate standard deviation
   * 
   * @param array $values Values
   * @return float Standard deviation
   */
  private function standardDeviation(array $values): float
  {
    return sqrt($this->variance($values));
  }

  /**
   * Calculate variance
   * 
   * @param array $values Values
   * @return float Variance
   */
  private function variance(array $values): float
  {
    $mean = array_sum($values) / count($values);
    $squaredDiffs = array_map(fn($x) => pow($x - $mean, 2), $values);
    return array_sum($squaredDiffs) / count($values);
  }

  /**
   * Calculate median
   * 
   * @param array $values Values
   * @return float Median
   */
  private function median(array $values): float
  {
    sort($values);
    $count = count($values);
    $middle = floor($count / 2);

    if ($count % 2 == 0) {
      return ($values[$middle - 1] + $values[$middle]) / 2;
    }

    return $values[$middle];
  }

  /**
   * Format number for display
   *
   * @param float|int $number Number to format
   * @param int $decimals Number of decimals
   * @return string Formatted number
   */
  public function formatNumber($number, int $decimals = 2): string
  {
    return number_format($number, $decimals, '.', ',');
  }

  /**
   * Get usage statistics
   * 
   * @return array Statistics
   */
  public function getStats(): array
  {
    return [
      'total_calculations' => count($this->calculationHistory),
      'variables_defined' => count($this->variables),
      'available_functions' => count($this->allowedFunctions),
      'available_constants' => count($this->constants),
    ];
  }

  /**
   * Get help on available functions
   * 
   * @return array Help information
   */
  public function getHelp(): array
  {
    return [
      'basic_operations' => [
        '+' => 'Addition',
        '-' => 'Subtraction',
        '*' => 'Multiplication',
        '/' => 'Division',
        '%' => 'Modulo',
        '**' => 'Power (or ^)',
      ],
      'functions' => [
        'sin(x)' => 'Sine',
        'cos(x)' => 'Cosine',
        'tan(x)' => 'Tangent',
        'sqrt(x)' => 'Square root',
        'abs(x)' => 'Absolute value',
        'log(x)' => 'Natural logarithm',
        'log10(x)' => 'Base-10 logarithm',
        'exp(x)' => 'Exponential',
        'pow(x, y)' => 'x to the power of y',
        'min(x, y, ...)' => 'Minimum value',
        'max(x, y, ...)' => 'Maximum value',
        'round(x)' => 'Round to nearest integer',
        'ceil(x)' => 'Round up',
        'floor(x)' => 'Round down',
      ],
      'constants' => [
        'pi' => 'π ≈ 3.14159',
        'e' => 'e ≈ 2.71828',
        'phi' => 'φ (golden ratio) ≈ 1.61803',
      ],
      'examples' => [
        '2 + 2' => '4',
        'sqrt(16)' => '4',
        'sin(pi/2)' => '1',
        'pow(2, 3)' => '8',
        '5 * (3 + 2)' => '25',
      ],
    ];
  }

  /**
   * Execute calculation in agent context
   * Interface for agent system execution plan
   *
   * @param array $context Context provided by PlanExecutor
   * @return array Result formatted for plan
   */
  public function executeInAgentContext(array $context): array
  {
    try {
      $expression = $context['expression'] ?? '';
      $variables = $context['variables'] ?? [];
      $operation = $context['operation'] ?? 'calculate';

      if (isset($context['dependency_results'])) {
        $variables = array_merge(
          $variables,
          $this->extractVariablesFromDependencies($context['dependency_results'])
        );
      }

      $result = match ($operation) {
        'calculate' => $this->calculate($expression, $variables),
        'statistic' => $this->calculateStatistic(
          $context['values'] ?? [],
          $context['stat_type'] ?? 'avg'
        ),
        'solve_linear' => $this->solveLinear(
          $context['a'] ?? 0,
          $context['b'] ?? 0
        ),
        'solve_quadratic' => $this->solveQuadratic(
          $context['a'] ?? 0,
          $context['b'] ?? 0,
          $context['c'] ?? 0
        ),
        'series' => $this->calculateSeries(
          $expression,
          $context['start'] ?? 0,
          $context['end'] ?? 10,
          $context['steps'] ?? 10
        ),
        default => throw new \Exception("Unknown operation: {$operation}"),
      };

      return [
        'type' => 'calculation_result',
        'success' => $result['success'] ?? false,
        'result' => $result['result'] ?? $result,
        'operation' => $operation,
        'expression' => $expression,
        'variables_used' => array_keys($variables),
        'metadata' => [
          'execution_time' => $result['execution_time'] ?? 0,
          'result_type' => $result['type'] ?? 'unknown',
        ],
      ];
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Agent context execution error: " . $e->getMessage(),
        'error',
        ['context' => $context]
      );

      return [
        'type' => 'calculation_error',
        'success' => false,
        'error' => $e->getMessage(),
        'operation' => $context['operation'] ?? 'unknown',
      ];
    }
  }

  /**
   * Extract variables from dependency results
   * 
   * @param array $dependencyResults Dependency results
   * @return array Extracted variables
   */
  private function extractVariablesFromDependencies(array $dependencyResults): array
  {
    $variables = [];

    foreach ($dependencyResults as $depId => $depResult) {
      if (isset($depResult['result']) && is_numeric($depResult['result'])) {
        $variables[$depId] = $depResult['result'];
      }

      if (isset($depResult['type']) && $depResult['type'] === 'calculation_result') {
        if (is_numeric($depResult['result'])) {
          $variables[$depId . '_result'] = $depResult['result'];
        }
      }

      if (isset($depResult['type']) && $depResult['type'] === 'aggregated_result') {
        foreach ($depResult['results'] as $aggResult) {
          foreach ($aggResult as $key => $value) {
            if (is_numeric($value)) {
              $safeKey = preg_replace('/[^a-z0-9_]/i', '_', $key);
              $variables[$depId . '_' . $safeKey] = $value;
            }
          }
        }
      }

      if (is_array($depResult)) {
        $extracted = $this->extractNumericValues($depResult, $depId);
        $variables = array_merge($variables, $extracted);
      }
    }

    return $variables;
  }

  /**
   * Extract numeric values from array recursively
   * 
   * @param array $data Data to extract from
   * @param string $prefix Key prefix
   * @param int $depth Recursion depth
   * @return array Extracted values
   */
  private function extractNumericValues(array $data, string $prefix = '', int $depth = 0): array
  {
    $values = [];

    if ($depth > 3) {
      return $values;
    }

    foreach ($data as $key => $value) {
      if (is_numeric($value)) {
        $safeKey = preg_replace('/[^a-z0-9_]/i', '_', $key);
        $fullKey = $prefix ? "{$prefix}_{$safeKey}" : $safeKey;
        $values[$fullKey] = $value;
      } elseif (is_array($value)) {
        $subPrefix = $prefix ? "{$prefix}_{$key}" : $key;
        $subValues = $this->extractNumericValues($value, $subPrefix, $depth + 1);
        $values = array_merge($values, $subValues);
      }
    }

    return $values;
  }

  /**
   * Remove expired cache entries. Delegates to CalculatorCache.
   */
  public function cleanCache(): int
  {
    return $this->calculatorCache->cleanCache();
  }

  /**
   * Clear all cached calculation results. Delegates to CalculatorCache.
   */
  public function clearCache(): bool
  {
    return $this->calculatorCache->clearCache();
  }

  /**
   * Cache statistics. Delegates to CalculatorCache.
   */
  public function getCacheStats(): array
  {
    return $this->calculatorCache->getCacheStats();
  }

  /**
   * Recent calculation log entries. Delegates to CalculatorLogger.
   */
  public function getLogs(int $limit = 100, array $filters = []): array
  {
    return $this->calculatorLogger->getLogs($limit, $filters);
  }

  /**
   * Calculation log statistics. Delegates to CalculatorLogger.
   */
  public function getLogStats(array $filters = []): array
  {
    return $this->calculatorLogger->getLogStats($filters);
  }

  /**
   * Prune log entries older than N days. Delegates to CalculatorLogger.
   */
  public function cleanLogs(int $daysToKeep = 30): int
  {
    return $this->calculatorLogger->cleanLogs($daysToKeep);
  }
}