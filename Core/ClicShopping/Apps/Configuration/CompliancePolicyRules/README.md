# CompliancePolicyRules — ajouter un pays à la facture

Une facture relève d'**une seule** juridiction fiscale. Le corps commun (`CPR`) s'imprime toujours ;
le **bloc pays** du pied de page vient du module pays **actif** (`…_{CODE}_STATUS = True`).

| Pays actifs | Facture | Admin |
|---|---|---|
| 0 | aucun bloc pays | — |
| 1 | le bloc de ce pays | — |
| 2 ou plus | rendu inchangé (choix d'avant, drapeau double taxe) | alerte `alert_country_conflict` sur l'écran de configuration |

`CPR_STATUS = False` coupe tout bloc pays. Pour éteindre un pays : son `_STATUS` à `False` —
**ne pas le désinstaller**, la réinstallation remet ses paramètres au défaut.

## Ce qui est déjà en place (rien à toucher)

- `AbstractOrderPdf::Footer()` appelle `Hooks->call('Orders', 'InvoicePdf', ['pdf' => $this], 'renderFooter')`
  (et `renderHeader` dans `Header()`).
- Les hooks `Module/Hooks/{Shop,ClicShoppingAdmin}/Orders/InvoicePdf.php` sont déclarés **sur les deux
  sites** dans `clicshopping.json` — la facture se génère côté admin ET côté client.
- `Classes/Shared/InvoiceFooterRenderer` trouve le pays actif (tout répertoire de
  `Module/ClicShoppingAdmin/Config/` sauf `CPR`) et appelle `Classes/Shared/InvoiceFooter/{CODE}::render()`.

Un nouveau pays ne demande donc **ni nouveau hook, ni ligne dans `Core/`**.

## Ajouter un pays — exemple fictif `NLD`

1. **Module de configuration** — copier `Module/ClicShoppingAdmin/Config/CAD/` en `NLD/` :
   - `NLD.php` : classe `NLD`, `$is_uninstallable = true` ;
   - `Params/status.php` avec **`$default = 'False'`** : installer le module ne doit pas créer de conflit ;
   - `Params/sort_order.php` + un fichier par donnée légale (ex. `kvk_number.php`). Un secret
     (clé d'API, mot de passe) se déclare dans `ConfigParamAbstract::ENCRYPTED_KEYS`.
2. **Langues** (EN + FR, clés identiques) : `languages/{english,french}/Module/ClicShoppingAdmin/Config/NLD/`
   (titre du module + un `.txt` par paramètre).
3. **Accesseur** — une méthode gardée par `defined()` dans `Classes/Shared/CompliancePolicyRules.php`
   (ex. `displayKvkNumber()`). Jamais de constante lue à nu dans le rendu PDF.
4. **Bloc de facture** — `Classes/Shared/InvoiceFooter/NLD.php`, sur le modèle de `FRE.php` :
   ```php
   class NLD
   {
     public static function render(AbstractOrderPdf $pdf): void
     {
       $rules = Registry::get('CompliancePolicyRules');
       $pdf->SetY(-25);
       $pdf->SetFont('Arial', '', 8);
       $pdf->SetTextColor(...$pdf->rgb());
       $pdf->Cell(0, 10, PDF::enc($pdf->def('entry_info_societe_nld', ['kvk' => $rules->displayKvkNumber()])), 0, 0, 'C');
     }
   }
   ```
   Positions réservées au bloc pays : `SetY(-25)` et `SetY(-20)`. Les libellés passent par `$pdf->def()` :
   ajouter les clés dans `Apps/Orders/Orders/languages/{english,french}/Sites/ClicShoppingAdmin/invoice.txt`
   **et** `packingslip.txt` (le pied de page sert aux deux documents).
5. **Purger** après tout ajout de `.txt` : `php clear_all_caches_complete.php` sur les deux arbres.

## QR code / code-barres fiscal

FPDF accepte une image construite en mémoire, sans fichier temporaire :
`$pdf->Image('data://image/png;base64,' . base64_encode($png), $x, $y, $w, $h, 'PNG');`
Dans le pied de page : depuis `InvoiceFooter/{CODE}::render()`. Dans l'en-tête : ajouter une méthode
`renderHeader(array $params)` aux deux hooks `InvoicePdf` (même garde `CPR_STATUS`) qui délègue au pays actif.
Un code-barres (Code128/EAN13) tient en quelques dizaines de lignes ; un QR demande une dépendance
Composer, à n'ajouter que le jour où un pays l'exige.

## Vérifier

- `php unit_test/2026_10_01/invoice_country_selector_test.php` — ajouter un cas « `NLD` seul → bloc NLD ».
- Les factures des autres pays doivent rester **identiques à l'octet** (hors `/CreationDate`).
