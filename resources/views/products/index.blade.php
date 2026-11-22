<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Receiver</title>
        <style>
            :root {
                --bg: #F3EFE6;
                --surface: #FFFFFF;
                --text: #1A1A1A;
                --muted: #6B6458;
                --border: #D6CFC0;
                --mapped: #1F7A4D;
                --pending: #C45C26;
                --header: #163A4A;
            }

            * { box-sizing: border-box; }

            body {
                margin: 0;
                min-height: 100vh;
                background: var(--bg);
                color: var(--text);
                font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif;
            }

            .page {
                max-width: 1400px;
                margin: 0 auto;
                padding: 24px 20px 48px;
            }

            h1 {
                margin: 0 0 6px;
                font-size: 32px;
                font-weight: 800;
            }

            .lede {
                margin: 0 0 20px;
                color: var(--muted);
                font-size: 16px;
            }

            .stats {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                margin-bottom: 20px;
            }

            .stat {
                background: var(--surface);
                border-radius: 16px;
                padding: 14px 16px;
                min-width: 120px;
            }

            .stat span {
                display: block;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.4px;
                text-transform: uppercase;
                color: var(--muted);
            }

            .stat strong {
                display: block;
                margin-top: 6px;
                font-size: 28px;
                font-variant-numeric: tabular-nums;
            }

            .stat.mapped strong { color: var(--mapped); }
            .stat.pending strong { color: var(--pending); }

            .table-wrap {
                overflow-x: auto;
                background: var(--surface);
                border-radius: 16px;
                border: 1px solid var(--border);
            }

            table {
                width: 100%;
                border-collapse: collapse;
                min-width: 1100px;
            }

            th, td {
                padding: 10px 12px;
                text-align: left;
                border-bottom: 1px solid var(--border);
                white-space: nowrap;
            }

            th {
                position: sticky;
                top: 0;
                background: var(--header);
                color: #FFFFFF;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            td {
                font-size: 14px;
                font-variant-numeric: tabular-nums;
            }

            td.sku {
                font-weight: 800;
            }

            td.name {
                white-space: normal;
                min-width: 180px;
            }

            td.num, th.num {
                text-align: right;
            }

            tbody tr:hover {
                background: #F8F4EA;
            }

            .empty {
                color: var(--muted);
            }

            .badge {
                display: inline-block;
                border-radius: 999px;
                padding: 3px 8px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .badge.mapped {
                background: #DCEFE3;
                color: var(--mapped);
            }

            .badge.pending {
                background: #F8E6D8;
                color: var(--pending);
            }

            tfoot td {
                font-weight: 800;
                background: #E7E1D4;
                border-bottom: 0;
            }
        </style>
    </head>
    <body>
        <div class="page">
            <h1>Receiver</h1>
            <p class="lede">All products and quantities.</p>

            <div class="stats">
                <div class="stat">
                    <span>Products</span>
                    <strong>{{ $products->count() }}</strong>
                </div>
                <div class="stat mapped">
                    <span>Linked</span>
                    <strong>{{ $mapped }}</strong>
                </div>
                <div class="stat pending">
                    <span>Waiting</span>
                    <strong>{{ $pending }}</strong>
                </div>
                <div class="stat mapped">
                    <span>Received</span>
                    <strong>{{ $totals['total_received_qty'] }}</strong>
                </div>
                <div class="stat">
                    <span>Expected</span>
                    <strong>{{ $totals['total_expected_qty'] }}</strong>
                </div>
                <div class="stat pending">
                    <span>Left</span>
                    <strong>{{ max(0, $totals['total_expected_qty'] - $totals['total_received_qty']) }}</strong>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>SKU</th>
                            <th>Name</th>
                            <th>Barcode</th>
                            <th>Status</th>
                            <th class="num">Amazon exp</th>
                            <th class="num">Amazon rec</th>
                            <th class="num">Noon exp</th>
                            <th class="num">Noon rec</th>
                            <th class="num">Namshi exp</th>
                            <th class="num">Namshi rec</th>
                            <th class="num">Total exp</th>
                            <th class="num">Total rec</th>
                            <th class="num">Left</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($products as $product)
                            @php
                                $linked = filled($product->barcode);
                                $left = max(0, $product->total_expected_qty - $product->total_received_qty);
                            @endphp
                            <tr>
                                <td class="sku">{{ $product->sku }}</td>
                                <td class="name">{{ $product->name }}</td>
                                <td class="{{ $linked ? '' : 'empty' }}">{{ $product->barcode ?: '—' }}</td>
                                <td>
                                    <span class="badge {{ $linked ? 'mapped' : 'pending' }}">
                                        {{ $linked ? 'Linked' : 'Waiting' }}
                                    </span>
                                </td>
                                <td class="num">{{ $product->amazon_expected_qty }}</td>
                                <td class="num">{{ $product->amazon_received_qty }}</td>
                                <td class="num">{{ $product->noon_expected_qty }}</td>
                                <td class="num">{{ $product->noon_received_qty }}</td>
                                <td class="num">{{ $product->namshi_expected_qty }}</td>
                                <td class="num">{{ $product->namshi_received_qty }}</td>
                                <td class="num">{{ $product->total_expected_qty }}</td>
                                <td class="num">{{ $product->total_received_qty }}</td>
                                <td class="num">{{ $left }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="empty">No products yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($products->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="4">Total</td>
                                <td class="num">{{ $totals['amazon_expected_qty'] }}</td>
                                <td class="num">{{ $totals['amazon_received_qty'] }}</td>
                                <td class="num">{{ $totals['noon_expected_qty'] }}</td>
                                <td class="num">{{ $totals['noon_received_qty'] }}</td>
                                <td class="num">{{ $totals['namshi_expected_qty'] }}</td>
                                <td class="num">{{ $totals['namshi_received_qty'] }}</td>
                                <td class="num">{{ $totals['total_expected_qty'] }}</td>
                                <td class="num">{{ $totals['total_received_qty'] }}</td>
                                <td class="num">{{ max(0, $totals['total_expected_qty'] - $totals['total_received_qty']) }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </body>
</html>
