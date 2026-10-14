<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

/**
 * ObjectiveExecutorConfig Class
 *
 * Dedicated, constant-based dormancy flag for the §Z Z3 objective-execution loop.
 * Independent of the global DB-backed ActorCriticConfig singleton. Dark-launch: the
 * flag defaults OFF, so ObjectiveExecutor::execute() is a structural no-op until the
 * gate is flipped (post-2B). Admin tunability can be added later by defining the
 * constant + a Params file, with no change to this class.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-06-23
 */
class ObjectiveExecutorConfig
{
    /** @var string Global framework constant string checked for administrative toggle status. */
    private const CONST_OBJECTIVE_EXECUTOR_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_OBJECTIVE_EXECUTOR_STATUS';

    /** @var array{objective_executor_status: bool} Internal fallback array for the configuration context. */
    private const DEFAULTS = [
        'objective_executor_status' => false,
    ];

    /** @var array<string, bool>|null Cached runtime key-value map loaded from system constants. */
    private static ?array $config = null;

    /** @var bool Internal debug state tracking whether system logs should be written to file. */
    private static bool $debug = false;

    /**
     * Whether the objective-execution loop is engaged. Dark-launch default: false.
     * * @return bool True if active, false otherwise.
     */
    public static function isEnabled(): bool
    {
        self::initialize();
        return self::$config['objective_executor_status'] ?? false;
    }

    /**
     * Initializes the static properties and parses environmental variables.
     * * @return void
     */
    private static function initialize(): void
    {
        if (self::$config !== null) {
            return;
        }

        self::$debug = \defined('CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER')
            && CLICSHOPPING_APP_CHATGPT_RA_DEBUG_RAG_MANAGER === 'True';

        self::$config = self::loadConfigFromConstants();

        if (self::$debug) {
            error_log('ObjectiveExecutorConfig: Initialized with objective_executor_status='
                . (self::$config['objective_executor_status'] ? 'true' : 'false'));
        }
    }

    /**
     * Extracts and maps configuration flags by evaluating globally defined system constants.
     * * @return array<string, bool> Parsed target execution states map.
     */
    private static function loadConfigFromConstants(): array
    {
        $config = self::DEFAULTS;

        if (\defined(self::CONST_OBJECTIVE_EXECUTOR_STATUS)) {
            $config['objective_executor_status'] = self::parseBool(constant(self::CONST_OBJECTIVE_EXECUTOR_STATUS));
        }

        return $config;
    }

    /**
     * Normalizes incoming variable structures into standard boolean values.
     * * @param mixed $value Raw configuration input target data.
     * @return bool Normalized true/false outcome.
     */
    private static function parseBool($value): bool
    {
        if (\is_bool($value)) {
            return $value;
        }

        if (\is_numeric($value)) {
            return (int)$value === 1;
        }

        $normalized = \strtolower(\trim((string)$value));
        return \in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Reset cached config (test hook).
     * * @return void
     */
    public static function reload(): void
    {
        self::$config = null;
        self::initialize();
    }
}
