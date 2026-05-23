<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Common;

use ClicShopping\Apps\Orders\Orders\Orders as OrdersApp;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

class EInvoiceService
{
  public const STATUS_INVOICE     = 2;
  public const STATUS_CANCEL      = 3;
  public const STATUS_CREDIT_NOTE = 4;

  private const TYPE_INVOICE     = 'FAC';
  private const TYPE_CREDIT_NOTE = 'AVR';
  private const OAUTH_SANDBOX  = 'https://sandbox-oauth.piste.gouv.fr/api/oauth/token';
  private const OAUTH_PROD     = 'https://oauth.piste.gouv.fr/api/oauth/token';
  private const API_SANDBOX    = 'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/soumettre';
  private const API_PROD       = 'https://api.piste.gouv.fr/cpro/factures/v1/soumettre';
  private const STATUS_SANDBOX = 'https://sandbox-api.piste.gouv.fr/cpro/factures/v1/consulter/fournisseur';
  private const STATUS_PROD    = 'https://api.piste.gouv.fr/cpro/factures/v1/consulter/fournisseur';

  // ── Chorus Pro portal URL (used in admin buttons) ────────────────────────
  public const CHORUS_PORTAL_URL = 'https://portail.chorus-pro.gouv.fr';

  // ── OAuth2 in-memory token cache (valid 1 hour per AIFE specification) ───
  private ?string $cachedToken    = null;
  private int     $tokenExpiresAt = 0;
  private mixed   $app;
  private mixed   $db;
  private ?string $logFile        = null;

  /**
   * Constructor — resolves dependencies from the Registry and loads language definitions.
   * Ensures the Orders application is registered before use.
   */
  public function __construct()
  {
    if (!Registry::exists('Orders')) {
      Registry::set('Orders', new OrdersApp());
    }

    $this->app = Registry::get('Orders');
    $this->db  = Registry::get('Db');

    $this->app->loadDefinitions('Sites/ClicShoppingAdmin/e_invoice_service');

    // Initialize log file path
    $logDir = CLICSHOPPING::getConfig('dir_root', 'Shop') . 'Core/ClicShopping/Work/Log/';
    
    if (!is_dir($logDir)) {
      @mkdir($logDir, 0755, true);
    }
    
    $this->logFile = $logDir . 'einvoice_' . date('Y-m') . '.log';
  }

  /**
   * Writes a log entry to the monthly log file.
   * Also falls back to error_log() if file writing fails.
   *
   * @param string $level  DEBUG, INFO, WARNING, ERROR
   * @param string $message Log message
   * @param array  $context Optional context data (order_id, etc.)
   */
  private function log(string $level, string $message, array $context = []): void
  {
    $timestamp = date('Y-m-d H:i:s');
    $order_ref = $context['order_id'] ?? '-';
    $log_line  = sprintf('[%s] [%s] [Order:%s] %s', $timestamp, $level, $order_ref, $message);

    // Write to file
    if ($this->logFile !== null) {
      $result = @file_put_contents($this->logFile, $log_line . PHP_EOL, FILE_APPEND | LOCK_EX);
      if ($result === false) {
        // Fallback to error_log if file write fails
        error_log($log_line);
      }
    } else {
      error_log($log_line);
    }
  }

