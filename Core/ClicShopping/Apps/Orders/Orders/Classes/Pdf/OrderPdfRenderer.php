<?php
/**
 * Copyright (c) 2008–2026 Loic Richard
 *
 * Licensed under AGPLv3 or commercial license.
 * See LICENSE file.
 */

namespace ClicShopping\Apps\Orders\Orders\Classes\Pdf;

use ClicShopping\Apps\Orders\Orders\Classes\ClicShoppingAdmin\OrderAdmin;
use ClicShopping\OM\CLICSHOPPING;
use ClicShopping\OM\HTTP;
use ClicShopping\OM\Registry;

/**
 * Facade that orchestrates PDF generation for orders.
 *
 * Replaces the duplicated logic previously living in
 *  - Sites/Shop/Pages/Account/Actions/OrderInvoice.php
 *  - Apps/Orders/.../Sites/ClicShoppingAdmin/Pages/Home/templates/invoice.php
 *  - templates/invoice_batch.php
 *  - templates/packingslip.php
 *  - templates/packingslip_batch.php
 *
 * Provides two API styles:
 *  - One-shot:  ::invoice($oID, $order, $context) / ::packingSlip(...)
 *  - Batch:     ::createInvoicePdf($context) / ::createPackingSlipPdf($context)
 *               + ::resolveInvoiceStatus($oID) + loop + ->Output()
 */
class OrderPdfRenderer
{
  public const CONTEXT_SHOP = 'shop';
  public const CONTEXT_ADMIN = 'admin';

  /**
   * Render and output one order invoice PDF (one-shot).
   */
  public static function invoice(int $oID, object $order, string $context = self::CONTEXT_SHOP): void
  {
    $pdf = self::createInvoicePdf($context);

    [$statusId, $title, $invoiceDate] = self::resolveInvoiceStatus($oID);

    $pdf->addInvoicePage(
      oID: $oID,
      order: $order,
      billingAddress: self::resolveBillingAddress($order, $context),
      deliveryAddress: $order->delivery,
      invoiceTitle: $title,
      invoiceStatusId: $statusId,
      invoiceDate: $invoiceDate
    );

    $pdf->Output();
  }

  /**
   * Render and output one packing-slip PDF (one-shot).
   */
  public static function packingSlip(int $oID, object $order, string $context = self::CONTEXT_ADMIN): void
  {
    $pdf = self::createPackingSlipPdf($context);

    [$statusId, $title, $invoiceDate] = self::resolveInvoiceStatus($oID);

    $pdf->addPackingSlipPage(
      oID: $oID,
      order: $order,
      billingAddress: self::resolveBillingAddress($order, $context),
      deliveryAddress: $order->delivery,
      invoiceTitle: $title,
      invoiceStatusId: $statusId,
      invoiceDate: $invoiceDate
    );

    $pdf->Output();
  }

  /**
   * Build a configured InvoicePdf ready for batch use.
   * Caller adds pages then calls ->Output().
   */
  public static function createInvoicePdf(string $context): InvoicePdf
  {
    self::bootFpdf();

    $pdf = new InvoicePdf();
    $pdf->setDefResolver(self::defResolver($context));
    $pdf->setLogoUrl(self::resolveLogoUrl($context));
    $pdf->setRenderHeader(self::shouldRenderHeader($context));
    $pdf->setRenderFooter(self::shouldRenderFooter());
    $pdf->setDrawSeparatorLine(self::shouldRenderFooter());
    $pdf->setDrawTitleBox($context === self::CONTEXT_ADMIN);

    return $pdf;
  }

  /**
   * Build a configured PackingSlipPdf ready for batch use.
   */
  public static function createPackingSlipPdf(string $context): PackingSlipPdf
  {
    self::bootFpdf();

    $pdf = new PackingSlipPdf();
    $pdf->setDefResolver(self::defResolver($context));
    $pdf->setLogoUrl(self::resolveLogoUrl($context));
    $pdf->setRenderHeader(self::shouldRenderHeader($context));
    $pdf->setRenderFooter(self::shouldRenderFooter());
    $pdf->setDrawSeparatorLine(self::shouldRenderFooter());

    return $pdf;
  }

