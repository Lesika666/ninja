<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Ninja\Repositories\ExpenseRepository;
use App\Ninja\Repositories\InvoiceRepository;
use Auth;
use DateTime;
use phpDocumentor\Reflection\Types\Self_;
use stdClass;

/**
 * Class ChartService.
 */
class ChartService extends BaseService
{
    /**
     * @var InvoiceRepository
     */
    private $invoiceRepository;

    /**
     * @var ExpenseRepository
     */
    private $expenseRepository;

    /**
     * @var InvoiceItemService
     */
    private $invoiceItemService;

    public function __construct(
        InvoiceRepository $invoiceRepository,
        ExpenseRepository $expenseRepository,
        InvoiceItemService $invoiceItemService
    )
    {
        $this->invoiceRepository  = $invoiceRepository;
        $this->expenseRepository  = $expenseRepository;
        $this->invoiceItemService = $invoiceItemService;
    }

    /**
     * Addition (Dylan) : Get chartData
     *
     * @param $startDate
     * @param $endDate
     * @return stdClass
     */
    public function getChartData($startDate, $endDate): stdClass
    {
        $chartData  = new stdClass();

        // current_year in $chartData
        $chartData = $this->getTotals(
            $startDate,
            $endDate,
            $chartData
        );

        // If diff between dates is inferior to a year
        // previous_year in $chartData
        $minusYearStartDate = new DateTime($startDate->format('Y-m-d'));
        $minusYearEndDate = new DateTime($endDate->format('Y-m-d'));
        $minusYearStartDate->modify('-1 year');
        $minusYearEndDate->modify('-1 year');
        if(!date_diff($startDate, $endDate)->y) {
            $chartData = $this->getTotals(
                $minusYearStartDate,
                $minusYearEndDate,
                $chartData,
                true
            );
        }

        $invoiceRepository = $this->invoiceRepository;
        $expenseRepository = $this->expenseRepository;

        // total_years in $chartData && active clients
        $invoiceTotal = [];
        $expenseTotal = [];
        $activeClients = [];
        $total_years_date = new DateTime($endDate->format('Y-m-d'));
        for ($i = 0; $i < 10; $i++) {
            $year = $total_years_date->format('Y');
            $invoiceTotal[$year] = $invoiceRepository->getYearlyInvoiceTotal($year);
            $expenseTotal[$year] = $expenseRepository->getYearlyExpenseTotal($year);
            $activeClients[$year] = count($invoiceRepository->getAllYearlyClients($year));
//            $activeClients[$year] = $invoiceRepository->getAllYearlyClients($year);

            $total_years_date->modify('-1 year');
        }
        $chartData->total_years = ["invoices" => $invoiceTotal, "expenses" => $expenseTotal];
        $chartData->clients = $activeClients;

        return $chartData;
    }

    /**
     * Addition (Dylan) : Format chartData
     */
    private function getTotals($startDate, $endDate, $chartData, $previous_year = false) {
        $totals     = [];
        $categories = [];

        $totals['invoiced']  = [];
        $totals['payments']  = [];
        $totals['expenses']  = [];
        $totals['unpaid'] = [];
        $totals['realMargin'] = [];
        $totals['potentialMargin'] = [];

        $invoiceRepository  = $this->invoiceRepository;
        $expenseRepository  = $this->expenseRepository;

        $labels = $this->getLabels($startDate, $endDate);
        $totals['labels'] = $labels;

        foreach ($labels as $start_of_month) {
            $end_of_month = date_create($start_of_month)->modify('last day of this month')->format('Y-m-d');

            // Get all invoices for the current month
            $invoices   = $invoiceRepository->findAllBetweenDates($start_of_month, $end_of_month);
            $recurring  = $invoiceRepository->findAllRecurringBetweenDates($start_of_month, $end_of_month);
            $totals     = $this->calcInvoices($totals, $invoices, $recurring, $start_of_month, $invoiceRepository);
            $categories = $this->calcCategories($categories, $invoices);
            $expenses   = $expenseRepository->findAllBetweenDates($start_of_month, $end_of_month);
            $totals     = $this->calcExpenses($totals, $expenses);
            $totals     = $this->calcUnpaid($totals, $invoices); // Unpaid (+ late and send)
            $totals     = $this->calcMargin($totals);
        }

        $results = new stdClass();
        $results->labels     = $labels;
        $results->totals     = $totals;
        $results->categories = $categories;

        $previous_year ? $chartData->previous_year = $results : $chartData->current_year = $results;

        return $chartData;
    }

