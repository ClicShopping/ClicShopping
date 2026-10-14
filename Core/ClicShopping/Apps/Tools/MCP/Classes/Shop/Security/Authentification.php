<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Tools\MCP\Classes\Shop\Security;


use ClicShopping\OM\HTML;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;


/**
 * Handles MCP authentication and session management for the Shop side.
 */
class Authentification
{
    private string $username;
    private string $key;
    private ?string $ip;
    private mixed $db;

    /**
     * Constructor for the Authentification class.
     *
     * @param string $username The username for authentication.
     * @param string $key The MCP key for authentication.
     * @param string|null $ip The IP address (optional).
     */
    public function __construct(string $username, string $key, ?string $ip = null)
    {
        $this->username = HTML::sanitize($username);
        $this->key = $key;
        $this->ip = $ip ? HTML::sanitize($ip) : HTTP::getIpAddress();
        $this->db = Registry::get('Db');
    }

    /**
     * Validates IP addresses for the given MCP ID.
     *
     * @param int $mcpId The MCP ID to check IP restrictions for.
     * @return bool True if IP is allowed, false otherwise.
     */
    public function getIps(int $mcpId): bool
    {
        return McpSecurity::validateIp($mcpId);
    }

    /**
     * Creates a new session for the authenticated user.
     *
     * @param int $mcpId The MCP ID to create a session for.
     * @return string The session token.
     * @throws \Exception If session creation fails.
     */
    public function addSession(int $mcpId): string
    {
        try {
            $sessionId = bin2hex(random_bytes(16));

            $sqlDataArray = [
                'mcp_id' => $mcpId,
                'session_id' => $sessionId,
                'ip' => $this->ip,
                'date_added' => 'now()',
                'date_modified' => 'now()'
            ];

            $result = $this->db->save('mcp_session', $sqlDataArray);

            if (!$result) {
                throw new \Exception('Failed to create session');
            }

            McpSecurity::logSecurityEvent('Session created via authentication', [
                'mcp_id' => $mcpId,
                'username' => $this->username,
                'session_id' => $sessionId
            ]);

            return $sessionId;

        } catch (\Exception $e) {
            McpSecurity::logSecurityEvent('Session creation failed', [
                'mcp_id' => $mcpId,
                'username' => $this->username,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Gets the username.
     *
     * @return string The username.
     */
    public function getUsername(): string
    {
        return $this->username;
    }

    /**
     * Gets the MCP key.
     *
     * @return string The MCP key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Gets the IP address.
     *
     * @return string|null The IP address.
     */
    public function getIp(): ?string
    {
        return $this->ip;
    }

  /**
   * Gets the MCP ID from the database using the authenticated username.
   *
   * @return int The MCP ID (0 if not found).
   */
  private function getMcpId(): int
  {
    $Qcheck = $this->db->prepare(' SELECT mcp_id
                                    FROM :table_mcp
                                    WHERE username = :mcp_username
                                ');

    $Qcheck->bindValue(':mcp_username', $this->username);
    $Qcheck->execute();

    if ($Qcheck->fetch()) {
      return (int)$Qcheck->valueInt('mcp_id');
    }

    return 0;
  }

  /**
   * Attempts to authenticate the user and creates a session upon success.
   *
   * @return string The newly created session ID.
   * @throws \Exception If authentication fails or session creation is unsuccessful.
   */
  public function authenticateAndCreateSession(): string
  {
    try {
      // Appelle McpSecurity::checkCredentials (qui vérifie la clé et le username)
      McpSecurity::checkCredentials($this->username, $this->key);
    } catch (\Exception $e) {
      throw new \Exception('Authentication Failed: ' . $e->getMessage(), 0, $e);
    }

    // 2. Récupération de l'ID utilisateur (Nouvelle méthode ajoutée ci-dessus)
    $mcpId = $this->getMcpId();

    if ($mcpId === 0) {
      // Cela ne devrait pas arriver si McpSecurity::checkCredentials est correct, mais c'est une sécurité.
      throw new \Exception('User not found after successful validation.');
    }

    // 3. CRÉATION D'UNE NOUVELLE SESSION (Appel à la méthode addSession déjà définie)
    return $this->addSession($mcpId);
  }
}