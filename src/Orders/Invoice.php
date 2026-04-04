<?php
declare(strict_types=1);

// Nemesis 5.0.0 | Phase 15 — E-Commerce | Created: 2026-04-04

namespace Nemesis\Orders;

/**
 * Invoice — generates a plain-text or HTML invoice from an Order.
 *
 * Usage:
 *   $invoice = Invoice::for($order);
 *   echo $invoice->toHtml();
 *   echo $invoice->toText();
 *   $invoice->number();  // e.g. INV-20260404-00001
 */
class Invoice
{
    private static int $seq = 0;

    private int    $id;
    private string $number;
    private int    $issuedAt;
    private array  $meta = [];

    private function __construct(private readonly Order $order)
    {
        $this->id       = ++static::$seq;
        $this->number   = static::generateNumber($this->id);
        $this->issuedAt = time();
    }

    public static function for(Order $order): static
    {
        return new static($order);
    }

    public static function resetSeq(): void { static::$seq = 0; }

    // -------------------------------------------------------------------------
    // Fluent metadata
    // -------------------------------------------------------------------------

    public function meta(string $k, mixed $v): static { $this->meta[$k] = $v; return $this; }

    // -------------------------------------------------------------------------
    // Getters
    // -------------------------------------------------------------------------

    public function getId(): int       { return $this->id; }
    public function number(): string   { return $this->number; }
    public function getOrder(): Order  { return $this->order; }
    public function issuedAt(): int    { return $this->issuedAt; }

    // -------------------------------------------------------------------------
    // Rendering
    // -------------------------------------------------------------------------

    public function toText(): string
    {
        $o     = $this->order;
        $lines = [];
        $sep   = str_repeat('-', 56);

        $lines[] = "INVOICE: {$this->number}";
        $lines[] = "Order:   {$o->getOrderNumber()}";
        $lines[] = "Date:    " . date('Y-m-d H:i', $this->issuedAt);
        $lines[] = "Status:  " . strtoupper($o->getStatus());
        if ($o->getTransactionId()) {
            $lines[] = "Txn:     {$o->getTransactionId()}";
        }
        $lines[] = $sep;
        $lines[] = sprintf("%-30s %6s %10s %10s", 'Item', 'Qty', 'Unit', 'Total');
        $lines[] = $sep;

        foreach ($o->getItems() as $item) {
            $lines[] = sprintf(
                "%-30s %6d %10s %10s",
                mb_substr($item->getProductName(), 0, 30),
                $item->getQty(),
                $item->unitPrice(),
                $item->subtotal(),
            );
        }

        $lines[] = $sep;
        $lines[] = sprintf("%48s %8s", 'Subtotal:', $o->grandTotal());

        if ($o->discountCents() > 0) {
            $disc = number_format($o->discountCents() / 100, 2);
            $lines[] = sprintf("%48s %8s", 'Discount:', "-{$disc}");
        }
        if ($o->shippingCents() > 0) {
            $ship = number_format($o->shippingCents() / 100, 2);
            $lines[] = sprintf("%48s %8s", 'Shipping:', $ship);
        }
        if ($o->taxCents() > 0) {
            $tax = number_format($o->taxCents() / 100, 2);
            $lines[] = sprintf("%48s %8s", 'Tax:', $tax);
        }

        $lines[] = $sep;
        $lines[] = sprintf("%48s %8s", 'TOTAL (' . $o->getCurrency() . '):', $o->grandTotal());
        $lines[] = $sep;

        if ($o->getBillingAddress()) {
            $lines[] = "Bill To: " . $this->formatAddress($o->getBillingAddress());
        }
        if ($o->getShippingAddress()) {
            $lines[] = "Ship To: " . $this->formatAddress($o->getShippingAddress());
        }

        return implode("\n", $lines);
    }

    public function toHtml(): string
    {
        $o     = $this->order;
        $esc   = fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
        $rows  = '';

        foreach ($o->getItems() as $item) {
            $rows .= sprintf(
                '<tr><td>%s</td><td>%s</td><td style="text-align:right">%s</td><td style="text-align:right">%s</td></tr>',
                $esc($item->getProductName()),
                $esc($item->getQty()),
                $esc($item->unitPrice()),
                $esc($item->subtotal()),
            );
        }

        $totalsHtml = '<tr><td colspan="3" style="text-align:right"><strong>Subtotal</strong></td>'
            . '<td style="text-align:right">' . $esc($o->grandTotal()) . '</td></tr>';

        if ($o->discountCents() > 0) {
            $disc = number_format($o->discountCents() / 100, 2);
            $totalsHtml .= '<tr><td colspan="3" style="text-align:right">Discount</td>'
                . '<td style="text-align:right">-' . $esc($disc) . '</td></tr>';
        }
        if ($o->shippingCents() > 0) {
            $ship = number_format($o->shippingCents() / 100, 2);
            $totalsHtml .= '<tr><td colspan="3" style="text-align:right">Shipping</td>'
                . '<td style="text-align:right">' . $esc($ship) . '</td></tr>';
        }
        if ($o->taxCents() > 0) {
            $tax = number_format($o->taxCents() / 100, 2);
            $totalsHtml .= '<tr><td colspan="3" style="text-align:right">Tax</td>'
                . '<td style="text-align:right">' . $esc($tax) . '</td></tr>';
        }

        $totalsHtml .= '<tr><td colspan="3" style="text-align:right"><strong>Total (' . $esc($o->getCurrency()) . ')</strong></td>'
            . '<td style="text-align:right"><strong>' . $esc($o->grandTotal()) . '</strong></td></tr>';

        $txnHtml = $o->getTransactionId()
            ? '<p><strong>Transaction ID:</strong> ' . $esc($o->getTransactionId()) . '</p>' : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Invoice {$this->number}</title>
<style>
  body{font-family:Arial,sans-serif;font-size:14px;margin:40px}
  table{width:100%;border-collapse:collapse;margin-top:16px}
  th,td{border:1px solid #ccc;padding:8px}
  th{background:#f5f5f5}
  .invoice-header{display:flex;justify-content:space-between;margin-bottom:24px}
</style>
</head>
<body>
<div class="invoice-header">
  <div>
    <h2>INVOICE</h2>
    <p><strong>Invoice #:</strong> {$this->number}<br>
    <strong>Order #:</strong> {$esc($o->getOrderNumber())}<br>
    <strong>Date:</strong> {$esc(date('Y-m-d H:i', $this->issuedAt))}<br>
    <strong>Status:</strong> {$esc(strtoupper($o->getStatus()))}</p>
    {$txnHtml}
  </div>
</div>
<table>
  <thead><tr><th>Item</th><th>Qty</th><th>Unit Price</th><th>Subtotal</th></tr></thead>
  <tbody>{$rows}{$totalsHtml}</tbody>
</table>
</body>
</html>
HTML;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatAddress(array $addr): string
    {
        return implode(', ', array_filter([
            $addr['name']    ?? '',
            $addr['line1']   ?? '',
            $addr['city']    ?? '',
            $addr['state']   ?? '',
            $addr['zip']     ?? '',
            $addr['country'] ?? '',
        ]));
    }

    private static function generateNumber(int $id): string
    {
        return 'INV-' . date('Ymd') . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    public function toArray(): array
    {
        return [
            'id'         => $this->id,
            'number'     => $this->number,
            'issued_at'  => $this->issuedAt,
            'order'      => $this->order->toArray(),
            'meta'       => $this->meta,
        ];
    }
}
