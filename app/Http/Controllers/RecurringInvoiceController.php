<?php

namespace App\Http\Controllers;

use App\Models\Frequency;
use App\Models\Invoice;
use App\Ninja\Datatables\RecurringInvoiceDatatable;
use App\Ninja\Repositories\InvoiceRepository;
use Carbon\Carbon;
use Carbon\CarbonInterval;

/**
 * Class RecurringInvoiceController.
 */
class RecurringInvoiceController extends BaseController
{
    /**
     * @var InvoiceRepository
     */
    protected $invoiceRepo;

    /**
     * RecurringInvoiceController constructor.
     *
     * @param InvoiceRepository $invoiceRepo
     */
    public function __construct(InvoiceRepository $invoiceRepo)
    {
        //parent::__construct();

        $this->invoiceRepo = $invoiceRepo;
    }

    /**
     * @return mixed
     */
    public function index()
    {
        // Addition (Dylan) : Added stats data to recurring invoices table
        $current_year = Carbon::now()->year;

        $estimatedInvoices = Invoice::recurringInvoiceStatsData($current_year);
        $previousInvoices = Invoice::recurringInvoiceStatsData($current_year-1);

        $categories = [];
        foreach ($estimatedInvoices as $month) {
            foreach ($month['categories'] as $key => $category) {
                if (!array_key_exists($key, $categories)) $categories[$key] = 0;
            }
        }
        foreach ($previousInvoices as $month) {
            foreach ($month['categories'] as $key => $category) {
                if (!array_key_exists($key, $categories)) $categories[$key] = 0;
            }
        }

        $data = [
            'title' => trans('texts.recurring_invoices'),
            'entityType' => ENTITY_RECURRING_INVOICE,
            'datatable' => new RecurringInvoiceDatatable(),
            'estimatedInvoices' => $estimatedInvoices,
            'previousInvoices' => $previousInvoices,
            'categories' => $categories
        ];

        return response()->view('list_wrapper', $data);
    }
}
