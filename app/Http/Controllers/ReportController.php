<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Barryvdh\DomPDF\Facade\Pdf;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        [$startDate, $endDate] = $this->getDates($request);

        $data = $this->getReportData($startDate, $endDate);

        return Inertia::render('Reports/Index', [
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
            'summary' => $data['summary'],
            'dailyRevenue' => $data['dailyRevenue'],
            'topProducts' => $data['topProducts'],
            'categoryStats' => $data['categoryStats'],
            'inventory' => $data['inventory'],
            'topupOrders' => $data['topupOrders'],
        ]);
    }

    public function export(Request $request)
    {
        [$startDate, $endDate] = $this->getDates($request);

        $data = $this->getReportData($startDate, $endDate);

        $spreadsheet = new Spreadsheet();

        $spreadsheet->getProperties()
            ->setCreator('CiCi Gaming')
            ->setTitle('Báo cáo kinh doanh CiCi Gaming')
            ->setSubject('Báo cáo doanh thu, nạp tài khoản và tồn kho');

        // SHEET 1: TỔNG QUAN
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tổng quan');

        $sheet->setCellValue('A1', 'BÁO CÁO KINH DOANH - CICI GAMING');
        $sheet->mergeCells('A1:B1');

        $sheet->setCellValue('A2', 'Thời gian:');
        $sheet->setCellValue('B2', $startDate . ' đến ' . $endDate);

        $sheet->fromArray([
            ['Chỉ số', 'Giá trị'],
            ['Doanh thu bán hàng', $data['summary']['revenue']],
            ['Tiền nạp đã thu', $data['summary']['topup_received']],
            ['Tổng tiền thực thu', $data['summary']['total_received']],
            ['Tiền nạp chờ xử lý', $data['summary']['topup_pending']],
            ['Tiền nạp đã xử lý', $data['summary']['topup_completed']],
            ['Lợi nhuận gộp', $data['summary']['profit']],
            ['Tổng đơn hàng', $data['summary']['total_orders']],
            ['Đơn hoàn thành', $data['summary']['completed_orders']],
            ['Đơn đã hủy', $data['summary']['cancelled_orders']],
            ['Đơn đã thanh toán', $data['summary']['paid_orders']],
            ['Đơn chưa thanh toán', $data['summary']['unpaid_orders']],
            ['Sản phẩm đang kinh doanh', $data['inventory']['total_products']],
            ['Sản phẩm sắp hết hàng', $data['inventory']['low_stock']],
            ['Sản phẩm hết hàng', $data['inventory']['out_of_stock']],
            ['Giá trị hàng tồn kho', $data['inventory']['stock_value']],
        ], null, 'A4');

        $this->styleHeader($sheet, 'A4:B4');

        // Định dạng tiền trong sheet tổng quan
        foreach ([5, 6, 7, 8, 9, 10, 19] as $currencyRow) {
            $sheet->getStyle('B' . $currencyRow)
                ->getNumberFormat()
                ->setFormatCode('#,##0" ₫"');
        }

        // SHEET 2: DOANH THU THEO NGÀY
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Doanh thu theo ngày');

        $sheet->fromArray([
            [
                'Ngày',
                'Doanh thu bán hàng',
                'Tiền nạp',
                'Tổng tiền thực thu',
                'Số đơn',
            ],
        ], null, 'A1');

        $row = 2;

        foreach ($data['dailyRevenue'] as $item) {
            $sheet->fromArray([
                [
                    $item->date,
                    (float) $item->revenue,
                    (float) $item->topup,
                    (float) $item->total_received,
                    (int) $item->orders,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $this->styleHeader($sheet, 'A1:E1');

        if ($row > 2) {
            $sheet->getStyle('B2:D' . ($row - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0" ₫"');
        }

        // SHEET 3: TOP SẢN PHẨM
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Sản phẩm bán chạy');

        $sheet->fromArray([
            ['Mã sản phẩm', 'Tên sản phẩm', 'Số lượng bán', 'Doanh thu'],
        ], null, 'A1');

        $row = 2;

        foreach ($data['topProducts'] as $item) {
            $sheet->fromArray([
                [
                    $item->product_id,
                    $item->product_name,
                    (int) $item->quantity_sold,
                    (float) $item->revenue,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $this->styleHeader($sheet, 'A1:D1');

        if ($row > 2) {
            $sheet->getStyle('D2:D' . ($row - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0" ₫"');
        }

        // SHEET 4: DOANH THU THEO DANH MỤC
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Danh mục');

        $sheet->fromArray([
            ['Danh mục', 'Số lượng bán', 'Doanh thu'],
        ], null, 'A1');

        $row = 2;

        foreach ($data['categoryStats'] as $item) {
            $sheet->fromArray([
                [
                    $item->category_name,
                    (int) $item->quantity_sold,
                    (float) $item->revenue,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $this->styleHeader($sheet, 'A1:C1');

        if ($row > 2) {
            $sheet->getStyle('C2:C' . ($row - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0" ₫"');
        }

        // SHEET 5: CHI TIẾT NẠP TÀI KHOẢN
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Nạp tài khoản');

        $sheet->fromArray([
            [
                'Mã đơn',
                'Ngày thanh toán',
                'Số tiền nạp',
                'Trạng thái nạp',
                'Ngày xác nhận cộng tiền',
            ],
        ], null, 'A1');

        $row = 2;

        foreach ($data['topupOrders'] as $item) {
            $statusLabel = $item->topup_status === 'completed'
                ? 'Đã xử lý'
                : 'Chờ xử lý';

            $sheet->fromArray([
                [
                    $item->id,
                    $item->completed_at,
                    (float) $item->topup_amount,
                    $statusLabel,
                    $item->topup_completed_at,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $this->styleHeader($sheet, 'A1:E1');

        if ($row > 2) {
            $sheet->getStyle('C2:C' . ($row - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0" ₫"');
        }

        // SHEET 6: CHI TIẾT TỒN KHO
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Tồn kho');

        $sheet->fromArray([
            [
                'SKU',
                'Tên sản phẩm',
                'Danh mục',
                'Đơn vị',
                'Tồn kho',
                'Ngưỡng tồn thấp',
                'Giá vốn',
                'Giá bán',
                'Giá trị tồn kho',
            ],
        ], null, 'A1');

        $inventoryProducts = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->select([
                'products.sku',
                'products.name',
                'categories.name as category_name',
                'products.unit',
                'products.stock_quantity',
                'products.low_stock_threshold',
                'products.cost_price',
                'products.selling_price',
            ])
            ->orderBy('categories.name')
            ->orderBy('products.name')
            ->get();

        $row = 2;

        foreach ($inventoryProducts as $product) {
            $stockValue = (float) $product->stock_quantity
                * (float) $product->cost_price;

            $sheet->fromArray([
                [
                    $product->sku,
                    $product->name,
                    $product->category_name ?? 'Chưa phân loại',
                    $product->unit,
                    (int) $product->stock_quantity,
                    (int) $product->low_stock_threshold,
                    (float) $product->cost_price,
                    (float) $product->selling_price,
                    $stockValue,
                ],
            ], null, 'A' . $row);

            $row++;
        }

        $this->styleHeader($sheet, 'A1:I1');

        if ($row > 2) {
            $sheet->getStyle('G2:I' . ($row - 1))
                ->getNumberFormat()
                ->setFormatCode('#,##0" ₫"');
        }

        // Định dạng chung cho các sheet
        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $worksheet->freezePane('A2');

            foreach (range('A', $worksheet->getHighestColumn()) as $column) {
                $worksheet->getColumnDimension($column)->setAutoSize(true);
            }
        }

        $filename = 'Bao-cao-CiCi-Gaming-'
            . $startDate . '-'
            . $endDate . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(
            function () use ($writer, $spreadsheet) {
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]
        );
    }

    private function getDates(Request $request): array
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ]);

        return [
            $validated['start_date'] ?? now()->toDateString(),
            $validated['end_date'] ?? now()->toDateString(),
        ];
    }

    private function getReportData(string $startDate, string $endDate): array
    {
        $startDateTime = $startDate . ' 00:00:00';
        $endDateTime = $endDate . ' 23:59:59';

        /*
         * Chỉ lấy đơn đã hoàn thành và đã thanh toán.
         * Ngày báo cáo được xác định theo completed_at,
         * giống logic báo cáo hiện tại của hệ thống.
         */
        $revenueQuery = DB::table('orders')
            ->where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('completed_at', [
                $startDateTime,
                $endDateTime,
            ]);

        // Doanh thu bán hàng = tổng đơn - tiền nạp
        $revenue = (clone $revenueQuery)
            ->selectRaw('COALESCE(SUM(total - COALESCE(topup_amount, 0)), 0) as revenue')
            ->value('revenue');

        // Tổng tiền nạp đã thu
        $topupReceived = (clone $revenueQuery)
            ->sum('topup_amount');

        // Tổng tiền thực thu, bao gồm hàng hóa và tiền nạp
        $totalReceived = (clone $revenueQuery)
            ->sum('total');

        $paidOrders = (clone $revenueQuery)->count();

        // Tiền nạp đã được nhân viên xác nhận cộng vào tài khoản
        $topupCompleted = (clone $revenueQuery)
            ->where('topup_amount', '>', 0)
            ->where('topup_status', 'completed')
            ->sum('topup_amount');

        // Tiền nạp khách đã thanh toán nhưng nhân viên chưa xử lý
        $topupPending = (clone $revenueQuery)
            ->where('topup_amount', '>', 0)
            ->where(function ($query) {
                $query->whereNull('topup_status')
                    ->orWhere('topup_status', '!=', 'completed');
            })
            ->sum('topup_amount');

        // Thống kê đơn hàng theo ngày tạo
        $orderQuery = DB::table('orders')
            ->whereBetween('created_at', [
                $startDateTime,
                $endDateTime,
            ]);

        $totalOrders = (clone $orderQuery)->count();

        $completedOrders = (clone $orderQuery)
            ->where('status', 'completed')
            ->count();

        $cancelledOrders = (clone $orderQuery)
            ->where('status', 'cancelled')
            ->count();

        $unpaidOrders = (clone $orderQuery)
            ->where('payment_status', 'unpaid')
            ->where('status', '!=', 'cancelled')
            ->count();

        // Lợi nhuận gộp chỉ tính sản phẩm, không tính tiền nạp
        $profit = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.completed_at', [
                $startDateTime,
                $endDateTime,
            ])
            ->selectRaw('
                COALESCE(
                    SUM(
                        (order_items.unit_price - order_items.cost_price)
                        * order_items.quantity
                    ),
                    0
                ) as profit
            ')
            ->value('profit');

        // Doanh thu từng ngày: tách riêng hàng hóa và tiền nạp
        $dailyRevenue = (clone $revenueQuery)
            ->selectRaw('
                DATE(completed_at) as date,
                COALESCE(SUM(total - COALESCE(topup_amount, 0)), 0) as revenue,
                COALESCE(SUM(topup_amount), 0) as topup,
                COALESCE(SUM(total), 0) as total_received,
                COUNT(*) as orders
            ')
            ->groupByRaw('DATE(completed_at)')
            ->orderBy('date')
            ->get();

        // Top sản phẩm bán chạy
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.completed_at', [
                $startDateTime,
                $endDateTime,
            ])
            ->selectRaw('
                order_items.product_id,
                order_items.product_name,
                SUM(order_items.quantity) as quantity_sold,
                SUM(order_items.subtotal) as revenue
            ')
            ->groupBy('order_items.product_id', 'order_items.product_name')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get();

        // Doanh thu theo danh mục
        $categoryStats = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('orders.status', 'completed')
            ->where('orders.payment_status', 'paid')
            ->whereBetween('orders.completed_at', [
                $startDateTime,
                $endDateTime,
            ])
            ->selectRaw("
                COALESCE(categories.name, 'Chưa phân loại') as category_name,
                SUM(order_items.quantity) as quantity_sold,
                SUM(order_items.subtotal) as revenue
            ")
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('revenue')
            ->get();

        // Danh sách đơn có nạp tài khoản
        $topupOrders = (clone $revenueQuery)
            ->where('topup_amount', '>', 0)
            ->select([
                'id',
                'topup_amount',
                'topup_status',
                'completed_at',
                'topup_completed_at',
            ])
            ->orderByDesc('completed_at')
            ->get();

        // Tồn kho hiện tại
        $inventoryQuery = DB::table('products')
            ->where('is_active', true);

        $inventory = [
            'total_products' => (clone $inventoryQuery)->count(),

            'low_stock' => (clone $inventoryQuery)
                ->whereColumn('stock_quantity', '<=', 'low_stock_threshold')
                ->count(),

            'out_of_stock' => (clone $inventoryQuery)
                ->where('stock_quantity', '<=', 0)
                ->count(),

            'stock_value' => (clone $inventoryQuery)
                ->selectRaw('COALESCE(SUM(stock_quantity * cost_price), 0) as total')
                ->value('total'),
        ];

        return [
            'summary' => [
                'revenue' => (float) $revenue,
                'topup_received' => (float) $topupReceived,
                'total_received' => (float) $totalReceived,
                'topup_pending' => (float) $topupPending,
                'topup_completed' => (float) $topupCompleted,
                'profit' => (float) $profit,
                'paid_orders' => $paidOrders,
                'total_orders' => $totalOrders,
                'completed_orders' => $completedOrders,
                'cancelled_orders' => $cancelledOrders,
                'unpaid_orders' => $unpaidOrders,
            ],
            'dailyRevenue' => $dailyRevenue,
            'topProducts' => $topProducts,
            'categoryStats' => $categoryStats,
            'inventory' => $inventory,
            'topupOrders' => $topupOrders,
        ];
    }

    private function styleHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFFFF');

        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()
            ->setARGB('FF991B1B');

        $sheet->getStyle($range)->getAlignment()
            ->setHorizontal('center')
            ->setVertical('center');

        $sheet->getRowDimension(1)->setRowHeight(24);
    }

    public function exportPdf(Request $request)
    {
        [$startDate, $endDate] = $this->getDates($request);

        $data = $this->getReportData($startDate, $endDate);

        $inventoryProducts = DB::table('products')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('products.is_active', true)
            ->select([
                'products.sku',
                'products.name',
                'categories.name as category_name',
                'products.unit',
                'products.stock_quantity',
                'products.low_stock_threshold',
                'products.cost_price',
                'products.selling_price',
            ])
            ->orderBy('categories.name')
            ->orderBy('products.name')
            ->get();

        $pdf = Pdf::loadView('reports.pdf', [
            'startDate' => $startDate,
            'endDate' => $endDate,
            'summary' => $data['summary'],
            'dailyRevenue' => $data['dailyRevenue'],
            'topProducts' => $data['topProducts'],
            'categoryStats' => $data['categoryStats'],
            'inventory' => $data['inventory'],
            'topupOrders' => $data['topupOrders'],
            'inventoryProducts' => $inventoryProducts,
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download(
            'Bao-cao-CiCi-Gaming-' . $startDate . '-' . $endDate . '.pdf'
        );
    }
}