  /**
   * Main method — processes the electronic invoice submission to Chorus Pro.
   *
   * Routes automatically based on the invoice_status value:
   *   STATUS_INVOICE (2)     -> submits a FAC (invoice) document
   *   STATUS_CANCEL (3)      -> submits an AVR (credit note) — legally required to reverse a validated invoice
   *   STATUS_CREDIT_NOTE (4) -> submits an AVR (credit note) document
   *
   * Guards in order:
   *   1. Module must be enabled (CHORUSPRO_ENABLED = True)
   *   2. Invoice status must be actionable (2, 3 or 4)
   *   3. Order must not have been already transmitted (erp_invoice = 0)
   *   4. Customer must have a valid SIRET (B2G check)
   *
   * @param int   $order_id       ClicShopping order ID
   * @param array $customer       $order->customer array from OrderAdmin
   * @param array $info           $order->info array from OrderAdmin
   * @param array $products       $order->products array from OrderAdmin
   * @param array $totals         $order->totals array from OrderAdmin
   * @param int   $invoice_status Numeric value of orders_status_invoice
   * @return array ['success' => bool, 'skipped' => bool, 'message' => string, ...]
   */
  public function process(
    int   $order_id,
    array $customer,
    array $info,
    array $products,
    array $totals,
    int   $invoice_status
  ): array
  {
    if (!$this->isEnabled()) {
      return $this->skip($this->app->getDef('skip_module_disabled'));
    }

    if (!in_array($invoice_status, [self::STATUS_INVOICE, self::STATUS_CANCEL, self::STATUS_CREDIT_NOTE])) {
      return $this->skip($this->app->getDef('skip_status_not_actionable') . ' (' . $invoice_status . ')');
    }

    if ($this->isAlreadySent($order_id)) {
      return $this->skip($this->app->getDef('skip_already_sent') . ' #' . $order_id);
    }

    $siret_client = preg_replace('/\D/', '', $customer['siret'] ?? '');
    if (strlen($siret_client) !== 14) {
      $msg = $this->app->getDef('skip_b2c_no_siret');
      $this->writeOrderHistory($order_id, $msg, false);
      return $this->skip($msg);
    }

    $token = $this->getOAuth2Token();
    if ($token === null) {
      $msg = $this->app->getDef('error_oauth2_failed') . ' #' . $order_id;
      $this->writeOrderHistory($order_id, '[ERROR] ' . $msg, false);
      return $this->failure($msg);
    }

    $doc_type = in_array($invoice_status, [self::STATUS_CANCEL, self::STATUS_CREDIT_NOTE]) ? self::TYPE_CREDIT_NOTE : self::TYPE_INVOICE;

    $payload = $this->buildPayload($order_id, $customer, $info, $products, $totals, $doc_type);
    if ($payload === null) {
      $msg = $this->app->getDef('error_payload_failed');
      $this->writeOrderHistory($order_id, '[ERROR] ' . $msg, false);
      return $this->failure($msg);
    }

    $result = $this->submitToChorusPro($token, $payload);

    if ($result['success']) {
      $numero = $result['response']['numeroFacture']
        ?? $result['response']['identifiantFactureCPP']
        ?? $payload['references']['numeroFactureSaisi'];
      $statut = $result['response']['statutFacture'] ?? 'EN_COURS_ACHEMINEMENT';

      $history = $this->app->getDef('history_transmitted_ok') . "\n"
        . $this->app->getDef('history_chorus_number') . ' : ' . $numero . "\n"
        . $this->app->getDef('history_initial_status') . ' : ' . $statut . "\n"
        . $this->app->getDef('history_doc_type') . ' : ' . $doc_type . ' (' . $payload['references']['numeroFactureSaisi'] . ')' . "\n"
        . $this->app->getDef('history_siret_recipient') . ' : ' . $payload['destinataire']['codeDestinataire'] . "\n"
        . $this->app->getDef('history_portal') . ' : ' . self::CHORUS_PORTAL_URL;

      $this->writeOrderHistory($order_id, $history, true);
      $this->markAsSent($order_id);

      return [
        'success'       => true,
        'skipped'       => false,
        'message'       => $history,
        'numero_chorus' => $numero,
        'statut'        => $statut,
        'portal_url'    => self::CHORUS_PORTAL_URL,
      ];

    } else {
      $history = $this->app->getDef('history_transmission_failed') . "\n"
        . $this->app->getDef('history_error') . ' : ' . $result['error'] . "\n"
        . $this->app->getDef('history_number') . ' : ' . $payload['references']['numeroFactureSaisi'] . "\n"
        . $this->app->getDef('history_portal') . ' : ' . self::CHORUS_PORTAL_URL;

      $this->writeOrderHistory($order_id, $history, false);
      return $this->failure($result['error']);
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // OAUTH2 TOKEN
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Obtains an OAuth2 Bearer token from the PISTE platform using client_credentials flow.
   *
   * Per AIFE documentation (https://communaute.chorus-pro.gouv.fr/documentation/aides-aux-developpeurs-api-en-mode-oauth2/):
   *   - grant_type = client_credentials
   *   - scope      = openid  (explicitly required by AIFE)
   *
   * The token is cached in the instance for the duration of the PHP execution cycle.
   * According to AIFE documentation, the token is valid for 1 hour.
   * A 60-second safety margin is applied before expiry.
   *
   * A single retry is attempted on network failure (HTTP::getResponse() returns false).
   * OAuth2 error responses (400/401/403) are detected via the 'error' key in the JSON body,
   * since HTTP::getResponse() does not expose the HTTP status code.
   *
   * @return string|null  Bearer token string, or null on failure
   */
  private function getOAuth2Token(): ?string
  {
    if ($this->cachedToken !== null && time() < $this->tokenExpiresAt) {
      return $this->cachedToken;
    }

    $sandbox       = $this->isSandbox();
    $oauth_url     = $sandbox ? self::OAUTH_SANDBOX : self::OAUTH_PROD;
    $client_id     = defined('CHORUSPRO_PISTE_CLIENT_ID')     ? CHORUSPRO_PISTE_CLIENT_ID     : '';
    $client_secret = defined('CHORUSPRO_PISTE_CLIENT_SECRET') ? CHORUSPRO_PISTE_CLIENT_SECRET : '';

    if (empty($client_id) || empty($client_secret)) {
      $this->log('ERROR', '[ERROR EInvoiceService] CHORUSPRO_PISTE_CLIENT_ID or CHORUSPRO_PISTE_CLIENT_SECRET not configured.');
      return null;
    }

    $request = [
      'url'        => $oauth_url,
      'method'     => 'post',
      'header'     => ['Content-Type: application/x-www-form-urlencoded'],
      'parameters' => http_build_query([
        'grant_type'    => 'client_credentials',
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'scope'         => 'openid',
      ]),
      'timeout'    => 30,
    ];

    // Single retry on network failure
    $response = HTTP::getResponse($request);
    if ($response === false || empty($response)) {
      $this->log('WARNING', '[WARNING EInvoiceService] OAuth2 PISTE request failed — retrying once.');
      $response = HTTP::getResponse($request);
    }

    if ($response === false || empty($response)) {
      $this->log('ERROR', '[ERROR EInvoiceService] OAuth2 PISTE request failed after retry — network error or unreachable endpoint.');
      return null;
    }

    $decoded = json_decode($response, true) ?? [];

    // OAuth2 error response (400 invalid_client, 401 unauthorized, 403 forbidden)
    // HTTP::getResponse() does not expose status codes — detect via response body
    if (isset($decoded['error'])) {
      // Never log the full response — it may contain tokens or sensitive data
      $this->log('ERROR', '[ERROR EInvoiceService] OAuth2 error — ' . ($decoded['error'] ?? 'unknown') . ': ' . ($decoded['error_description'] ?? '(no description)'));
      return null;
    }

    $token = $decoded['access_token'] ?? null;

    if (empty($token)) {
      $this->log('ERROR', '[ERROR EInvoiceService] access_token missing from OAuth2 response');
      return null;
    }

    // Cache the token with a 60-second safety margin before expiry
    $this->cachedToken    = $token;
    $this->tokenExpiresAt = time() + (int)($decoded['expires_in'] ?? 3600) - 60;

    return $token;
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // PAYLOAD BUILDER
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Builds the JSON payload for the Chorus Pro /cpro/factures/v1/soumettre endpoint.
   *
   * Handles both B2B-to-Government invoice (FAC) and credit note (AVR) document types.
   * Calculates product lines, VAT summary, and totals from the order data.
   * Returns null if either SIRET (supplier or recipient) is invalid.
   *
   * @param int    $order_id  ClicShopping order ID
   * @param array  $customer  Customer data array
   * @param array  $info      Order info array
   * @param array  $products  Products array
   * @param array  $totals    Totals array
   * @param string $doc_type  Document type: TYPE_INVOICE ('FAC') or TYPE_CREDIT_NOTE ('AVR')
   * @return array|null       Ready-to-submit payload, or null on blocking data error
   */
  private function buildPayload(
    int    $order_id,
    array  $customer,
    array  $info,
    array  $products,
    array  $totals,
    string $doc_type = self::TYPE_INVOICE
  ): ?array
  {
    $siret_supplier    = preg_replace('/\D/', '', defined('CHORUSPRO_SIRET_FOURNISSEUR') ? CHORUSPRO_SIRET_FOURNISSEUR : '');
    $siret_recipient   = preg_replace('/\D/', '', $customer['siret'] ?? '');
    $login             = defined('CHORUSPRO_TECHNICAL_LOGIN')    ? CHORUSPRO_TECHNICAL_LOGIN    : '';
    $id_fournisseur    = defined('CHORUSPRO_FOURNISSEUR_ID')     ? (int)CHORUSPRO_FOURNISSEUR_ID : 0;
    $bank_account_code = defined('CHORUSPRO_BANK_ACCOUNT_CODE')  ? (int)CHORUSPRO_BANK_ACCOUNT_CODE : 0;

    if (strlen($siret_supplier) !== 14 || strlen($siret_recipient) !== 14) {
      $this->log('ERROR', '[ERROR EInvoiceService] Invalid SIRET — supplier: "' . $siret_supplier . '" / recipient: "' . $siret_recipient . '"');
      return null;
    }

    if ($id_fournisseur === 0 || $bank_account_code === 0) {
      $this->log('INFO', '[INFO EInvoiceService] CHORUSPRO_FOURNISSEUR_ID or CHORUSPRO_BANK_ACCOUNT_CODE not configured.');
      return null;
    }

    if (empty($login)) {
      $this->log('INFO', '[INFO EInvoiceService] CHORUSPRO_TECHNICAL_LOGIN not configured — required as idUtilisateurCourant.');
      return null;
    }

    if (empty($products)) {
      $this->log('ERROR', '[ERROR EInvoiceService] Order #' . $order_id . ' has no products — lignePoste cannot be empty.');
      return null;
    }

    $date_ts = strtotime($info['date_purchased'] ?? '');
    if ($date_ts === false || $date_ts <= 0) {
      $this->log('WARNING', '[WARNING EInvoiceService] Invalid or missing date_purchased for order #' . $order_id . ' — falling back to current date.');
      $date_ts = time();
    }
    $invoice_num  = date('Ymd', $date_ts) . '-' . $order_id;
    $date_invoice = date('Y-m-d', $date_ts);
    $date_due     = date('Y-m-d', strtotime('+30 days', $date_ts));

    // Extract totals from the order totals array
    $ht = $tva = $ttc = 0.0;
    foreach ($totals as $t) {
      $val = (float)str_replace(',', '.', preg_replace('/[^0-9.,\-]/', '', strip_tags($t['text'] ?? '0')));
      $c   = $t['class'] ?? '';
      if (in_array($c, ['ot_subtotal', 'ST'])) $ht  = $val;
      if (in_array($c, ['ot_tax',      'TX'])) $tva = $val;
      if (in_array($c, ['ot_total',    'TO'])) $ttc = $val;
    }

    // Fallback: compute HT from product lines if ot_subtotal not found
    if ($ht == 0.0) {
      foreach ($products as $p) {
        $ht += (float)$p['final_price'] * (float)$p['qty'];
      }
    }

    if ($ttc == 0.0) $ttc = $ht + $tva;

    // Build product lines (lignePoste) and aggregate VAT by rate
    $lignePoste = [];
    $vat_map    = [];

    foreach ($products as $i => $p) {
      $unit  = round((float)($p['final_price'] ?? 0), 4);
      $qty   = (float)($p['qty'] ?? 1);
      $rate  = (float)($p['tax'] ?? 0);
      $l_ht  = round($unit * $qty, 2);
      $l_vat = round($l_ht * $rate / 100, 2);

      $lignePoste[] = [
        'lignePosteNumero'          => $i + 1,
        'lignePosteReference'       => substr($p['model'] ?? $p['name'] ?? 'REF-' . ($i + 1), 0, 50),
        'lignePosteDenomination'    => substr($p['name'] ?? 'Product', 0, 250),
        'lignePosteQuantite'        => $qty,
        'lignePosteUnite'           => 'U',
        'lignePosteMontantUnitaireHT' => $unit,
        'lignePosteTauxTvaManuel'   => $rate,
        'lignePosteMontantRemiseHT' => 0.00,
      ];

      $k = number_format($rate, 2);
      $vat_map[$k]['base'] = ($vat_map[$k]['base'] ?? 0.0) + $l_ht;
      $vat_map[$k]['tva']  = ($vat_map[$k]['tva']  ?? 0.0) + $l_vat;
    }

    // Build VAT summary lines (ligneTva) from the aggregated map
    $ligneTva = array_values(array_map(
      fn($r, $d) => [
        'ligneTvaTauxManuel'            => (float)$r,
        'ligneTvaMontantBaseHtParTaux'  => round($d['base'], 2),
        'ligneTvaMontantTvaParTaux'     => round($d['tva'], 2),
      ],
      array_keys($vat_map),
      $vat_map
    ));

    return [
      'idUtilisateurCourant' => $login,
      'modeDepot'            => 'SAISIE_API',
      'cadreDeFacturation'   => [
        'codeCadreFacturation' => 'A1_FACTURE_FOURNISSEUR_DOSSIER_STANDARD',
      ],
      'fournisseur'          => [
        'idFournisseur'                       => $id_fournisseur,
        'codeCoordonneesBancairesFournisseur' => $bank_account_code,
      ],
      'destinataire'         => [
        'codeDestinataire' => $siret_recipient,
      ],
      'references'           => [
        'typeFacture'               => $doc_type,
        'deviseFacture'             => strtoupper($info['currency'] ?? 'EUR'),
        'modePaiement'              => $this->resolvePaymentMode($info['payment_method'] ?? ''),
        'typeTva'                   => 'TVA_SUR_DEBIT',
        'numeroFactureSaisi'        => $invoice_num,
        'dateFacture'               => $date_invoice,
        'dateEcheancePaiement'      => $date_due,
        'referenceCommandeAcheteur' => (string)$order_id,
        'mentionsPenalitesRetard'   => 'Late payment penalties: 3x legal interest rate. Fixed recovery fee: 40 EUR (art. L.441-10 French Commercial Code).',
      ],
      'montantTotal'         => [
        'montantHtTotal'           => round($ht, 2),
        'montantTVA'               => round($tva, 2),
        'montantTtcTotal'          => round($ttc, 2),
        'montantAPayer'            => round($ttc, 2),
        'montantRemiseGlobaleTTC'  => 0.00,
      ],
      'lignePoste'           => $lignePoste,
      'ligneTva'             => $ligneTva,
    ];
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // HTTP SUBMISSION TO CHORUS PRO
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Submits the invoice payload to Chorus Pro via the PISTE API gateway.
   *
   * PISTE requires two authentication layers:
   *   - Authorization: Bearer {oauth2_token}      (obtained from getOAuth2Token)
   *   - cpro-account: base64({login}:{password})  (technical account credentials)
   *
   * HTTP::getResponse() returns false on network error, or a JSON string on success.
   * Since getLastStatusCode() does NOT exist in ClicShopping's HTTP class, success is
   * determined by checking the decoded response for the absence of Chorus Pro error keys
   * ('libelle', 'message', 'error') and the presence of a document identifier.
   *
   * @param string $token    OAuth2 Bearer token
   * @param array  $payload  Invoice payload built by buildPayload()
   * @return array           ['success' => bool, 'response' => array, 'error' => string]
   */
  private function submitToChorusPro(string $token, array $payload): array
  {
    $api_url  = $this->isSandbox() ? self::API_SANDBOX : self::API_PROD;
    $login    = defined('CHORUSPRO_TECHNICAL_LOGIN')    ? CHORUSPRO_TECHNICAL_LOGIN    : '';
    $password = defined('CHORUSPRO_TECHNICAL_PASSWORD') ? CHORUSPRO_TECHNICAL_PASSWORD : '';

    if (empty($login) || empty($password)) {
      return ['success' => false, 'response' => [], 'error' => 'Chorus Pro technical account not configured.'];
    }

    // HTTP::getResponse() expects 'header' as an array of "Key: value" strings
    $raw = HTTP::getResponse([
      'url'        => $api_url,
      'method'     => 'post',
      'header'     => [
        'Content-Type: application/json;charset=UTF-8',
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
        'cpro-account: ' . base64_encode($login . ':' . $password),
      ],
      'parameters' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
      'timeout'    => 30,
    ]);

    // No retry on submission — a lost response after success would cause a duplicate invoice.
    // Chorus Pro will reject same numeroFactureSaisi, but the risk is not acceptable.
    if ($raw === false) {
      $this->log('ERROR', '[ERROR EInvoiceService] submitToChorusPro: network failure — invoice #' . ($payload['references']['numeroFactureSaisi'] ?? '?') . ' may or may not have been received by Chorus Pro.');
      return ['success' => false, 'response' => [], 'error' => 'HTTP request failed (network error or invalid URL).'];
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null) {
      $this->log('ERROR', '[ERROR EInvoiceService] submitToChorusPro: invalid JSON response (non-parseable) for invoice #' . ($payload['references']['numeroFactureSaisi'] ?? '?'));
      return ['success' => false, 'response' => [], 'error' => 'Invalid JSON response from Chorus Pro.'];
    }

    // Chorus Pro returns error details in 'libelle', 'message', or 'error' keys
    // Success is indicated by the presence of a document identifier
    $has_error_key = isset($decoded['libelle']) || isset($decoded['message']) || isset($decoded['error']);
    $has_success   = isset($decoded['numeroFacture']) || isset($decoded['identifiantFactureCPP']);

    if ($has_success && !$has_error_key) {
      return ['success' => true, 'response' => $decoded, 'error' => ''];
    }

    // Extract the most relevant error message
    $error = $decoded['libelle'] ?? $decoded['message'] ?? $decoded['error'] ?? 'Unknown error from Chorus Pro.';

    return ['success' => false, 'response' => $decoded, 'error' => $error];
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // STATUS CHECK (callable via CRON or manual refresh)
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Checks the processing status of a previously submitted invoice in Chorus Pro.
   *
   * Possible Chorus Pro status values returned:
   *   EN_COURS_ACHEMINEMENT | MISE_A_DISPOSITION | COMPTABILISEE |
   *   REJETEE | SUSPENDUE | MANDATEE | MISE_EN_PAIEMENT | PAYEE
   *
   * @param string $invoice_number  The invoice number submitted to Chorus Pro
   * @return array ['success' => bool, 'statut' => string, 'response' => array]
   */
  public function checkStatus(string $invoice_number): array
  {
    $token = $this->getOAuth2Token();
    if ($token === null) {
      return ['success' => false, 'error' => $this->app->getDef('error_oauth2_failed')];
    }

    $login    = defined('CHORUSPRO_TECHNICAL_LOGIN')    ? CHORUSPRO_TECHNICAL_LOGIN    : '';
    $password = defined('CHORUSPRO_TECHNICAL_PASSWORD') ? CHORUSPRO_TECHNICAL_PASSWORD : '';

    $raw = HTTP::getResponse([
      'url'        => $this->isSandbox() ? self::STATUS_SANDBOX : self::STATUS_PROD,
      'method'     => 'post',
      'header'     => [
        'Content-Type: application/json;charset=UTF-8',
        'Accept: application/json',
        'Authorization: Bearer ' . $token,
        'cpro-account: ' . base64_encode($login . ':' . $password),
      ],
      'parameters' => json_encode([
        'idUtilisateurCourant' => $login,
        'numeroFacture'        => $invoice_number,
      ]),
      'timeout'    => 30,
    ]);

    // Single retry on network failure — read-only call, safe to retry
    if ($raw === false) {
      $this->log('WARNING', '[WARNING EInvoiceService] checkStatus: network failure on first attempt — retrying for invoice ' . $invoice_number);
      
      $raw = HTTP::getResponse([
        'url'        => $this->isSandbox() ? self::STATUS_SANDBOX : self::STATUS_PROD,
        'method'     => 'post',
        'header'     => [
          'Content-Type: application/json;charset=UTF-8',
          'Accept: application/json',
          'Authorization: Bearer ' . $token,
          'cpro-account: ' . base64_encode($login . ':' . $password),
        ],
        'parameters' => json_encode([
          'idUtilisateurCourant' => $login,
          'numeroFacture'        => $invoice_number,
        ]),
        'timeout'    => 30,
      ]);
    }

    if ($raw === false) {
      $this->log('ERROR', '[ERROR EInvoiceService] checkStatus: network failure after retry for invoice ' . $invoice_number);
      return ['success' => false, 'error' => 'HTTP request failed for status check.'];
    }

    $decoded = json_decode($raw, true);
    if ($decoded === null) {
      $this->log('ERROR', '[ERROR EInvoiceService] checkStatus: invalid JSON response for invoice ' . $invoice_number);
      return ['success' => false, 'error' => 'Invalid JSON response from Chorus Pro status check.'];
    }

    // If the response contains a statutFacture key, the call succeeded
    if (isset($decoded['statutFacture'])) {
      return ['success' => true, 'response' => $decoded, 'statut' => $decoded['statutFacture']];
    }

    $error = $decoded['libelle'] ?? $decoded['message'] ?? 'Unknown status check error.';
    return ['success' => false, 'response' => $decoded, 'error' => $error];
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // ORDER HISTORY WRITER
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Writes the transmission result to the orders_status_history table.
   *
   * The entry will appear in the "Status" tab of the order in the admin panel,
   * providing full traceability of all Chorus Pro interactions.
   * The current order status and invoice status are preserved unchanged.
   *
   * @param int    $order_id  ClicShopping order ID
   * @param string $message   Message to record (success details or error description)
   * @param bool   $success   True for a successful transmission, false for an error
   */
  public function writeOrderHistory(int $order_id, string $message, bool $success): void
  {
    try {
      $Q = $this->db->prepare('select orders_status, orders_status_invoice
                               from :table_orders
                               where orders_id = :orders_id');
      $Q->bindInt(':orders_id', $order_id);
      $Q->execute();

      if ($Q->fetch() === false) {
        return;
      }

      $prefix  = $success ? '[Chorus Pro OK] ' : '[Chorus Pro ERROR] ';
      $comment = $prefix . date('Y-m-d H:i:s') . "\n" . $message;

      $this->db->save('orders_status_history', [
        'orders_id'                => $order_id,
        'orders_status_id'         => $Q->valueInt('orders_status'),
        'orders_status_invoice_id' => $Q->valueInt('orders_status_invoice'),
        'date_added'               => date('Y-m-d H:i:s'),
        'customer_notified'        => 0,
        'comments'                 => $comment,
        'admin_user_name'          => 'Chorus Pro (automatic)',
      ]);
    } catch (\Throwable $e) {
      $this->log('ERROR', '[ERROR EInvoiceService] Cannot write order history: ' . $e->getMessage());
    }
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // PUBLIC HELPER METHODS
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Returns true if the Chorus Pro module is enabled (CHORUSPRO_ENABLED = 'True').
   *
   * @return bool
   */
  public function isEnabled(): bool
  {
    return defined('CHORUSPRO_ENABLED') && CHORUSPRO_ENABLED === 'True';
  }

  /**
   * Returns true if the customer has a valid 14-digit SIRET number,
   * indicating a B2B or B2G customer eligible for Chorus Pro submission.
   *
   * @param array $customer  Customer data array containing 'siret' key
   * @return bool
   */
  public function isB2B(array $customer): bool
  {
    return strlen(preg_replace('/\D/', '', $customer['siret'] ?? '')) === 14;
  }

  /**
   * Returns true if the order has already been transmitted to Chorus Pro.
   * Checks the erp_invoice field in the orders table (1 = transmitted).
   *
   * @param int $order_id  ClicShopping order ID
   * @return bool
   */
  public function isAlreadySent(int $order_id): bool
  {
    try {
      $Q = $this->db->prepare('select erp_invoice 
                               from :table_orders 
                               where orders_id = :orders_id
                               ');
      $Q->bindInt(':orders_id', $order_id);
      $Q->execute();
      return $Q->fetch() !== false && (int)$Q->value('erp_invoice') === 1;
    } catch (\Throwable) {
      return false;
    }
  }

  /**
   * Resolves the Chorus Pro payment mode code from the ClicShopping payment method string.
   *
   * Accepted Chorus Pro values: VIREMENT | PRELEVEMENT | CHEQUE | CARTE | AUTRE
   * Defaults to VIREMENT if no match is found (most common in B2G).
   *
   * @param string $method  Payment method string from $order->info['payment_method']
   * @return string         Chorus Pro payment mode code
   */
  public function resolvePaymentMode(string $method): string
  {
    $m = strtolower($method);
    if (str_contains($m, 'stripe') || str_contains($m, 'card'))        return 'CARTE';
    if (str_contains($m, 'virement') || str_contains($m, 'sepa'))      return 'VIREMENT';
    if (str_contains($m, 'prelevement') || str_contains($m, 'debit'))  return 'PRELEVEMENT';
    if (str_contains($m, 'cheque'))                                     return 'CHEQUE';
    return 'VIREMENT';
  }

  // ═══════════════════════════════════════════════════════════════════════════
  // PRIVATE HELPERS
  // ═══════════════════════════════════════════════════════════════════════════

  /**
   * Returns true if the sandbox mode is active (CHORUSPRO_SANDBOX = 'True').
   * In sandbox mode, requests are sent to the PISTE qualification environment.
   *
   * @return bool
   */
  private function isSandbox(): bool
  {
    return defined('CHORUSPRO_SANDBOX') && CHORUSPRO_SANDBOX === 'True';
  }

  /**
   * Marks the order as transmitted to Chorus Pro by setting erp_invoice = 1.
   * This prevents duplicate transmissions on subsequent status changes.
   *
   * @param int $order_id  ClicShopping order ID
   */
  private function markAsSent(int $order_id): void
  {
    try {
      $this->db->save('orders', ['erp_invoice' => 1], ['orders_id' => $order_id]);
    } catch (\Throwable $e) {
      $this->log('ERROR', '[ERROR EInvoiceService] Cannot update erp_invoice: ' . $e->getMessage());
    }
  }

  /**
   * Returns a standard "skipped" result array.
   * Used when a guard condition prevents transmission (not an error).
   *
   * @param string $msg  Reason for skipping
   * @return array
   */
  private function skip(string $msg): array
  {
    return ['success' => false, 'skipped' => true, 'message' => $msg];
  }

  /**
   * Returns a standard "failure" result array.
   * Used when a transmission attempt was made but failed.
   *
   * @param string $msg  Error description
   * @return array
   */
  private function failure(string $msg): array
  {
    return ['success' => false, 'skipped' => false, 'message' => $msg];
  }
}