    /**
     * Get all months names between 2 dates
     *
     * @param $startDate
     * @param $endDate
     * @return array
     */
    private function getLabels($startDate, $endDate): array
    {
        $labels = [];

        // Get first day of start date
        $startDate = $startDate->modify('first day of this month');

        // Fill labels array with months between start date / end date
        $label_date = $startDate;
        while ($label_date < $endDate) {
            $current_month = $label_date->format('Y-m-d');
            if(!in_array($current_month, $labels)) $labels[] = $current_month;
            $label_date = date_create(date('Y-m-d', strtotime("first day of +1 month", strtotime($current_month))));
        }

        return $labels;
    }

    /**
     * Calc invoices totals between 2 dates
     *
     * @param $totals
     * @param $invoices
     * @return array
     */
    private function calcInvoices($totals, $invoices, $recurring, $start_of_month, $invoiceRepository): array
    {
        $invoiced_total = 0;
        $payments_total = 0;
        $newClients_total = 0;
        $oldClients_total = 0;
        $newClients_list = [];
        $activeClients_list = [];

        foreach ($invoices as $invoice) {
            $invoiced_total += $invoice->amount;
            !in_array($invoice->client_id, $activeClients_list) ? $activeClients_list[] = $invoice->client_id : '';

            $this->isClientNew($invoiceRepository, $invoice->client_id, $start_of_month) ?
                $newClients_total += $invoice->amount :
                $oldClients_total += $invoice->amount;
            if (
                $this->isClientNew($invoiceRepository, $invoice->client_id, $start_of_month, 'monthly') &&
                !in_array($invoice->client_id, $newClients_list)
            ) { $newClients_list[] = $invoice->client_id; }

            $invoice_payments = $invoice->payments;
            foreach ($invoice_payments as $payment) {
                $payments_total += $payment->amount;
            }
        }

        $recurring_total = 0;

        foreach ($recurring as $invoice) {
            $recurring_total += $invoice->amount;
        }

        $totals['invoiced'][]  = $invoiced_total;
        $totals['recurringAmount'][] = $recurring_total;
        $totals['noRecurringAmount'][] = $invoiced_total - $recurring_total;
        $totals['newClients'][] = $newClients_total;
        $totals['nbNewClients'][] = count($newClients_list);
        $totals['nbActiveClients'][] = count($activeClients_list);
        $totals['oldClients'][] = $oldClients_total;
        $totals['payments'][]  = $payments_total;

        return $totals;
    }

    private function isClientNew($invoiceRepository, $client_id, $start_of_month, $range = 'yearly') {
        $first_invoice_date = new DateTime($invoiceRepository->getClientFirstInvoiceDate($client_id));

        if ($range == 'yearly') {
            $start_of_month = new DateTime($start_of_month);
            $interval = $first_invoice_date->diff($start_of_month);
            return $interval->y < 1;

        } else if ($range == 'monthly') {
            $first_invoice_date = $first_invoice_date->format('Y-m');

            $current_date = new DateTime($start_of_month);
            $current_date = $current_date->format('Y-m');

            return $first_invoice_date === $current_date;
        }
    }

    /**
     * Calc invoices categories between 2 dates
     *
     * @param $categories
     * @param $invoices
     * @return array
     */
    private function calcCategories($categories, $invoices): array
    {
        $invoiceItemService = $this->invoiceItemService;

        $month_categories = $invoiceItemService->getInvoicesItemCategories($invoices);
        foreach ($month_categories as $key => $value) {
            if (!array_key_exists($key, $categories)) {
                $categories[$key]['value'] = $value['value'];
                $categories[$key]['clients'] = $value['clients'];
            }
            else {
                $categories[$key]['value'] += $value['value'];
                foreach ($value['clients'] as $client) {
                    !in_array($client, $categories[$key]['clients']) ? $categories[$key]['clients'][] = $client : "";
                }
            }
        }

        return $categories;
    }