  /**
   * Pick the billing address (admin OrderAdmin uses ->billing,
   * shop Order uses ->customer).
   */
  public static function resolveBillingAddress(object $order, string $context): array
  {
    if ($context === self::CONTEXT_ADMIN && isset($order->billing)) {
      return $order->billing;
    }
    return $order->customer;
  }

  /**
   * Look up the latest orders_status_history row for the order
   * and return [invoiceStatusId, localisedTitle, date_added].
   *
   * @return array{0:int,1:string,2:string}
   */
  public static function resolveInvoiceStatus(int $oID): array
  {
    $db = Registry::get('Db');
    $language = Registry::get('Language');

    $QordersHistory = $db->prepare('select orders_status_id,
                                                 date_added,
                                                 customer_notified,
                                                 orders_status_invoice_id,
                                                 comments
                                          from :table_orders_status_history
                                          where orders_id = :orders_id
                                          order by date_added desc
                                          limit 1
                                         ');
    $QordersHistory->bindInt(':orders_id', $oID);
    $QordersHistory->execute();

    $statusId = $QordersHistory->valueInt('orders_status_invoice_id');
    $dateAdded = (string)$QordersHistory->value('date_added');

    $QOrdersStatusInvoice = $db->prepare('select orders_status_invoice_id,
                                                   orders_status_invoice_name,
                                                   language_id
                                            from :table_orders_status_invoice
                                            where orders_status_invoice_id = :orders_status_invoice_id
                                            and language_id = :language_id
                                           ');
    $QOrdersStatusInvoice->bindInt(':orders_status_invoice_id', $statusId);
    $QOrdersStatusInvoice->bindInt(':language_id', (int)$language->getId());
    $QOrdersStatusInvoice->execute();

    $title = (string)$QOrdersStatusInvoice->value('orders_status_invoice_name');

    return [$statusId, $title, $dateAdded];
  }

  private static function bootFpdf(): void
  {
    if (!defined('FPDF_FONTPATH')) {
      define('FPDF_FONTPATH', CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/font/');
    }
    if (!class_exists('FPDF', false)) {
      require_once CLICSHOPPING::BASE_DIR . 'External/vendor/setasign/fpdf/fpdf.php';
    }
  }

  private static function defResolver(string $context): \Closure
  {
    if ($context === self::CONTEXT_ADMIN) {
      $app = Registry::get('Orders');
      return static fn(string $key, ?array $values = null) => $app->getDef($key, $values);
    }

    return static fn(string $key, ?array $values = null) => CLICSHOPPING::getDef($key, $values);
  }

  private static function resolveLogoUrl(string $context): string|false
  {
    if ($context === self::CONTEXT_ADMIN) {
      return OrderAdmin::getOrderPdfInvoiceLogo();
    }

    $tpl = Registry::get('Template');
    $path = CLICSHOPPING::getConfig('dir_root', 'Shop') . $tpl->getDirectoryTemplateImages() . 'logos/invoice/' . INVOICE_LOGO;

    if (!is_file($path)) {
      return false;
    }

    return HTTP::getShopUrlDomain() . $tpl->getDirectoryTemplateImages() . 'logos/invoice/' . INVOICE_LOGO;
  }

  private static function shouldRenderHeader(string $context): bool
  {
    if ($context === self::CONTEXT_ADMIN) {
      return defined('DISPLAY_INVOICE_HEADER') && DISPLAY_INVOICE_HEADER == 'false';
    }
    return true;
  }

  private static function shouldRenderFooter(): bool
  {
    return defined('DISPLAY_INVOICE_FOOTER') && DISPLAY_INVOICE_FOOTER == 'false';
  }
}
