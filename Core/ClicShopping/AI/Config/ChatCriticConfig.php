<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\AI\Config;

/**
 * ChatCriticConfig Class
 *
 * Dedicated, constant-based configuration for the chat critic seam (§Z Z1).
 * Independent of the global DB-backed ActorCriticConfig singleton: the chat seam
 * must NOT inherit SEO's actor_critic / quality_gate_regeneration flags.
 *
 * Dark-launch: every flag defaults OFF. Admin tunability can be added later by
 * defining the CLICSHOPPING_APP_CHATGPT_* constant + a Params file, with no change
 * to this class.
 *
 * @package ClicShopping\AI\Config
 * @version 1.0.0
 * @since 2026-06-22
 */
class ChatCriticConfig
{
    /** @var string Global core constant identifier mapped to the active chat seam status toggle. */
    private const CONST_CHAT_SEAM_STATUS = 'CLICSHOPPING_APP_CHATGPT_AC_CHAT_SEAM_STATUS';

    /** @var array{chat_seam_status: bool} Internal fallback default array states. */
    private const DEFAULTS = [
        'chat_seam_status' => false,
    ];

    /** @var array<string, bool>|null Static runtime cache store housing the resolved configuration settings. */
    private static ?array $config = null;

    /** @var bool Internal debug level flag mapping whether to emit logging messages to standard error logs. */
    private static bool $debug = false;

    /**
     * Whether the chat critic seam is engaged on the chat chokepoint.
     * Dark-launch default: false.
     * * @return bool True if the chat chokepoint integration is active, false otherwise.
     */
    public static function isSeamEnabled(): bool
    {
        self::initialize();
        return self::$config['chat_seam_status'] ?? false;
    }

    /**
     * Bootstraps configuration parameters and resolves environmental logger flags.
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
            error_log('ChatCriticConfig: Initialized with chat_seam_status='
                . (self::$config['chat_seam_status'] ? 'true' : 'false'));
        }
    }

    /**
     * Evaluates global context constants to construct the current configuration map state.
     * * @return array<string, bool> Map containing loaded runtime state attributes.
     */
    private static function loadConfigFromConstants(): array
    {
        $config = self::DEFAULTS;

        if (\defined(self::CONST_CHAT_SEAM_STATUS)) {
            $config['chat_seam_status'] = self::parseBool(constant(self::CONST_CHAT_SEAM_STATUS));
        }

        return $config;
    }

    /**
     * Converts varied configuration input datatypes cleanly into scalar boolean types.
     * * @param mixed $value Configuration values parsing target.
     * @return bool Normalized true or false state.
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
