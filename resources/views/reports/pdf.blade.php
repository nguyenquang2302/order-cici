<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Báo cáo CiCi Gaming</title>
    <style>
        @page {
            margin: 25px 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #222;
        }

        h1 {
            text-align: center;
            color: #991b1b;
            font-size: 20px;
            margin-bottom: 5px;
        }

        h2 {
            color: #991b1b;
            font-size: 13px;
            margin-top: 22px;
            margin-bottom: 8px;
            border-bottom: 1px solid #ddd;
            padding-bottom: 5px;
        }

        .subtitle {
            text-align: center;
            color: #555;
            margin-bottom: 20px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .summary td {
            width: 25%;
            border: 1px solid #ddd;
            padding: 10px;
            text-align: center;
        }

        .summary strong {
            display: block;
            font-size: 15px;
            color: #991b1b;
            margin-top: 5px;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        table.data th {
            background: #991b1b;
            color: white;
            font-weight: bold;
            padding: 7px 5px;
            text-align: center;
        }

        table.data td {
            border: 1px solid #ddd;
            padding: 6px 5px;
        }

        table.data tr:nth-child(even) {
            background: #f5f5f5;
        }

        .right {
            text-align: right;
        }

        .center {
            text-align: center;
        }

        .money {
            white-space: nowrap;
        }

        .page-break {
            page-break-before: always;
        }

        .footer {
            margin-top: 25px;
            text-align: right;
            color: #777;
            font-size: 9px;
        }
    </style>
</head>
<body>

    <h1>BÁO CÁO KINH DOANH</h1>
    <div class="subtitle">
        CICI GAMING<br>
        Thời gian: {{ $startDate }} đến {{ $endDate }}
    </div>

    <h2>1. TỔNG QUAN KINH DOANH</h2>

    <table class="summary">
        <tr>
            <td>
                Doanh thu thực thu
                <strong>{{ number_format($summary['revenue'], 0, ',', '.') }} đ</strong>
            </td>
            <td>
                Lợi nhuận gộp
                <strong>{{ number_format($summary['profit'], 0, ',', '.') }} đ</strong>
            </td>
            <td>
                Tổng đơn hàng
                <strong>{{ number_format($summary['total_orders']) }}</strong>
            </td>
            <td>
                Đơn đã thanh toán
                <strong>{{ number_format($summary['paid_orders']) }}</strong>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Chỉ tiêu</th>
                <th>Giá trị</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Đơn hoàn thành</td>
                <td class="right">{{ $summary['completed_orders'] }}</td>
            </tr>
            <tr>
                <td>Đơn đã hủy</td>
                <td class="right">{{ $summary['cancelled_orders'] }}</td>
            </tr>
            <tr>
                <td>Đơn chưa thanh toán</td>
                <td class="right">{{ $summary['unpaid_orders'] }}</td>
            </tr>
        </tbody>
    </table>

    <h2>2. DOANH THU THEO NGÀY</h2>

    <table class="data">
        <thead>
            <tr>
                <th>Ngày</th>
                <th>Số đơn</th>
                <th>Doanh thu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($dailyRevenue as $item)
                <tr>
                    <td class="center">{{ $item->date }}</td>
                    <td class="center">{{ $item->orders }}</td>
                    <td class="right money">
                        {{ number_format($item->revenue, 0, ',', '.') }} đ
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="center">Chưa có dữ liệu</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>3. TOP 10 SẢN PHẨM BÁN CHẠY</h2>

    <table class="data">
        <thead>
            <tr>
                <th>STT</th>
                <th>Sản phẩm</th>
                <th>Số lượng bán</th>
                <th>Doanh thu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($topProducts as $index => $product)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td>{{ $product->product_name }}</td>
                    <td class="center">{{ $product->quantity_sold }}</td>
                    <td class="right money">
                        {{ number_format($product->revenue, 0, ',', '.') }} đ
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="center">Chưa có dữ liệu</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="page-break"></div>

    <h2>4. DOANH THU THEO DANH MỤC</h2>

    <table class="data">
        <thead>
            <tr>
                <th>Danh mục</th>
                <th>Số lượng bán</th>
                <th>Doanh thu</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($categoryStats as $category)
                <tr>
                    <td>{{ $category->category_name }}</td>
                    <td class="center">{{ $category->quantity_sold }}</td>
                    <td class="right money">
                        {{ number_format($category->revenue, 0, ',', '.') }} đ
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="center">Chưa có dữ liệu</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>5. TÌNH HÌNH TỒN KHO</h2>

    <table class="summary">
        <tr>
            <td>
                Sản phẩm đang kinh doanh
                <strong>{{ $inventory['total_products'] }}</strong>
            </td>
            <td>
                Sắp hết hàng
                <strong>{{ $inventory['low_stock'] }}</strong>
            </td>
            <td>
                Hết hàng
                <strong>{{ $inventory['out_of_stock'] }}</strong>
            </td>
            <td>
                Giá trị tồn kho
                <strong>{{ number_format($inventory['stock_value'], 0, ',', '.') }} đ</strong>
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>SKU</th>
                <th>Sản phẩm</th>
                <th>Danh mục</th>
                <th>Đơn vị</th>
                <th>Tồn kho</th>
                <th>Ngưỡng thấp</th>
                <th>Giá vốn</th>
                <th>Giá bán</th>
                <th>Giá trị tồn</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($inventoryProducts as $product)
                <tr>
                    <td>{{ $product->sku }}</td>
                    <td>{{ $product->name }}</td>
                    <td>{{ $product->category_name ?? 'Chưa phân loại' }}</td>
                    <td>{{ $product->unit }}</td>
                    <td class="center">{{ $product->stock_quantity }}</td>
                    <td class="center">{{ $product->low_stock_threshold }}</td>
                    <td class="right">
                        {{ number_format($product->cost_price, 0, ',', '.') }}
                    </td>
                    <td class="right">
                        {{ number_format($product->selling_price, 0, ',', '.') }}
                    </td>
                    <td class="right">
                        {{ number_format($product->stock_quantity * $product->cost_price, 0, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="center">Chưa có sản phẩm</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Báo cáo được xuất từ hệ thống CiCi Gaming
    </div>

</body>
</html>
