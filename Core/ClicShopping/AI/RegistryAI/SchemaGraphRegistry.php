<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\RegistryAI;

use ClicShopping\AI\Config\SchemaGraphRegistryConfig;
use ClicShopping\AI\InterfacesAI\SchemaGraphProviderInterface;

/**
 * SchemaGraphRegistry
 *
 * Domain-agnostic, process-scoped registry of SCHEMA GRAPHS and the traversal
 * engine over them. Nodes are entities, edges carry the join key and cardinality.
 *
 * This is a KNOWLEDGE layer, never a DECISION layer: {@see projectWindow()} takes
 * the entities a plan already chose (metric grains + dimensions) and returns the
 * sub-graph that connects their tables — tables, keys, cardinalities. It routes
 * nothing, it selects no metric, it emits no verdict. Its output feeds the prompt
 * context so the aggregation grain becomes structural instead of a prompt sentence.
 *
 * Core holds no built-in graph: entities are domain knowledge by definition. Each
 * domain App opts in by shipping
 * `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/SchemaGraph/Registration/SchemaGraphRegistration.php`
 * exposing `public static register(SchemaGraphRegistry $r): void`.
 *
 * @package ClicShopping\AI\RegistryAI
 */
class SchemaGraphRegistry
{
  private static ?self $instance = null;

  /** @var array<int, SchemaGraphProviderInterface> */
  private array $providers = [];

  private bool $domainsBootstrapped = false;

  /** @var array<string, string>|null Merged node id => unprefixed table, lazily built */
  private ?array $nodes = null;

  /** @var array<int, array{from: string, to: string, from_col: string, to_col: string, cardinality: string}>|null */
  private ?array $edges = null;

  /**
   * Shared instance — registration happens once per process.
   *
   * @return self The process-wide registry
   */
  public static function getInstance(): self
  {
    if (self::$instance === null) {
      self::$instance = new self();
    }

    return self::$instance;
  }

  /**
   * Drop the shared instance. For tests only.
   *
   * @return void
   */
  public static function reset(): void
  {
    self::$instance = null;
  }

  /**
   * Register a domain's schema graph. The merged graph is invalidated so the next
   * traversal rebuilds it.
   *
   * @param SchemaGraphProviderInterface $provider Provider to register
   * @return void
   */
  public function register(SchemaGraphProviderInterface $provider): void
  {
    $this->providers[] = $provider;
    $this->nodes = null;
    $this->edges = null;
  }

  /**
   * Project the sub-graph that connects the entities a plan named.
   *
   * The start tokens are the plan's metric grains and dimensions. Each is resolved
   * to a node (by node id, or by the node's table name); unknown tokens are ignored.
   * The result is the union of the shortest join paths between the resolved nodes,
   * which pulls in the bridge tables a JOIN needs (an order-to-product query pulls
   * in the order-line node between them). The output carries tables and, per edge,
   * the join columns and cardinality — never a decision.
   *
   * @param array<int, string> $startTokens Metric grains and dimensions from the plan
   * @return array{tables: array<int, string>, edges: array<int, array{from_table: string, to_table: string, from_col: string, to_col: string, cardinality: string}>}
   */
  public function projectWindow(array $startTokens): array
  {
    $this->bootstrapDomains();
    $this->buildMergedGraph();

    $startNodes = [];

    foreach ($startTokens as $token) {
      $node = $this->resolveToken((string)$token);

      if ($node !== null && !\in_array($node, $startNodes, true)) {
        $startNodes[] = $node;
      }
    }

    if ($startNodes === []) {
      return ['tables' => [], 'edges' => []];
    }

    $keptNodes = $this->connectingNodes($startNodes);
    $keptEdges = [];
    $tables = [];

    foreach ($keptNodes as $node) {
      $tables[] = $this->nodes[$node];
    }

    foreach ($this->edges as $edge) {
      if (\in_array($edge['from'], $keptNodes, true) && \in_array($edge['to'], $keptNodes, true)) {
        $keptEdges[] = [
          'from_table' => $this->nodes[$edge['from']],
          'to_table' => $this->nodes[$edge['to']],
          'from_col' => $edge['from_col'],
          'to_col' => $edge['to_col'],
          'cardinality' => $edge['cardinality'],
        ];
      }
    }

    return ['tables' => \array_values(\array_unique($tables)), 'edges' => $keptEdges];
  }

  /**
   * Resolve a plan token (a grain or a dimension) to a node id, or null.
   * Matches the node id first, then the node's table name.
   *
   * @param string $token Grain or dimension label
   * @return string|null Node id, or null when the graph does not know it
   */
  private function resolveToken(string $token): ?string
  {
    if (isset($this->nodes[$token])) {
      return $token;
    }

    $node = \array_search($token, $this->nodes, true);

    return $node === false ? null : $node;
  }