    /**
     * Calc expenses totals between 2 dates
     *
     * @param $totals
     * @param $expenses
     * @return array
     */
    private function calcExpenses($totals, $expenses): array
    {
        $expenses_total = 0;

        foreach ($expenses as $expense) {
            $expenses_total += $expense->amount;
        }

        $totals['expenses'][] = $expenses_total;

        return $totals;
    }

    /**
     * Calc unpaid invoices
     *
     * @param $totals
     * @param $expenses
     * @return array
     */
    private function calcUnpaid($totals, $invoices): array
    {
        $unpaid = [
            'value' => 0,
            'invoices' => [],
            'awaiting' => [
                'value' => 0,
                'count' => 0
            ],
            'late' => [
                'value' => 0,
                'count' => 0
            ],
            'very_late' => [
                'value' => 0,
                'count' => 0
            ]
        ];

        foreach ($invoices as $invoice) {
            if ($invoice->balance != 0) {
                $unpaid['value'] += $invoice->balance;
                $today = date_create(date("Y-m-d"));
                $due_date = $invoice->due_date ? date_create($invoice->due_date) : "";

                // Filter in which category to add invoice balance
                if (!$due_date) {
                    $unpaid['very_late']['value'] += $invoice->balance;
                    $unpaid['very_late']['count'] += 1;
                } else {
                    $diff = abs(strtotime($today->format("Y-m-d")) - strtotime($due_date->format("Y-m-d")));
                    $diff = $diff/60/60/24/30; // Get months diff
                    if ($today < $due_date) {
                        $unpaid['awaiting']['value'] += $invoice->balance;
                        $unpaid['awaiting']['count'] += 1;
                    } else if ($diff <= 6) { // late < 6 months
                        $unpaid['late']['value'] += $invoice->balance;
                        $unpaid['late']['count'] += 1;
                    } else { // very_late > 6 months
                        $unpaid['very_late']['value'] += $invoice->balance;
                        $unpaid['very_late']['count'] += 1;
                    }
                }

                $unpaidInvoice = new stdClass();
                $unpaidInvoice->id = $invoice->id;
                $unpaidInvoice->public_id = $invoice->public_id;
                $unpaidInvoice->invoice_number = $invoice->invoice_number;
                $unpaidInvoice->balance = $invoice->balance;
                $unpaidInvoice->invoice_date = $invoice->invoice_date;

                $unpaid['invoices'][] = $unpaidInvoice;
            }
        }
        $totals['unpaid'][] = $unpaid;

        return $totals;
    }

    /**
     * Calc margin
     *
     * @param $totals
     * @return array
     */
    private function calcMargin($totals): array
    {
        end($totals['payments']);
        $key = key($totals['payments']);
        $totals['realMargin'][] = $totals['payments'][$key] - $totals['expenses'][$key];

        end($totals['invoiced']);
        $key = key($totals['invoiced']);
        $totals['potentialMargin'][] = $totals['invoiced'][$key] - $totals['expenses'][$key];

        return $totals;
    }

    /**
     * Calc totals results
     *
     * @param $chartData
     * @return stdClass
     */
    public function calcTotals($chartData): stdClass
    {
        $totals = new stdClass();
        $totals->current_year = $chartData->current_year->totals;
        if(property_exists($chartData, 'previous_year')) {
            $totals->previous_year = $chartData->previous_year->totals;
        }

        return $totals;
    }

