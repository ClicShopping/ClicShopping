<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Customers\Groups\Classes\ClicShoppingAdmin;

use ClicShopping\OM\HTTP;
use SoapClient;
/**
 * Get the prefix for Intracommunity VAT numbers for various countries.
 *
 * This method returns an associative array where the keys represent ISO
 * code of countries and values represent the corresponding VAT prefix.
 *
 * @return array Returns an array of ISO country codes and their VAT prefixes.
 */
class VatNumber
{
  /**
   * Retrieves an associative array of intracommunity VAT prefixes for European Union countries.
   *
   * @return array An associative array where the keys represent country codes and the values are the corresponding VAT prefixes.
   */
  public static function getPrefixIntracomVAT(): array
  {
    $intracomArray = [
      'AT' => 'AT',
      //Austria
      'BE' => 'BE',
      //Belgium
      'DK' => 'DK',
      //Denmark
      'FI' => 'FI',
      //Finland
      'FR' => 'FR',
      //France
      'FX' => 'FR',
      //France métropolitaine
      'DE' => 'DE',
      //Germany
      'GR' => 'EL',
      //Greece
      'IE' => 'IE',
      //Irland
      'IT' => 'IT',
      //Italy
      'LU' => 'LU',
      //Luxembourg
      'NL' => 'NL',
      //Netherlands
      'PT' => 'PT',
      //Portugal
      'ES' => 'ES',
      //Spain
      'SE' => 'SE',
      //Sweden
      'CY' => 'CY',
      //Cyprus
      'EE' => 'EE',
      //Estonia
      'HU' => 'HU',
      //Hungary
      'LV' => 'LV',
      //Latvia
      'LT' => 'LT',
      //Lithuania
      'MT' => 'MT',
      //Malta
      'PL' => 'PL',
      //Poland
      'SK' => 'SK',
      //Slovakia
      'CZ' => 'CZ',
      //Czech Republic
      'SI' => 'SI',
      //Slovenia
      'RO' => 'RO',
      //Romania
      'BG' => 'BG',
      //Bulgaria
      'HR' => 'HR',
      //Croatia
      'XI' => 'XI'
      // Norhen Ireland
    ];

    return $intracomArray;
  }

  /**
   * Checks if the provided ISO country code is valid and present in the list of prefixes for Intracom VAT.
   *
   * @param string $country_iso The ISO country code to be checked.
   * @return bool Returns true if the country ISO is invalid or not found in the list; otherwise, false.
   */
  public static function checkIsoCountry(string $country_iso): bool
  {
    if (strlen($country_iso) != 2) {
      return true;
    }

    foreach (static::getPrefixIntracomVAT() as $value) {
      if (mb_strtoupper($value) == mb_strtoupper($country_iso)) {
        return false;
      }
    }

    // 2-char code but not in the EU intracom list: treat as invalid.
    return true;
  }

  /**
   * Checks the availability of a web service by attempting to create a SOAP client.
   *
   * @return mixed Returns the SOAP client if the web service is available, or true if it is unavailable.
   */
  public static function checkWebService(): SoapClient|false
  {
    try {
      $client = new SoapClient(
        "https://ec.europa.eu/taxation_customs/vies/checkVatService.wsdl",
        [
          'connection_timeout' => 10,
          'exceptions'         => true,
        ]
      );
      return $client;
    } catch (SoapFault $e) {
      return false;
    }
  }

  /**
   * Checks the validity of a VAT number against a web service.
   *
   * @param string|null $country_iso The ISO country code. If null, it will be determined based on the VAT number.
   * @param string $tva_intracom The VAT number to validate.
   * @return bool Returns true if the VAT check fails or the web service is unavailable, false if the VAT check succeeds.
   */
  public static function serviceCheckVat(?string $country_iso, string $tva_intracom): bool
  {
    if (ACCOUNT_TVA_INTRACOM_PRO_VERIFICATION == 'false') {
      return false;
    }

    // Détermination du code ISO
    if (!empty($country_iso)) {
      $result = static::checkIsoCountry($country_iso);
    } else {
      $country_iso = strtoupper(substr(str_replace(' ', '', $tva_intracom), 0, 2));
      $result = static::checkIsoCountry($country_iso);
    }

    // Pays invalide
    if ($result === true) {
      return true;
    }

    // Connexion au web service (une seule fois)
    $client = static::checkWebService();
    if ($client === false) {
      return true; // service indisponible
    }

    // Numéro TVA sans le préfixe pays
    $vatNumber = preg_replace('/^' . preg_quote($country_iso, '/') . '/i', '', str_replace(' ', '', $tva_intracom));

    try {
      $response = $client->checkVat([
        'countryCode' => $country_iso,
        'vatNumber'   => $vatNumber,
      ]);
    } catch (SoapFault $e) {
      $faults = [
        'INVALID_INPUT'     => 'The provided CountryCode is invalid or the VAT number is empty',
        'SERVICE_UNAVAILABLE' => 'The SOAP service is unavailable, try again later',
        'MS_UNAVAILABLE'    => 'The Member State service is unavailable, try again later',
        'TIMEOUT'           => 'The Member State service could not be reached in time',
        'SERVER_BUSY'       => 'The service cannot process your request. Try again later.',
      ];
      // $error_message = $faults[$e->faultstring] ?? $e->faultstring;
      return true; // erreur SOAP = TVA non vérifiable
    }

    // Vérification de la réponse
    if (empty($response) || $response->valid !== true) {
      return true; // numéro invalide
    }

    return false; // numéro valide
  }

