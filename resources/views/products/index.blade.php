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
                max-width: 1600px;
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

            .top {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 20px;
            }

            .stats {
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
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

            .export {
                display: inline-block;
                background: var(--header);
                color: #FFFFFF;
                text-decoration: none;
                font-weight: 700;
                border-radius: 12px;
                padding: 14px 18px;
            }

            .table-wrap {
                overflow-x: auto;
                background: var(--surface);
                border-radius: 16px;
                border: 1px solid var(--border);
            }

            table {
                width: 100%;
                border-collapse: collapse;
                min-width: 1280px;
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

            td.name {
                white-space: normal;
                min-width: 160px;
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
            <p class="lede">Scanned lot quantities by size barcode.</p>

            <div class="top">
                <div class="stats">
                    <div class="stat">
                        <span>Rows</span>
                        <strong>{{ $items->count() }}</strong>
                    </div>
                    <div class="stat mapped">
                        <span>Amazon</span>
                        <strong>{{ $amazon }}</strong>
                    </div>
                    <div class="stat mapped">
                        <span>Noon</span>
                        <strong>{{ $noon }}</strong>
                    </div>
                    <div class="stat mapped">
                        <span>Namshi</span>
                        <strong>{{ $namshi }}</strong>
                    </div>
                </div>
                <a class="export" href="{{ url('/export.csv') }}">Download CSV</a>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Model</th>
                            <th>Name</th>
                            <th>Color</th>
                            <th>Size</th>
                            <th>Lot barcode</th>
                            <th>Item barcode</th>
                            <th class="num">Per lot</th>
                            <th class="num">Amazon</th>
                            <th class="num">Noon</th>
                            <th class="num">Namshi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($items as $item)
                            <tr>
                                <td>{{ $item->lot?->model }}</td>
                                <td class="name">{{ $item->lot?->name_en }}</td>
                                <td>{{ $item->lot?->color_en ?: $item->lot?->color }}</td>
                                <td>{{ $item->size_code }}</td>
                                <td>{{ $item->lot?->lot_barcode }}</td>
                                <td>{{ $item->barcode }}</td>
                                <td class="num">{{ $item->pieces_per_lot }}</td>
                                <td class="num">{{ $item->amazon_received_qty }}</td>
                                <td class="num">{{ $item->noon_received_qty }}</td>
                                <td class="num">{{ $item->namshi_received_qty }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="empty">No catalog rows. Run php artisan db:seed.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if ($items->isNotEmpty())
                        <tfoot>
                            <tr>
                                <td colspan="7">Total received</td>
                                <td class="num">{{ $amazon }}</td>
                                <td class="num">{{ $noon }}</td>
                                <td class="num">{{ $namshi }}</td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </body>
</html>