    /**
     * Return overviewChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getOverviewChart($chartData): stdClass
    {
        $entities = [ENTITY_PAYMENT, ENTITY_INVOICE, ENTITY_EXPENSE];
        $datasets = [];

        foreach ($entities as $entity) {
            if ($entity == 'expense') {
                $data = $chartData->current_year->totals['expenses'];
                $label = trans("texts.expenses");
                $color = "70,70,70";
                $backgroundColor = "rgb({$color})";
            } else if ($entity == 'payment') {
                $data = $chartData->current_year->totals['payments'];
                $label = trans("texts.payments");
                $color = "80,157,235";
                $backgroundColor = "rgba({$color}, 1)";
            } else if ($entity == 'invoice') {
                $combined = [];
                foreach ($chartData->current_year->totals['invoiced'] as $key => $value) {
                    $combined[$key] = $chartData->current_year->totals['invoiced'][$key] - $chartData->current_year->totals['payments'][$key];
                }
                $data = $combined;
                $label = trans('texts.left_to_pay');
                $color = "80,157,235";
                $backgroundColor = "rgba({$color}, 0.5)";
            }

            $dataset = new stdClass();
            $dataset->data = $data;
            $dataset->label = $label;
            $dataset->type = $entity == 'expense' ? 'line' : 'bar';
            $dataset->stack = $entity == 'expense' ? 'lign-stack' : 'bar-stack';
            $dataset->order = $entity == 'expense' ? 1 : 2;
            $dataset->backgroundColor = $backgroundColor;
            if ($entity == 'expense') {
                $dataset->tension = 0.3;
                $dataset->borderColor = "rgba({$color}, 1)";
                $dataset->borderWidth = 2;
                $dataset->pointBackgroundColor = "rgb(70,70,70)";
                $dataset->pointBorderColor = "rgba({$color}, 1)";
                $dataset->pointBorderWidth = 2;
                $dataset->pointHoverBorderWidth = 2;
                $dataset->pointHoverRadius = 5;
            }
            $datasets[] = $dataset;
        }

        $data = new stdClass();
        $data->labels = $chartData->current_year->labels;
        $data->datasets = $datasets;

        return $data;
    }

    /**
     * Return invoicesChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getInvoicesChart($chartData): stdClass
    {
        $entities = [ENTITY_INVOICE];
        if(property_exists($chartData, 'previous_year')) {
            $entities[] = 'previous_year';
        }
        $datasets = [];

        foreach ($entities as $entity) {

            $data = $entity == 'previous_year' ? $chartData->previous_year->totals['invoiced'] : $chartData->current_year->totals['invoiced'];
            $label = $entity == 'previous_year' ? trans("texts.previous_year") : trans("texts.invoiced");
            $color = $entity == 'previous_year' ? "200,200,200" : "51,122,183";

            $dataset = new stdClass();
            $dataset->data = $data;
            $dataset->label = $label;
            $dataset->tension = 0.3;
            $dataset->backgroundColor = "rgba({$color}, 0.1)";
            $dataset->borderColor = "rgba({$color}, 1)";
            $dataset->borderWidth = 2;
            $dataset->fill = "origin";
            $dataset->pointBackgroundColor = "rgba(255,255,255,1)";
            $dataset->pointBorderColor = "rgba({$color}, 1)";
            $dataset->pointBorderWidth = 2;
            $dataset->pointHoverBorderWidth = 2;
            $dataset->pointHoverRadius = 5;
            $datasets[] = $dataset;
        }

        $data = new stdClass();
        $data->labels = $chartData->current_year->labels;
        $data->datasets = $datasets;

        return $data;
    }

    /**
     * Return categoriesChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getCategoriesChart($chartData): stdClass
    {
        $categories = $chartData->current_year->categories;
        $labels          = [];
        $values          = [];
        $clients         = [];
        $backgroundColor = [];
        foreach ($categories as $category => $value) {
            $labels[]  = $category;
            $values[]  = $value['value'];
            $clients[] = $value['clients'];
            $x = rand(0 , 255);
            $y = rand(0 , 255);
            $z = rand(0 , 255);
            $backgroundColor[] = "rgb({$x},{$y},{$z})";
        }

        $sort = [
            'values'  => $values,
            'labels'  => $labels,
            'clients' => $clients
        ];

        array_multisort(
            $sort['values'], SORT_DESC, SORT_NUMERIC,
            $sort['labels'],
            $sort['clients']
        );



        $dataset = new stdClass();
        $dataset->data = $sort['values'];
        $dataset->label = trans("texts.categories");
        $dataset->backgroundColor = $backgroundColor;
        $datasets[] = $dataset;

        $data = new stdClass();
        $data->labels = $sort['labels'];
        $data->datasets = $datasets;
        $data->clients = $sort['clients'];

        // Same job for previous year (if range < 1 year)
        if (property_exists($chartData, 'previous_year')) {
            $categories = $chartData->previous_year->categories;
            $previousYear = [];
            foreach ($categories as $category => $value) {
                $previousYear[$category]  = $value['value'];
            }
            $data->previous_year = $previousYear;
        }

        return $data;
    }

    /**
     * Return Chart4 as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getYearlySummary($chartData): stdClass
    {
        $total_years_invoices = $chartData->total_years["invoices"];
        $labels = [];
        $values = [];

        foreach ($total_years_invoices as $year => $value) {
            $labels[] = $year;
            $values[] = $value;
        }

        array_multisort($labels, SORT_ASC, $values);

        $dataset = new stdClass();
        $dataset->data = $values;
        $dataset->label = trans("texts.yearly_report");
        $dataset->type = 'bar';
        $dataset->order = 2;
        $dataset->backgroundColor = "rgba(80, 157, 235, 1)";
        $datasets[] = $dataset;

        $total_years_expenses = $chartData->total_years["expenses"];
        $labels = [];
        $values = [];

        foreach ($total_years_expenses as $year => $value) {
            $labels[] = $year;
            $values[] = $value;
        }

        array_multisort($labels, SORT_ASC, $values);

        $dataset = new stdClass();
        $dataset->data = $values;
        $dataset->label = trans("texts.expenses");
        $dataset->type = 'line';
        $dataset->order = 1;
        $dataset->backgroundColor = "rgb(70,70,70)";
        $dataset->tension = 0.3;
        $dataset->borderColor = "rgba(70,70,70, 1)";
        $dataset->borderWidth = 2;
        $dataset->pointBackgroundColor = "rgb(70,70,70)";
        $dataset->pointBorderColor = "rgba(70,70,70, 1)";
        $dataset->pointBorderWidth = 2;
        $dataset->pointHoverBorderWidth = 2;
        $dataset->pointHoverRadius = 5;
        $datasets[] = $dataset;

        $data = new stdClass();
        $data->labels = $labels;
        $data->datasets = $datasets;

        return $data;
    }

    /**
     * Return paymentsChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getPaymentsChart($chartData): stdClass
    {
        $labels          = ['Payées', 'Envoyées', 'Retards', 'Impayées'];
        $backgroundColor = ['rgb(30,150,10)', 'rgb(10,110,150)', 'rgb(150, 70, 10)', 'rgb(150,10,10)'];
        $awaiting  = 0;
        $late      = 0;
        $very_late = 0;
        $counts = [0, 0, 0, 0];
        foreach ($chartData->current_year->totals['unpaid'] as $month) {
            $awaiting  += $month['awaiting']['value'];
            $counts[1] += $month['awaiting']['count'];
            $late      += $month['late']['value'];
            $counts[2] += $month['late']['count'];
            $very_late += $month['very_late']['value'];
            $counts[3] += $month['very_late']['count'];
        }
        $values = [
            array_sum($chartData->current_year->totals['payments']),
            $awaiting,
            $late,
            $very_late
        ];

        $dataset = new stdClass();
        $dataset->data = $values;
        $dataset->label = $labels;
        $dataset->backgroundColor = $backgroundColor;
        $datasets[] = $dataset;

        $data = new stdClass();
        $data->labels = $labels;
        $data->datasets = $datasets;
        $data->counts = $counts;

        return $data;
    }

    /**
     * Return clientsChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getClientsChart($chartData): stdClass
    {
        $total_years_clients = $chartData->clients;
        $labels = [];
        $values = [];

        foreach ($total_years_clients as $year => $value) {
            $labels[] = $year;
            $values[] = $value;
        }

        array_multisort($labels, SORT_ASC, $values);

        $results = [];
        $backgroundColors = [];
        $comparison = array_first($values);
        foreach ($values as $value) {
            $result = $value - $comparison;
            $results[] = $result;
            $backgroundColors[] = $result >= 0 ? "rgba(80, 157, 235, 1)" : "rgba(235, 80, 80, 1)";
            $comparison = $value;
        }

        $dataset = new stdClass();
        $dataset->data = $results;
        $dataset->label = $labels;
        $dataset->type = 'bar';
        $dataset->order = 2;
        $dataset->backgroundColor = $backgroundColors;
        $datasets[] = $dataset;

        $results = [];
        foreach ($values as $value) {
            $results[] = $value/8;
        }

        $dataset = new stdClass();
        $dataset->data = $results;
        $dataset->label = $labels;
        $dataset->type = 'line';
        $dataset->order = 1;
        $dataset->backgroundColor = "rgb(70,70,70)";
        $dataset->tension = 0.3;
        $dataset->borderColor = "rgba(70,70,70, 1)";
        $dataset->borderWidth = 2;
        $dataset->pointBackgroundColor = "rgb(70,70,70)";
        $dataset->pointBorderColor = "rgba(70,70,70, 1)";
        $dataset->pointBorderWidth = 2;
        $dataset->pointHoverBorderWidth = 2;
        $dataset->pointHoverRadius = 5;
        $datasets[] = $dataset;

        $data = new stdClass();
        $data->labels = $labels;
        $data->datasets = $datasets;

        return $data;
    }

    /**
     * Return revenuesChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getRevenuesChart($chartData): stdClass
    {
        $datasets = [];

        if(property_exists($chartData, 'previous_year')) {
            $dataset = new stdClass();
            $dataset->data = $chartData->previous_year->totals['recurringAmount'];
            $dataset->label = trans('texts.ca_recurring_N-1');
            $dataset->type = 'bar';
            $dataset->stack = 'previous';
            $dataset->order = 1;
            $dataset->backgroundColor = "#4D4352";

            $datasets[] = $dataset;

            $dataset = new stdClass();
            $dataset->data = $chartData->previous_year->totals['noRecurringAmount'];
            $dataset->label = trans('texts.ca_no_recurring_N-1');
            $dataset->type = 'bar';
            $dataset->stack = 'previous';
            $dataset->order = 1;
            $dataset->backgroundColor = "#9364B1";

            $datasets[] = $dataset;
        }

        $dataset = new stdClass();
        $dataset->data = $chartData->current_year->totals['recurringAmount'];
        $dataset->label = trans('texts.ca_recurring');
        $dataset->type = 'bar';
        $dataset->stack = 'current';
        $dataset->order = 1;
        $dataset->backgroundColor = "#4D4352";

        $datasets[] = $dataset;

        $dataset = new stdClass();
        $dataset->data = $chartData->current_year->totals['noRecurringAmount'];
        $dataset->label = trans('texts.ca_no_recurring');
        $dataset->type = 'bar';
        $dataset->stack = 'current';
        $dataset->order = 1;
        $dataset->backgroundColor = "#9364B1";

        $datasets[] = $dataset;

        $data = new stdClass();
        $data->labels = $chartData->current_year->labels;
        $data->datasets = $datasets;

        return $data;
    }

    /**
     * Return revenuesClChart as ChartJS data formatted object
     *
     * @param $chartData
     * @return stdClass
     */
    public function getRevenuesClChart($chartData): stdClass
    {
        $datasets = [];

        if(property_exists($chartData, 'previous_year')) {
            $dataset = new stdClass();
            $dataset->data = $chartData->previous_year->totals['oldClients'];
            $dataset->label = trans('texts.ca_old_clients_N-1');
            $dataset->type = 'bar';
            $dataset->stack = 'previous';
            $dataset->order = 1;
            $dataset->backgroundColor = "#337AB7";

            $datasets[] = $dataset;

            $dataset = new stdClass();
            $dataset->data = $chartData->previous_year->totals['newClients'];
            $dataset->label = trans('texts.ca_new_clients_N-1');
            $dataset->type = 'bar';
            $dataset->stack = 'previous';
            $dataset->order = 1;
            $dataset->backgroundColor = "#98EC87";

            $datasets[] = $dataset;
        }

        $dataset = new stdClass();
        $dataset->data = $chartData->current_year->totals['oldClients'];
        $dataset->label = trans('texts.ca_old_clients');
        $dataset->type = 'bar';
        $dataset->stack = 'current';
        $dataset->order = 1;
        $dataset->backgroundColor = "#337AB7";

        $datasets[] = $dataset;

        $dataset = new stdClass();
        $dataset->data = $chartData->current_year->totals['newClients'];
        $dataset->label = trans('texts.ca_new_clients');
        $dataset->type = 'bar';
        $dataset->stack = 'current';
        $dataset->order = 1;
        $dataset->backgroundColor = "#98EC87";

        $datasets[] = $dataset;

        $data = new stdClass();
        $data->labels = $chartData->current_year->labels;
        $data->datasets = $datasets;

        return $data;
    }
}