  /**
   * Nodes on the join paths between the start nodes: the union of the shortest path
   * from the first start node to each other one. Isolated start nodes are kept as-is.
   *
   * ponytail: union-of-shortest-paths, not a minimal Steiner tree — exact enough on
   * a graph of a few dozen entities; revisit only if a domain graph grows large.
   *
   * @param array<int, string> $startNodes Resolved start node ids
   * @return array<int, string> Node ids to keep, deduplicated
   */
  private function connectingNodes(array $startNodes): array
  {
    $kept = [$startNodes[0]];
    $anchor = $startNodes[0];

    for ($i = 1, $n = \count($startNodes); $i < $n; $i++) {
      $path = $this->shortestPath($anchor, $startNodes[$i]);

      // No path: keep the target on its own rather than drop the entity the plan named.
      $kept = \array_merge($kept, $path === [] ? [$startNodes[$i]] : $path);
    }

    return \array_values(\array_unique($kept));
  }

  /**
   * Breadth-first shortest path between two nodes over the undirected edge set.
   *
   * @param string $from Start node id
   * @param string $to Target node id
   * @return array<int, string> Nodes from `from` to `to` inclusive, or [] if unreachable
   */
  private function shortestPath(string $from, string $to): array
  {
    if ($from === $to) {
      return [$from];
    }

    $queue = [[$from]];
    $seen = [$from => true];

    while ($queue !== []) {
      $path = \array_shift($queue);
      $last = \end($path);

      foreach ($this->neighbours($last) as $next) {
        if (isset($seen[$next])) {
          continue;
        }

        $seen[$next] = true;
        $extended = \array_merge($path, [$next]);

        if ($next === $to) {
          return $extended;
        }

        $queue[] = $extended;
      }
    }

    return [];
  }

  /**
   * Neighbour node ids of a node over the undirected edge set.
   *
   * @param string $node Node id
   * @return array<int, string> Adjacent node ids
   */
  private function neighbours(string $node): array
  {
    $out = [];

    foreach ($this->edges as $edge) {
      if ($edge['from'] === $node) {
        $out[] = $edge['to'];
      } elseif ($edge['to'] === $node) {
        $out[] = $edge['from'];
      }
    }

    return $out;
  }

  /**
   * Merge every registered domain's nodes and edges once. A provider that throws is
   * skipped — a broken domain must not disable the graph for the others.
   *
   * @return void
   */
  private function buildMergedGraph(): void
  {
    if ($this->nodes !== null && $this->edges !== null) {
      return;
    }

    $this->nodes = [];
    $this->edges = [];

    foreach ($this->providers as $provider) {
      try {
        foreach ($provider->getNodes() as $id => $table) {
          $this->nodes[$id] = $table;
        }

        foreach ($provider->getEdges() as $edge) {
          $this->edges[] = $edge;
        }
      } catch (\Throwable $e) {
        error_log('[SchemaGraphRegistry] provider failed: ' . $e->getMessage());
      }
    }
  }

  /**
   * Scan `Apps/AI/*` and invoke each domain's registration class, at most once.
   * A missing or malformed registration is skipped so a broken domain never
   * prevents a projection.
   *
   * @return void
   */
  private function bootstrapDomains(): void
  {
    if ($this->domainsBootstrapped || !SchemaGraphRegistryConfig::isAutoScanEnabled()) {
      return;
    }

    $this->domainsBootstrapped = true;

    if (!\defined('CLICSHOPPING_BASE_DIR')) {
      return;
    }

    $basePath = \CLICSHOPPING_BASE_DIR . SchemaGraphRegistryConfig::getDomainBasePath();

    if (!\is_dir($basePath)) {
      return;
    }

    $entries = \scandir($basePath);

    if ($entries === false) {
      return;
    }

    foreach ($entries as $entry) {
      if ($entry === '.' || $entry === '..' || !\is_dir($basePath . $entry)) {
        continue;
      }

      $file = $basePath . $entry . '/' . SchemaGraphRegistryConfig::REGISTRATION_CLASS_RELATIVE_PATH;

      if (!\file_exists($file)) {
        continue;
      }

      $fqcn = SchemaGraphRegistryConfig::getRegistrationClassFqcn($entry);

      if (!\class_exists($fqcn) || !\method_exists($fqcn, 'register')) {
        error_log('[SchemaGraphRegistry] registration class missing for domain ' . $entry . ': ' . $fqcn);
        continue;
      }

      try {
        $fqcn::register($this);
      } catch (\Throwable $e) {
        error_log('[SchemaGraphRegistry] registration failed for domain ' . $entry . ': ' . $e->getMessage());
      }
    }
  }
}
