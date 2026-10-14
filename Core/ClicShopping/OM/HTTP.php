<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\OM;

use ClicShopping\OM\Is\IpAddress;
use Exception;
use GuzzleHttp\Client as GuzzleClient;
use InvalidArgumentException;

use function in_array;
use const JSON_PRETTY_PRINT;

/**
 * The HTTP class provides a collection of static methods to handle HTTP requests, responses,
 * redirections, client IP retrieval, and security configurations such as HSTS.
 *
 * It utilizes the GuzzleHttp client for handling requests and provides utility methods
 * to manipulate HTTP headers and retrieve domain or IP-related information.
 */
class HTTP
{
  protected static string $request_type;

  /**
   * Determines and sets the type of the current request (SSL or NONSSL) based on server environment variables.
   *
   * @return void
   */
  public static function setRequestType()
  {
    static::$request_type = ((isset($_SERVER['HTTPS']) && (mb_strtolower($_SERVER['HTTPS']) == 'on')) || (isset($_SERVER['SERVER_PORT']) && ($_SERVER['SERVER_PORT'] == 443))) ? 'SSL' : 'NONSSL';
  }

  /**
   * Retrieves the current request type.
   *
   * @return string The type of the request.
   */
  public static function getRequestType(): string
  {
    return static::$request_type;
  }

