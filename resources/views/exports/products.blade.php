<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Products List</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
            background-color: #fff;
            line-height: 1.6;
        }

        .header {
            background-color: #f8f9fa;
            padding: 30px 20px;
            border-bottom: 3px solid #007bff;
            margin-bottom: 30px;
        }

        .header h1 {
            color: #007bff;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 12px;
        }

        .filter-info {
            background-color: #e7f3ff;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
            font-size: 11px;
            color: #004085;
        }

        .pagination-info {
            font-size: 11px;
            color: #666;
            margin-bottom: 15px;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }

        thead {
            background-color: #007bff;
            color: white;
        }

        th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            border: 1px solid #0056b3;
        }

        td {
            padding: 10px 12px;
            border-bottom: 1px solid #ddd;
            font-size: 11px;
        }

        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        tbody tr:hover {
            background-color: #f0f0f0;
        }

        .status-active {
            background-color: #d4edda;
            color: #155724;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }

        .status-inactive {
            background-color: #f8d7da;
            color: #721c24;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 10px;
            font-weight: 600;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            background-color: #e9ecef;
            color: #333;
            border-radius: 3px;
            font-size: 10px;
            margin: 2px 2px 2px 0;
        }

        .no-data {
            text-align: center;
            padding: 20px;
            color: #999;
            font-size: 12px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #666;
            text-align: center;
        }

        .page-number {
            text-align: right;
            font-size: 10px;
            color: #999;
            margin-top: 20px;
        }

        .summary {
            background-color: #f8f9fa;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 11px;
            color: #333;
        }

        .summary-item {
            display: inline-block;
            margin-right: 30px;
        }

        .summary-label {
            color: #666;
            font-weight: 600;
        }

        .summary-value {
            color: #007bff;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Products List</h1>
        <p>Generated on {{ now()->format('F j, Y \a\t g:i A') }}</p>
    </div>

    @if(isset($filters) && count($filters) > 0)
        <div class="filter-info">
            <strong>Filters Applied:</strong>
            @foreach($filters as $key => $value)
                @if($value)
                    <span>{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                    <span>{{ is_array($value) ? implode(', ', $value) : $value }}</span>
                    @if(!$loop->last), @endif
                @endif
            @endforeach
        </div>
    @endif

    <div class="summary">
        <div class="summary-item">
            <span class="summary-label">Total Products:</span>
            <span class="summary-value">{{ $total }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Page:</span>
            <span class="summary-value">{{ $current_page }} of {{ ceil($total / $per_page) }}</span>
        </div>
        <div class="summary-item">
            <span class="summary-label">Items per Page:</span>
            <span class="summary-value">{{ $per_page }}</span>
        </div>
    </div>

    <div class="pagination-info">
        Page {{ $current_page }} of {{ ceil($total / $per_page) }}
    </div>

    @if(count($products) > 0)
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>SKU</th>
                    <th>Name</th>
                    <th>Brand</th>
                    <th>Face Value</th>
                    <th>Currency</th>
                    <th>Status</th>
                    <th>Region</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $index => $product)
                    <tr>
                        <td>{{ (($current_page - 1) * $per_page) + $loop->iteration }}</td>
                        <td><strong>{{ $product->sku }}</strong></td>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->brand?->name ?? 'N/A' }}</td>
                        <td>{{ number_format($product->face_value, 2) }}</td>
                        <td>{{ strtoupper($product->currency) }}</td>
                        <td>
                            <span class="status-{{ $product->status }}">
                                {{ ucfirst($product->status) }}
                            </span>
                        </td>
                        <td>
                            @if($product->regions && count($product->regions) > 0)
                                @foreach($product->regions as $region)
                                    <span class="badge">{{ $region }}</span>
                                @endforeach
                            @else
                                <span class="badge">All</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="no-data">
            <p>No products found.</p>
        </div>
    @endif

    <div class="footer">
        <p>&copy; {{ now()->year }} Manavault. All rights reserved.</p>
    </div>

    <div class="page-number">
        <script type="text/php">
            if (isset($pdf)) {
                $pdf->page_script(function($pageNumber, $pageCount) {
                    echo "Page {$pageNumber} of {$pageCount}";
                });
            }
        </script>
    </div>
</body>
</html>