  /**
   * Processes the response to extract company information and formats it as a JSON-like string.
   *
   * @param array $response The response data containing company details.
   * @return string A formatted string representing the company's information in a JSON-like structure.
   */
  public static function getInfoCompany($response): string
  {
    $result = '';

    foreach ($response as $key => $prop) {
      $result .= ",\n  \"" . $key . "\": \"" . str_replace('"', '\"', $prop) . "\"";
    }

    $result .= "\n}";

    return $result;
  }


  /**
   * Retrieves company information from the Pappers API.
   *
   * If a SIREN number is provided, the method fetches company details directly.
   * Otherwise, it performs a search by company name to retrieve the SIREN first,
   * then fetches the full company details.
   *
   * Returns false if the API token is not configured.
   * Returns null if the company is not found or the API is unavailable.
   *
   * @param ?string      $name  The company name to search for (used when SIREN is not provided).
   * @param string|null $siren The SIREN number (9 digits). If provided, skips the name search.
   *
   * @return array|bool|null Company details as an associative array, false if token missing, null on failure.
   *
   *
   * // Avec SIREN → recherche directe (1 seul appel API)
   * $info = $this->checkCompanyInfo('Renault', '444513151');
   *
   * // Sans SIREN → recherche par nom (2 appels API)
   * $info = $this->checkCompanyInfo('Renault');
   *
   * // SIREN vide explicitement → aussi par nom
   * $info = $this->checkCompanyInfo('Renault', '');
   *
   */
  public function checkPapersCompanyInfo(?string $name, ?string $siren = null): array|bool|null
  {
    // Check that the Pappers API token is defined and not empty
    if (!defined('PAPPERS_API_TOKEN') || empty(PAPPERS_API_TOKEN)) {
      return false;
    }

    $apiToken = PAPPERS_API_TOKEN;
    $allowedHosts   = ['api.pappers.fr'];

    // Step 1 — SIREN provided: fetch company details directly (single API call)
    if (!empty($siren)) {
      $siren = preg_replace('/\s/', '', $siren);
      $siren = substr($siren, 0, 9);

      $response = HTTP::getResponse(
        [
          'url'        => 'https://api.pappers.fr/v2/entreprise?' . http_build_query([
              'api_token' => $apiToken,
              'siren'     => $siren,
            ]),
          'method'     => 'get',
          'format'     => 'json',
        ],
        $allowedHosts
      );

      if ($response === false || $response === null) {
        return null;
      }

    } else {
      // Step 2 — No SIREN provided: search by company name to retrieve the SIREN
      $search = HTTP::getResponse(
        [
          'url'        => 'https://api.pappers.fr/v2/recherche?' . http_build_query([
              'api_token' => $apiToken,
              'q'         => $name,
              'par_page'  => 1,
            ]),
          'method'     => 'get',
          'format'     => 'json',
        ],
        $allowedHosts
      );

      if ($search === false || $search === null || empty($search['resultats'])) {
        return null;
      }

      // Extract the SIREN from the first search result
      $siren = $search['resultats'][0]['siren'] ?? null;

      if (!$siren) {
        return null;
      }

      // Step 3 — Fetch full company details using the retrieved SIREN
      $response = HTTP::getResponse(
        [
          'url'        => 'https://api.pappers.fr/v2/entreprise?' . http_build_query([
              'api_token' => $apiToken,
              'siren'     => $siren,
            ]),
          'method'     => 'get',
          'format'     => 'json',
        ],
        $allowedHosts
      );

      if ($response === false || $response === null) {
        return null;
      }
    }

    // Step 4 — Extract and return relevant company fields
    return [
      'siren'           => $response['siren']                           ?? null,
      'siret'           => $response['siege']['siret']                  ?? null,
      'name'            => $response['nom_entreprise']                  ?? null,
      'legal_form'      => $response['forme_juridique']                 ?? null,
      'creation_date'   => $response['date_creation']                   ?? null,
      'vat_number'      => $response['numero_tva_intracommunautaire']   ?? null,
      'address'         => $response['siege']['adresse_ligne_1']        ?? null,
      'zip_code'        => $response['siege']['code_postal']            ?? null,
      'city'            => $response['siege']['ville']                  ?? null,
      'country'         => $response['siege']['pays']                   ?? null,
      'status'          => $response['statut']                          ?? null, // 'A' = active, 'C' = closed
      'manager'         => $response['representants'][0]['nom_complet'] ?? null,
      'naf_code'        => $response['code_naf']                        ?? null,
      'naf_label'       => $response['libelle_code_naf']                ?? null,
      'capital'         => $response['capital']                         ?? null,
      'pappers_url'     => 'https://www.pappers.fr/entreprise/' . ($response['nom_url'] ?? $siren),
    ];
  }
}