  /*
   * Use HTTP Strict Transport Security to force client to use secure connections only
   */
  /**
   * Handles HTTP Strict Transport Security (HSTS) for secure connections.
   *
   * @param bool $use_sts Determines whether to send HSTS headers or redirect to HTTPS.
   *                       If true, the method sends the HSTS header. If false, it redirects to HTTPS and terminates further execution.
   * @return bool Returns true if HSTS header was sent successfully, false otherwise.
   */
  public static function getHSTS(bool $use_sts = true): bool
  {
    if (headers_sent($filename, $linenum)) {
      trigger_error("Headers already sent in $filename on line $linenum");
      return false;
    }

    if (static::$request_type !== 'SSL') {
      return false; // pas en HTTPS
    }

    if ($use_sts === true) {
      header('Strict-Transport-Security: max-age=15768000; includeSubDomains; preload'); // 6 mois
      return true;
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';

    // Nettoyage sécurisé
    $host = preg_replace('/[^a-zA-Z0-9\.\-]/', '', $host);
    $uri = filter_var($uri, FILTER_SANITIZE_URL);

    if ($host && $uri) {
      header('Location: https://' . $host . $uri, true, 301);
      exit();
    }

    return false;
  }

  /**
   * Redirects the browser to the specified URL with an optional HTTP response code.
   *
   * @param string|null $url The URL to redirect to. It can be null.
   * @param int $http_response_code Optional HTTP response status code for the redirection. Defaults to 0.
   * @return never
   */
  public static function redirect(string|null $url = null, int $http_response_code = 302): never
  {
    $url ??= 'index.php';

    if (preg_match('/[\r\n]/', $url)) {
      exit;
    }

    if (str_contains($url, '&amp;')) {
      $url = str_replace('&amp;', '&', $url);
    }

    header('Location: ' . $url, true, $http_response_code);
    exit;
  }

  /**
   * Mask credential-looking query parameters in a URL or an error message.
   *
   * Applied at the LOGGING point, never at the caller: a secret travelling in a query string
   * (SerpAPI `api_key`, tokens, signatures) would otherwise be written in clear to
   * `Work/Log/errors-*.txt`, which is readable from the back-office and ends up in backups.
   * Everything else is preserved so the log stays diagnosable.
   *
   * @param string $text URL or free-form message about to be logged.
   * @return string The same text with credential values replaced by `***`.
   */
  public static function redactSecrets(string $text): string
  {
    $pattern = '/\b(api[_-]?key|access[_-]?token|client[_-]?secret|signature|password|passwd|token|secret|auth|pwd|key)=[^&\s"\'\]\)]+/i';

    return preg_replace($pattern, '$1=***', $text) ?? $text;
  }

  /**
   * Sends an HTTP request based on the provided data and retrieves the response.
   *
   * @param array $data An associative array containing the following keys:
   *                    - 'header' (array): Optional. An array of request headers.
   *                    - 'parameters' (mixed): Optional. Parameters to be sent with the request.
   *                    - 'method' (string): Optional. HTTP method to use ('get' or 'post').
   *                    - 'cafile' (string): Optional. Path to the certificate authority file for SSL validation.
   *                    - 'format' (string): Optional. Expected response format, e.g., 'json'.
   *                    - 'url' (string): Required. The URL for the request.
   *                    - 'certificate' (string): Optional. Path to the certificate file for SSL authentication.
   *                    - 'timeout' (int): Optional. Request timeout in seconds (Guzzle connect_timeout + timeout). Default: no timeout.
   * @return mixed The response body. If 'format' is set to 'json', the response will be decoded into an array. Returns false if an error occurs.
   */
  public static function getResponse(array $data, array|null $allowed_hosts = null): mixed
  {
    if (!isset($data['header']) || !\is_array($data['header'])) {
      $data['header'] = [];
    }

    if (!isset($data['parameters'])) {
      $data['parameters'] = '';
    }

    if (!isset($data['method'])) {
      $data['method'] = !empty($data['parameters']) ? 'post' : 'get';
    }

    if (!isset($data['cafile'])) {
      $data['cafile'] = CLICSHOPPING::BASE_DIR . 'External/cacert.pem';
    }

    if (isset($data['format']) && !in_array($data['format'], ['json'])) {
      trigger_error('HttpRequest::getResponse(): Unknown "format": ' . $data['format']);

      unset($data['format']);
    }

    // Add this before making the request in getResponse()
    if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
      trigger_error('Invalid URL provided to getResponse().');
      return false;
    }

    // Only the web schemes: file:// and friends are not what this method is for.
    if (!in_array(strtolower((string)parse_url($data['url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
      trigger_error('URL scheme not allowed in getResponse().');
      return false;
    }

    // A caller owning its own outbound policy forces the private-network gate.
    $allowPrivate = isset($data['allow_private_network']) ? (bool)$data['allow_private_network'] : null;

    // Check if the URL is allowed — same decision as the parallel call, and as every redirect hop.
    if (!self::hostAllowed(parse_url($data['url'], PHP_URL_HOST), $allowed_hosts, $allowPrivate)) {
      trigger_error('URL host not allowed in getResponse().');
      return false;
    }

    $options = [
      // A redirect is a second request: the allowlist decides again, or it decides nothing.
      'allow_redirects' => [
        'on_redirect' => static function ($request, $response, $uri) use ($allowed_hosts, $allowPrivate): void {
          if (!self::hostAllowed($uri->getHost(), $allowed_hosts, $allowPrivate)) {
            throw new \RuntimeException('Redirect to a host that is not allowed: ' . $uri->getHost());
          }
        },
      ],
    ];

    if (!empty($data['header'])) {
      foreach ($data['header'] as $h) {
        [$key, $value] = explode(':', $h, 2);

        $options['headers'][$key] = $value;

        unset($key);
        unset($value);
      }
    }

    if (isset($data['format']) && ($data['format'] === 'json')) {
      $options['json'] = $data['parameters'];
    } else {
      if (($data['method'] === 'post') && !empty($data['parameters'])) {
        if (!isset($options['headers'], $options['headers']['Content-Type'])) {
          $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        $options['body'] = $data['parameters'];
      }
    }

    if (isset($data['cafile']) && is_file($data['cafile'])) {
      $options['verify'] = $data['cafile'];
    }

    if (isset($data['certificate']) && is_file($data['certificate'])) {
      $options['cert'] = $data['certificate'];
    }

    if (isset($data['timeout']) && $data['timeout'] > 0) {
      $options['timeout']         = (int)$data['timeout'];
      $options['connect_timeout'] = (int)$data['timeout'];
    }

    $result = false;

    try {
      $client = new GuzzleClient();
      $response = $client->request($data['method'], $data['url'], $options);

      $result = $response->getBody()->getContents();

      if (isset($data['format']) && ($data['format'] === 'json')) {
        $result = json_decode($result, true);
      }
    } catch (Exception $e) {
      // Log only method and URL — never log headers or options (may contain credentials/tokens).
      // The URL itself carries secrets (api_key=…) and Guzzle repeats it: redact both.
      trigger_error('HTTP::getResponse() failed [' . strtoupper($data['method']) . ' '
        . static::redactSecrets($data['url']) . ']: ' . static::redactSecrets($e->getMessage()));
    }

    return $result;
  }

  /**
   * Sets the HTTP response code for the current execution context.
   *
   * @param int $code The HTTP response code to be set.
   * @return bool Returns true if the response code is successfully set. Throws an exception and returns false if the headers are already sent.
   */

  public static function setResponseCode(int $code): bool
  {
    if (headers_sent()) {
      throw new InvalidArgumentException('HTTP::setResponseCode() - headers already sent, cannot set response code.');
    }

    http_response_code($code);

    return true;
  }

  /**
   * Retrieves the IP address of the client making the request.
   * Optionally, it can return the IP in its integer representation.
   *
   * @param bool $to_int Indicates whether the IP address should be returned as an integer.
   *                      If true, the IP address is converted to an unsigned integer.
   *                      Defaults to false.
   *
   * @return string The IP address of the client. Returns "0.0.0.0" if no valid IP address is found.
   */

  public static function getIpAddress(bool $to_int = false): string
  {
    $ip = null;

    // Priorité aux proxys
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
      $ip = $ips[0]; // IP client réel
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_CLUSTER_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_X_CLUSTER_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_PROXY_USER'])) {
      $ip = $_SERVER['HTTP_PROXY_USER'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    // Validation IP (IPv4 ou IPv6)
    if (empty($ip) || !IpAddress::execute($ip, 'any')) {
      $ip = '0.0.0.0';
    }

    if ($to_int === true) {
      // Conversion IPv4 uniquement
      if (IpAddress::execute($ip, 'ipv4')) {
        $ipLong = ip2long($ip);
        return sprintf('%u', $ipLong);
      }
      return '0'; // IPv6 ou IP invalide ne peut pas être convertie
    }

    return $ip;
  }

  /**
   * Retrieves the name of the internet service provider (ISP) for the customer based on their IP address.
   *
   * This method checks various server variables to determine the client's IP address, prioritizing
   * proxy headers if present. It then performs a reverse DNS lookup to obtain the hostname associated
   * with the IP address and extracts a simplified provider name from it.
   *
   * @return string The name of the internet service provider or 'Unknown or localhost' if the IP is not defined or is localhost.
   */
  public static function getProviderNameCustomer(): string
  {
    $ip = null;

    // Priorité aux proxies si présents
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ips = array_map('trim', explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']));
      $ip = $ips[0]; // IP réelle du client
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    // IP non définie ou localhost
    if (empty($ip) || $ip === '::1' || $ip === '127.0.0.1') {
      return 'Unknown or localhost';
    }

    // Résolution DNS inversée
    $hostname = @gethostbyaddr($ip);
    if ($hostname === false || filter_var($hostname, FILTER_VALIDATE_IP)) {
      return 'Unknown or localhost';
    }

    // Extraire deux premiers segments pour une forme simplifiée
    $segments = preg_split('/[.:]/', $hostname); // support IPv6 segmenté
    $provider = $segments[0] ?? '';
    if (isset($segments[1])) {
      $provider .= '.' . $segments[1];
    }

    // Nettoyage pour sécurité (évite injection HTML/JS)
    return htmlspecialchars($provider, ENT_QUOTES, 'UTF-8');
  }


  /**
   * Determines the URL domain based on the current site type.
   *
   * @return string Returns the full domain URL for either the admin panel or the shop, depending on the site context.
   */
  public static function typeUrlDomain(): string
  {
    if (CLICSHOPPING::getSite() === 'ClicShoppingAdmin') {
      $domain = CLICSHOPPING::getConfig('http_server', 'ClicShoppingAdmin') . CLICSHOPPING::getConfig('http_path', 'ClicShoppingAdmin');
    } else {
      $domain = static::getShopUrlDomain();
    }

    return $domain;
  }

  /**
   * Retrieves the shop's URL domain by combining the HTTP server and HTTP path configurations.
   *
   * @return string The constructed shop URL domain.
   */
  public static function getShopUrlDomain(): string
  {
    $domain = CLICSHOPPING::getConfig('http_server', 'Shop') . CLICSHOPPING::getConfig('http_path', 'Shop');

    return $domain;
  }

  /**
   * Retrieves the URI from the server request, removing any OpenID-related query string parameters.
   *
   * @return string The sanitized URI without OpenID-related parameters.
   */
  public static function getUri(): string
  {
    $uri = rtrim(preg_replace('#((?<=\?)|&)openid\.[^&]+#', '', $_SERVER['REQUEST_URI']), '?');

    return $uri;
  }

  /**
   * Constructs and returns the full normalized path based on the given input, separator, and system root configurations.
   *
   * @param string $path The relative or absolute path to be processed. Defaults to an empty string.
   * @param string $separator The directory separator to use for path normalization. Defaults to '/'.
   * @return string The fully resolved and normalized path.
   */
  public static function getFullPath(string $path = '', string $separator = '/'): string
  {
    $systemroot = CLICSHOPPING::getSite();

    // Normalize system root and base paths
    $systemroot = rtrim($systemroot, $separator) . $separator;
    $base = rtrim($systemroot, $separator) . $separator;

    if ($path === '' || $path === '.' . $separator) {
      return $systemroot;
    }

    if (str_starts_with($path, '..' . $separator)) {
      $path = $systemroot . $path;
    }

    // Normalize path
    $path = rtrim($path, $separator) . $separator;

    // Absolute path
    if ($path[0] === $separator || str_starts_with($path, $systemroot)) {
      return $path;
    }

    // Relative path from 'Here'
    if (str_starts_with($path, '.' . $separator) || $path[0] !== '.') {
      $arrn = preg_split('/\\' . $separator . '/', $path, -1, PREG_SPLIT_NO_EMPTY);
      if ($arrn[0] !== '.') {
        array_unshift($arrn, '.');
      }
      $arrn[0] = rtrim($base, $separator);
      
      return implode($separator, $arrn);
    }

    return $path;
  }

  /**
   * Is this host one the caller allowed, and may we reach it at all?
   *
   * Two decisions, two owners: the allowlist belongs to the developer (which hosts this feature
   * calls), the private-network gate to the operator (HTTP_BLOCK_PRIVATE_NETWORK).
   *
   * @param string|null $host Host part of the URL
   * @param array|null $allowed_hosts Allowed hostnames, or null for no restriction
   * @param bool|null $allow_private true/false forces the gate, null follows the configuration.
   *                                 A caller that owns its own outbound policy forces it.
   * @return bool
   */
  private static function hostAllowed(?string $host, array|null $allowed_hosts, ?bool $allow_private = null): bool
  {
    $host = self::normaliseHost($host);

    if (\is_array($allowed_hosts)) {
      if ($host === '') {
        return false;
      }

      $allowed = array_map(static fn(string $h): string => self::normaliseHost($h), $allowed_hosts);

      if (!in_array($host, $allowed, true)) {
        return false;
      }
    }

    return self::privateNetworkAllowed($host, $allow_private);
  }

  /**
   * Lowercase, no trailing dot, no IPv6 brackets — an allowlist compares graphies.
   *
   * @param string|null $host Raw host, as parse_url() returns it
   * @return string
   */
  private static function normaliseHost(?string $host): string
  {
    return trim(rtrim(strtolower((string)$host), '.'), '[]');
  }

  /**
   * May we reach this host when it sits on a private network?
   *
   * The operator decides through HTTP_BLOCK_PRIVATE_NETWORK; a caller that owns its own outbound
   * policy — the AI layer and its sovereign mode — forces the answer instead of inheriting it.
   * A name resolving to several addresses is refused as soon as ONE of them is private.
   *
   * @param string $host Normalised host
   * @param bool|null $allow_private Forced answer, or null to follow the configuration
   * @return bool
   */
  private static function privateNetworkAllowed(string $host, ?bool $allow_private): bool
  {
    if ($allow_private === true) {
      return true;
    }

    if ($allow_private === null) {
      // Absent constant means no policy: a fresh install and install/rpc.php must keep working.
      $blocking = \defined('HTTP_BLOCK_PRIVATE_NETWORK') && HTTP_BLOCK_PRIVATE_NETWORK == 'true';

      if (!$blocking) {
        return true;
      }
    }

    if ($host === '') {
      return false;
    }

    // A literal address needs no resolution.
    if (IpAddress::execute($host, 'any')) {
      return IpAddress::execute($host, 'public');
    }

    $addresses = gethostbynamel($host);

    if ($addresses === false) {
      return false;
    }

    foreach ($addresses as $address) {
      if (!IpAddress::execute($address, 'public')) {
        return false;
      }
    }

    return true;
  }

  /**
   * Executes multiple HTTP requests in parallel using Guzzle promises.
   *
   * This method is designed for scenarios where multiple independent HTTP requests
   * need to be executed concurrently to improve performance (e.g., fetching data
   * from multiple APIs simultaneously).
   *
   * @param array $requests An array of request configurations. Each element should be an associative array with:
   *                        - 'url' (string): Required. The URL for the request.
   *                        - 'method' (string): Optional. HTTP method ('get' or 'post'). Defaults to 'get'.
   *                        - 'header' (array): Optional. Array of request headers.
   *                        - 'parameters' (mixed): Optional. Parameters to send with the request.
   *                        - 'timeout' (int): Optional. Request timeout in seconds. Defaults to 10.
   *                        - 'format' (string): Optional. Expected response format (e.g., 'json').
   * @param array|null $allowed_hosts Optional. Array of allowed hostnames for security validation.
   *                                   If provided, only requests to these hosts will be executed.
   * @param int $max_concurrent Optional. How many requests are in flight at once. Requests beyond
   *                            that wait for the current batch, so a long list cannot open one
   *                            socket per entry. Defaults to 5.
   * @return array An array of responses indexed by the same keys as the input $requests array.
   *               Each response contains:
   *               - 'success' (bool): Whether the request succeeded.
   *               - 'data' (mixed): The response body (decoded if format='json'), or null on failure.
   *               - 'error' (string|null): Error message if the request failed.
   *               - 'status_code' (int|null): HTTP status code of the response.
   *
   * @example
   * ```php
   * $requests = [
   *   'api1' => ['url' => 'https://api1.example.com/data', 'method' => 'get'],
   *   'api2' => ['url' => 'https://api2.example.com/data', 'method' => 'get', 'timeout' => 5],
   * ];
   * $responses = HTTP::getParallelResponses($requests, ['api1.example.com', 'api2.example.com']);
   * if ($responses['api1']['success']) {
   *   $data = $responses['api1']['data'];
   * }
   * ```
   */
  public static function getParallelResponses(array $requests, array|null $allowed_hosts = null, int $max_concurrent = 5): array
  {
    if (empty($requests)) {
      return [];
    }

    $client = new GuzzleClient();
    $promises = [];
    $results = [];

    // Build promises for each request
    foreach ($requests as $key => $data) {
      // Validate URL
      if (!isset($data['url']) || !filter_var($data['url'], FILTER_VALIDATE_URL)) {
        $results[$key] = [
          'success' => false,
          'data' => null,
          'error' => 'Invalid URL provided',
          'status_code' => null,
        ];
        continue;
      }

      // Only the web schemes: file:// and friends are not what this method is for.
      if (!in_array(strtolower((string)parse_url($data['url'], PHP_URL_SCHEME)), ['http', 'https'], true)) {
        $results[$key] = [
          'success' => false,
          'data' => null,
          'error' => 'URL scheme not allowed',
          'status_code' => null,
        ];
        continue;
      }

      // A caller owning its own outbound policy forces the private-network gate.
      $allowPrivate = isset($data['allow_private_network']) ? (bool)$data['allow_private_network'] : null;

      // Check if the URL is allowed
      if (!self::hostAllowed(parse_url($data['url'], PHP_URL_HOST), $allowed_hosts, $allowPrivate)) {
        $results[$key] = [
          'success' => false,
          'data' => null,
          'error' => 'URL host not allowed',
          'status_code' => null,
        ];
        continue;
      }

      // Set defaults
      if (!isset($data['method'])) {
        $data['method'] = !empty($data['parameters']) ? 'post' : 'get';
      }

      if (!isset($data['timeout'])) {
        $data['timeout'] = 10;
      }

      if (!isset($data['cafile'])) {
        $data['cafile'] = CLICSHOPPING::BASE_DIR . 'External/cacert.pem';
      }

      // Build Guzzle options
      $options = [
        'timeout' => (int)$data['timeout'],
        'connect_timeout' => (int)$data['timeout'],
        'http_errors' => false, // Don't throw exceptions on HTTP errors
        // A redirect is a second request: the allowlist decides again, or it decides nothing.
        'allow_redirects' => [
          'on_redirect' => static function ($request, $response, $uri) use ($allowed_hosts, $allowPrivate): void {
            if (!self::hostAllowed($uri->getHost(), $allowed_hosts, $allowPrivate)) {
              throw new \RuntimeException('Redirect to a host that is not allowed: ' . $uri->getHost());
            }
          },
        ],
      ];

      // Add headers
      if (!empty($data['header']) && \is_array($data['header'])) {
        foreach ($data['header'] as $h) {
          [$headerKey, $value] = explode(':', $h, 2);
          $options['headers'][$headerKey] = trim($value);
        }
      }

      // Add parameters
      if (isset($data['format']) && ($data['format'] === 'json')) {
        $options['json'] = $data['parameters'] ?? [];
      } else {
        if (($data['method'] === 'post') && !empty($data['parameters'])) {
          if (!isset($options['headers']['Content-Type'])) {
            $options['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
          }
          $options['body'] = $data['parameters'];
        }
      }

      // Add SSL verification
      if (isset($data['cafile']) && is_file($data['cafile'])) {
        $options['verify'] = $data['cafile'];
      }

      if (isset($data['certificate']) && is_file($data['certificate'])) {
        $options['cert'] = $data['certificate'];
      }

      // Create async promise
      try {
        $promises[$key] = $client->requestAsync($data['method'], $data['url'], $options);
      } catch (Exception $e) {
        $results[$key] = [
          'success' => false,
          'data' => null,
          'error' => 'Failed to create request: ' . $e->getMessage(),
          'status_code' => null,
        ];
      }
    }

    // Execute in batches: a caller passing a long list must not open one socket per entry.
    foreach (array_chunk($promises, max(1, $max_concurrent), true) as $batch) {
      $responses = \GuzzleHttp\Promise\Utils::settle($batch)->wait();

      foreach ($responses as $key => $response) {
        if ($response['state'] === 'fulfilled') {
          try {
            $httpResponse = $response['value'];
            $statusCode = $httpResponse->getStatusCode();
            $body = $httpResponse->getBody()->getContents();

            // Decode JSON if requested
            $data = $body;
            if (isset($requests[$key]['format']) && $requests[$key]['format'] === 'json') {
              $decoded = json_decode($body, true);
              if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
              }
            }

            $results[$key] = [
              'success' => true,
              'data' => $data,
              'error' => null,
              'status_code' => $statusCode,
            ];
          } catch (Exception $e) {
            $results[$key] = [
              'success' => false,
              'data' => null,
              'error' => 'Failed to process response: ' . $e->getMessage(),
              'status_code' => null,
            ];
          }
        } else {
          // Promise rejected
          $exception = $response['reason'];
          $results[$key] = [
            'success' => false,
            'data' => null,
            'error' => $exception->getMessage(),
            'status_code' => null,
          ];
        }
      }
    }

    return $results;
  }
}