<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

declare(strict_types=1);

namespace ClicShopping\AI\InterfacesAI;

/**
 * SchemaGraphProviderInterface
 *
 * Contract for a domain's SCHEMA GRAPH: the entities of one vertical as nodes and
 * the way they join as edges (key + cardinality). Core holds the traversal engine
 * ({@see \ClicShopping\AI\RegistryAI\SchemaGraphRegistry}); it never knows what an
 * entity means. A domain opts in via
 * `Apps/AI/{Domain}/Classes/ClicShoppingAdmin/SchemaGraph/Registration/SchemaGraphRegistration.php`,
 * exactly as WebSearch engines and dynamic placeholders do.
 *
 * Node ids are the domain's own vocabulary and SHOULD match the grain values its
 * metric catalogue declares (e.g. `order`, `order_line`, `product`): a plan names a
 * metric's grain, and the graph resolves that grain to a table and its join keys —
 * which is what makes the aggregation grain structural rather than a prompt sentence.
 *
 * @package ClicShopping\AI\InterfacesAI
 */
interface SchemaGraphProviderInterface
{
  /**
   * Nodes of this domain: node id => unprefixed table name.
   *
   * The prefix is one install's setting; the caller applies it.
   *
   * @return array<string, string> Node id => unprefixed table name
   */
  public function getNodes(): array;

  /**
   * Edges of this domain, each a join between two nodes with its cardinality.
   *
   * `from`/`to` are node ids declared by {@see getNodes()}. `from_col`/`to_col` are
   * the join columns. `cardinality` reads in the from→to direction (`1-N`, `N-1`,
   * `N-N` — an N-N link is modelled as two 1-N edges through a bridge node).
   *
   * @return array<int, array{from: string, to: string, from_col: string, to_col: string, cardinality: string}>
   */
  public function getEdges(): array;
}
