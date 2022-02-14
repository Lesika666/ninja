<?php

namespace App\Services;

use Auth;
use Utils;

/**
 * Class InvoiceItemService.
 */
class InvoiceItemService extends BaseService
{
    /**
     * Addition (Dylan) : Return categories from an invoice collections with a total value for each categories
     *
     * @param $invoices
     * @return array
     */
    public function getInvoicesItemCategories($invoices): array
    {
        $categories = [];

        foreach ($invoices as $invoice) {
            $items = $invoice->invoice_items;
            foreach ($items as $item) {
                $total = 0;

                $category = !empty($item->product_key) ? explode(' ', $item->product_key)[0] : "AUTRE";
                if (!array_key_exists($category, $categories)) {
                    $categories[$category] = ['value' => 0, 'clients' => []];
                }

                $invoiceItemCost = Utils::roundSignificant(Utils::parseFloat($item->cost));
                $invoiceItemQty = Utils::roundSignificant(Utils::parseFloat($item->qty));

                $lineTotal = $invoiceItemCost * $invoiceItemQty;

                $total += $lineTotal;
                $total -= empty($item->discount) ? 0 : round(Utils::parseFloat($item->discount), 4);
                $total -= empty($invoice->discount) ? 0 : round(Utils::parseFloat($invoice->discount), 4);
                $total += round($lineTotal * Utils::parseFloat($item->tax_rate1) / 100, 2);
                $total += round($lineTotal * Utils::parseFloat($item->tax_rate2) / 100, 2);
                $total += round($lineTotal * Utils::parseFloat($invoice->tax_rate1) / 100, 2);
                $total += round($lineTotal * Utils::parseFloat($invoice->tax_rate2) / 100, 2);
                $categories[$category]['value'] += round($total, 2);
//                in_array($invoice->client_id, $categories[$category]['clients']) ?: $categories[$category]['clients'][] = $invoice->client_id;
                in_array($invoice->client_id, $categories[$category]['clients']) ?: $categories[$category]['clients'][] = $invoice->client->id;
            }
        }
        return $categories;
    }
}
