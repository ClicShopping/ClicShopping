<?php
/**
 * Unified entry point for PDF invoice and packingslip rendering.
 *
 * This file provides a simple interface to render PDF documents without
 * duplicating code across multiple files (OrderInvoice, invoice_batch,
 * packingslip, packingslip_batch).
 *
 * Usage:
 *   // Invoice single order
 *   $result = PDF_Invoice_Caller::render('invoice', $pdf, $order, $extra);
 *
 *   // Packingslip batch
 *   $renderer = new PDF_Invoice_Caller();
 *   $renderer->batchRender('packingslip', $pdf, $orders_array, $options);
 */

use ClicShopping\OM\Registry;
  use ClicShopping\Sites\Common\PDF_Invoice;

  class PDF_Invoice_Caller
{
    /**
     * Render a single invoice or packingslip PDF.
     *
     * @param string   $type      'invoice' or 'packingslip'
     * @param \FPDF    $pdf       FPDF instance (must be new FPDF(), not pdfInvoice)
     * @param object   $order     Order object with ->info, ->customer, ->delivery, ->billing, ->products, ->totals
     * @param array    $extra     Extra data: oID, customers_id, order_status_invoice_id,
     *                            orders_status_invoice_name, date_added, date_purchased, payment_method
     *
     * @return void
     */
    public static function render(string $type, \FPDF $pdf, object $order, array $extra): void
    {
        // Validate type
        if (!in_array($type, ['invoice', 'packingslip'])) {
            throw new InvalidArgumentException('Invalid PDF type. Must be "invoice" or "packingslip".');
        }

        // Include the renderer class if not already loaded
        if (!class_exists('PDF_Invoice')) {
            require_once __DIR__ . '/PDF_Invoice.php';
        }

        $renderer = new PDF_Invoice($type);
        $renderer->render($pdf, $order, $extra);
    }

    /**
     * Render batch packingslips (multiple orders in one PDF).
     *
     * @param string   $type       'packingslip' only (invoices are single-order)
     * @param \FPDF    $pdf        FPDF instance
     * @param array    $orders     Array of order objects
     * @param array    $options    Additional options (not used currently)
     *
     * @return void
     */
    public static function renderBatch(string $type, \FPDF $pdf, array $orders, array $options = []): void
    {
        if ($type !== 'packingslip') {
            throw new InvalidArgumentException('Batch rendering only supported for packingslip.');
        }

        if (!class_exists('PDF_Invoice')) {
            require_once __DIR__ . '/PDF_Invoice.php';
        }

        // For batch, we create a fresh renderer each time (page setup per order)
        foreach ($orders as $order) {
            $renderer = new PDF_Invoice($type);
            
            // Extract extra data from order or options
            $extra = [
                'oID' => $options['oID'] ?? 0,
                'customers_id' => $options['customers_id'] ?? 0,
                'order_status_invoice_id' => $options['order_status_invoice_id'] ?? 0,
                'orders_status_invoice_name' => $options['orders_status_invoice_name'] ?? '',
                'date_added' => $options['date_added'] ?? '',
                'date_purchased' => $options['date_purchased'] ?? '',
                'payment_method' => $options['payment_method'] ?? '',
            ];

            $renderer->render($pdf, $order, $extra);
        }
    }

    /**
     * Get a list of all supported PDF types.
     *
     * @return array
     */
    public static function getSupportedTypes(): array
    {
        return ['invoice', 'packingslip'];
    }
}
