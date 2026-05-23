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
  private mixed $db;
  private bool $enableCache = false;
  private bool $enableLogging = false;
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
        $cachedResult = $this->getCachedResult($expression, $this->variables);
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
        $this->cacheResult($expression, $this->variables, $response);
      }

      if ($this->enableLogging) {
        $this->logCalculation($expression, $result, true, null, $response['execution_time']);
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
        $this->logCalculation($expression, null, false, $e->getMessage(), $executionTime);
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
   * Generate cache hash
   * 
   * @param string $expression Expression
   * @param array $variables Variables
   * @return string Hash
   */
  private function generateCacheHash(string $expression, array $variables): string
  {
    $data = $expression . json_encode($variables, JSON_UNESCAPED_UNICODE);
    return hash('sha256', $data);
  }

  /**
   * Get cached result
   * 
   * @param string $expression Expression
   * @param array $variables Variables
   * @return array|null Cached result or null
   */
  private function getCachedResult(string $expression, array $variables): ?array
  {
    if (!$this->db) {
      return null;
    }

    try {
      $hash = $this->generateCacheHash($expression, $variables);

      $sql = "SELECT * FROM :table_rag_calculator_cache 
              WHERE expression_hash = :hash 
              AND created_at > DATE_SUB(NOW(), INTERVAL :ttl SECOND)
              LIMIT 1";

      $stmt = $this->db->prepare($sql);
      $stmt->execute([
        'hash' => $hash,
        'ttl' => $this->cacheTTL
      ]);

      $row = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($row) {
        $updateSql = "UPDATE :table_rag_calculator_cache 
                      SET last_accessed = NOW(), 
                          access_count = access_count + 1 
                      WHERE cache_id = :id";
        $updateStmt = $this->db->prepare($updateSql);
        $updateStmt->execute(['id' => $row['cache_id']]);

        return [
          'success' => true,
          'result' => (float)$row['result'],
          'expression' => $expression,
          'prepared_expression' => $expression,
          'execution_time' => (float)$row['execution_time'],
          'type' => $row['result_type'],
          'from_cache' => true,
          'cache_age' => time() - strtotime($row['created_at']),
          'access_count' => (int)$row['access_count'] + 1,
        ];
      }

      return null;
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Cache retrieval error: " . $e->getMessage(),
          'error'
        );
      }
      return null;
    }
  }

  /**
   * Cache result
   * 
   * @param string $expression Expression
   * @param array $variables Variables
   * @param array $result Result
   * @return bool Success
   */
  private function cacheResult(string $expression, array $variables, array $result): bool
  {
    if (!$this->db) {
      return false;
    }

    try {
      $hash = $this->generateCacheHash($expression, $variables);

      $sql = "INSERT INTO :table_rag_calculator_cache 
              (expression, expression_hash, result, result_type, variables, 
               execution_time, created_at, last_accessed, access_count) 
              VALUES 
              (:expression, :hash, :result, :type, :variables, 
               :exec_time, NOW(), NOW(), 0)
              ON DUPLICATE KEY UPDATE 
                result = VALUES(result),
                result_type = VALUES(result_type),
                execution_time = VALUES(execution_time),
                last_accessed = NOW(),
                access_count = access_count + 1";

      $stmt = $this->db->prepare($sql);

      return $stmt->execute([
        'expression' => substr($expression, 0, 500),
        'hash' => $hash,
        'result' => $result['result'],
        'type' => $result['type'],
        'variables' => json_encode($variables, JSON_UNESCAPED_UNICODE),
        'exec_time' => $result['execution_time'],
      ]);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Cache storage error: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }

  /**
   * Clean expired cache
   * 
   * @return int Number of deleted entries
   */
  public function cleanCache(): int
  {
    if (!$this->db) {
      return 0;
    }

    try {
      $sql = "DELETE FROM :table_rag_calculator_cache 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL :ttl SECOND)";

      $stmt = $this->db->prepare($sql);
      $stmt->execute(['ttl' => $this->cacheTTL]);

      $deleted = $stmt->rowCount();

      if ($this->debug && $deleted > 0) {
        $this->securityLogger->logSecurityEvent(
          "Cleaned {$deleted} expired cache entries",
          'info'
        );
      }

      return $deleted;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Cache cleaning error: " . $e->getMessage(),
        'error'
      );
      return 0;
    }
  }

  /**
   * Clear all cache
   * 
   * @return bool Success
   */
  public function clearCache(): bool
  {
    if (!$this->db) {
      return false;
    }

    try {
      $this->db->exec("TRUNCATE TABLE calculator_cache");

      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Calculator cache cleared",
          'info'
        );
      }

      return true;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Cache clearing error: " . $e->getMessage(),
        'error'
      );
      return false;
    }
  }

  /**
   * Get cache statistics
   * 
   * @return array Cache statistics
   */
  public function getCacheStats(): array
  {
    if (!$this->db) {
      return ['enabled' => false];
    }

    try {
      $stats = [];

      $stmt = $this->db->query("SELECT COUNT(*) as total FROM :table_rag_calculator_cache");
      $stats['total_entries'] = (int)$stmt->fetchColumn();

      $sql = "SELECT COUNT(*) as valid FROM :table_rag_calculator_cache 
              WHERE created_at > DATE_SUB(NOW(), INTERVAL :ttl SECOND)";
      $stmt = $this->db->prepare($sql);
      $stmt->execute(['ttl' => $this->cacheTTL]);
      $stats['valid_entries'] = (int)$stmt->fetchColumn();

      $stmt = $this->db->query("SELECT SUM(access_count) as total FROM :table_rag_calculator_cache");
      $stats['total_accesses'] = (int)$stmt->fetchColumn();

      $stmt = $this->db->query(
        "SELECT expression, 
                access_count 
         FROM :table_rag_calculator_cache 
         ORDER BY access_count DESC 
         LIMIT 1"
      );
      $popular = $stmt->fetch(\PDO::FETCH_ASSOC);
      $stats['most_popular'] = $popular ? [
        'expression' => $popular['expression'],
        'accesses' => (int)$popular['access_count']
      ] : null;

      if ($stats['total_accesses'] > 0) {
        $stats['hit_rate'] = round(
          ($stats['total_accesses'] / ($stats['total_entries'] + $stats['total_accesses'])) * 100,
          2
        );
      } else {
        $stats['hit_rate'] = 0;
      }

      $stats['enabled'] = true;
      $stats['ttl'] = $this->cacheTTL;

      return $stats;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Cache stats error: " . $e->getMessage(),
        'error'
      );
      return ['enabled' => true, 'error' => $e->getMessage()];
    }
  }

  /**
   * Log calculation
   * 
   * @param string $expression Expression
   * @param float|null $result Result
   * @param bool $success Success status
   * @param string|null $errorMessage Error message
   * @param float $executionTime Execution time
   * @param string|null $stepId Step ID
   * @param string|null $planId Plan ID
   * @param array|null $metadata Metadata
   * @return bool Success
   */
  private function logCalculation(
    string $expression,
    ?float $result,
    bool $success,
    ?string $errorMessage,
    float $executionTime,
    ?string $stepId = null,
    ?string $planId = null,
    ?array $metadata = null
  ): bool {
    if (!$this->db || !$this->enableLogging) {
      return false;
    }

    try {
      $sql = "INSERT INTO :table_rag_calculator_logs 
              (user_id, expression, result, success, error_message, 
               execution_time, step_id, plan_id, metadata, created_at) 
              VALUES 
              (:user_id, :expression, :result, :success, :error, 
               :exec_time, :step_id, :plan_id, :metadata, NOW())";

      $stmt = $this->db->prepare($sql);

      return $stmt->execute([
        'user_id' => $this->getUserId(),
        'expression' => $expression,
        'result' => $result,
        'success' => $success ? 1 : 0,
        'error' => $errorMessage,
        'exec_time' => $executionTime,
        'step_id' => $stepId,
        'plan_id' => $planId,
        'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null,
      ]);
    } catch (\Exception $e) {
      if ($this->debug) {
        $this->securityLogger->logSecurityEvent(
          "Logging error: " . $e->getMessage(),
          'error'
        );
      }
      return false;
    }
  }

  /**
   * Get calculation logs
   * 
   * @param int $limit Number of entries
   * @param array $filters Filters
   * @return array Log entries
   */
  public function getLogs(int $limit = 100, array $filters = []): array
  {
    if (!$this->db) {
      return [];
    }

    try {
      $where = [];
      $params = [];

      if (isset($filters['success'])) {
        $where[] = "success = :success";
        $params['success'] = $filters['success'] ? 1 : 0;
      }

      if (isset($filters['user_id'])) {
        $where[] = "user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
      }

      if (isset($filters['plan_id'])) {
        $where[] = "plan_id = :plan_id";
        $params['plan_id'] = $filters['plan_id'];
      }

      if (isset($filters['from_date'])) {
        $where[] = "created_at >= :from_date";
        $params['from_date'] = $filters['from_date'];
      }

      $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

      $sql = "SELECT * FROM :table_rag_calculator_logs 
              {$whereClause}
              ORDER BY created_at DESC 
              LIMIT :limit";

      $stmt = $this->db->prepare($sql);

      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->bindValue('limit', $limit, \PDO::PARAM_INT);

      $stmt->execute();

      return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Log retrieval error: " . $e->getMessage(),
        'error'
      );
      return [];
    }
  }

  /**
   * Get log statistics
   * 
   * @param array $filters Filters
   * @return array Log statistics
   */
  public function getLogStats(array $filters = []): array
  {
    if (!$this->db) {
      return ['enabled' => false];
    }

    try {
      $where = [];
      $params = [];

      if (isset($filters['user_id'])) {
        $where[] = "user_id = :user_id";
        $params['user_id'] = $filters['user_id'];
      }

      if (isset($filters['from_date'])) {
        $where[] = "created_at >= :from_date";
        $params['from_date'] = $filters['from_date'];
      }

      $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

      $sql = "SELECT 
                COUNT(*) as total_calculations,
                SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful,
                SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed,
                AVG(execution_time) as avg_execution_time,
                MIN(execution_time) as min_execution_time,
                MAX(execution_time) as max_execution_time
              FROM calculator_logs
              {$whereClause}";

      $stmt = $this->db->prepare($sql);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

      if ($stats['total_calculations'] > 0) {
        $stats['success_rate'] = round(
          ($stats['successful'] / $stats['total_calculations']) * 100,
          2
        );
      } else {
        $stats['success_rate'] = 0;
      }

      $stats['enabled'] = true;

      return $stats;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Log stats error: " . $e->getMessage(),
        'error'
      );
      return ['enabled' => true, 'error' => $e->getMessage()];
    }
  }

  /**
   * Clean old logs
   * 
   * @param int $daysToKeep Days to keep
   * @return int Number of deleted entries
   */
  public function cleanLogs(int $daysToKeep = 30): int
  {
    if (!$this->db) {
      return 0;
    }

    try {
      $sql = "DELETE FROM :table_rag_calculator_logs 
              WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

      $stmt = $this->db->prepare($sql);
      $stmt->execute(['days' => $daysToKeep]);

      $deleted = $stmt->rowCount();

      if ($this->debug && $deleted > 0) {
        $this->securityLogger->logSecurityEvent(
          "Cleaned {$deleted} old log entries",
          'info'
        );
      }

      return $deleted;
    } catch (\Exception $e) {
      $this->securityLogger->logSecurityEvent(
        "Log cleaning error: " . $e->getMessage(),
        'error'
      );
      return 0;
    }
  }

  /**
   * Get current user ID
   * 
   * @return string User ID
   */
  private function getUserId(): string
  {
    AdministratorAdmin::getUserAdminId();

/*
    // Try to get user ID from session
    if (isset($_SESSION['customer_id'])) {
      return (string)$_SESSION['customer_id'];
    }

    if (Registry::exists('Customer')) {
      $customer = Registry::get('Customer');
      if (method_exists($customer, 'getId')) {
        return (string)$customer->getId();
      }
    }
*/
    return 'system';
  }
}