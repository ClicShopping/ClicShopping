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
 * BatchableWebSearchInterface
 *
 * OPTIONAL capability on top of {@see WebSearchInterface}: an engine that can declare its
 * network calls up front lets {@see \ClicShopping\AI\DomainsAI\WebSearch\Executor\WebSearchExecutor}
 * issue every engine's calls in ONE round, so a hybrid query costs the slowest engine instead
 * of the sum of them.
 *
 * It is opt-in on purpose. An engine — and therefore a whole new domain (HR, Finance,
 * Trading, ...) — that implements nothing here still runs, sequentially: declaring a
 * WebSearch engine must never cost two extra methods nobody asked for.
 *
 * Contract: what travels between the two methods is the CALLER'S KEY, never a URL. The
 * engine context (target site, location params, per-site patterns) stays in PHP.
 *
 * @package ClicShopping\AI\InterfacesAI
 * @since 2026-09-21
 */
interface BatchableWebSearchInterface
{
    /**
     * Declare the searches this engine would run for that query.
     *
     * An engine may declare several (one per target site, per keyword, ...). Returning an
     * empty array means "not this time" — the executor falls back to {@see WebSearchInterface::search()}.
     *
     * Each entry is a request array ready for {@see \ClicShopping\OM\HTTP::getParallelResponses()},
     * built by the engine's own client — which is where the per-URL outbound policy check happens,
     * before the lot is constituted.
     *
     * @param string $query Search query
     * @param array $options Engine options as prepared by the executor
     * @return array<string,array{url:string,method:string,timeout:int,header:array}> Requests keyed by the engine
     */
    public function prepareBatchRequests(string $query, array $options = []): array;

    /**
     * Build this engine's usual result structure from the responses of its own declared searches.
     *
     * @param array<string,string|false> $responses Raw body per key, false for the ones that failed
     * @param string $query Search query the batch was declared for
     * @param array $options Same options that were passed to prepareBatchRequests()
     * @return array Same structure {@see WebSearchInterface::search()} returns
     */
    public function buildResultFromBatch(array $responses, string $query, array $options = []): array;
}
