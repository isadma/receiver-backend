<!DOCTYPE html>
<html lang="ru">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Приёмка</title>
        <link rel="stylesheet" href="https://cdn.datatables.net/2.3.2/css/dataTables.dataTables.min.css">
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

            .stats-heading {
                margin: 0 0 8px;
                font-size: 13px;
                font-weight: 800;
                letter-spacing: 0.4px;
                text-transform: uppercase;
                color: var(--muted);
            }

            .shop-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 10px;
                width: 100%;
                margin: 4px 0 16px;
            }

            .shop-stat {
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 14px 16px;
            }

            .shop-stat-name {
                font-size: 18px;
                font-weight: 800;
                margin-bottom: 10px;
            }

            .shop-stat-nums {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 10px 16px;
            }

            .shop-stat-nums strong {
                display: block;
                font-size: 24px;
                font-variant-numeric: tabular-nums;
                color: var(--mapped);
            }

            .shop-stat-nums small {
                color: var(--muted);
                font-size: 11px;
                font-weight: 700;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            .section-title {
                margin: 8px 0 12px;
                font-size: 18px;
                font-weight: 800;
            }

            .shop-boxes {
                margin-bottom: 14px;
                background: var(--surface);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 8px 12px 12px;
            }

            .shop-boxes > summary {
                cursor: pointer;
                list-style: none;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                padding: 8px 4px 10px;
                font-size: 18px;
                font-weight: 800;
            }

            .shop-boxes > summary::-webkit-details-marker {
                display: none;
            }

            .shop-boxes > summary::after {
                content: '▾';
                color: var(--muted);
                font-size: 16px;
            }

            .shop-boxes:not([open]) > summary::after {
                content: '▸';
            }

            .shop-boxes-count {
                color: var(--muted);
                font-size: 13px;
                font-weight: 700;
                letter-spacing: 0.3px;
                text-transform: uppercase;
                margin-left: auto;
                margin-right: 8px;
            }

            .box-stats {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
                gap: 10px;
            }

            .box-stat {
                background: var(--bg);
                border: 1px solid var(--border);
                border-radius: 16px;
                padding: 14px 16px;
            }

            .box-stat-label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.4px;
                text-transform: uppercase;
                color: var(--muted);
            }

            .box-stat-title {
                margin: 0 0 12px;
                font-size: 22px;
                font-weight: 800;
            }

            .box-stat-nums {
                display: flex;
                gap: 18px;
            }

            .box-stat-nums div {
                min-width: 72px;
            }

            .box-stat-nums strong {
                display: block;
                font-size: 28px;
                font-variant-numeric: tabular-nums;
                color: var(--mapped);
            }

            .box-stat-nums small {
                color: var(--muted);
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.3px;
                text-transform: uppercase;
            }

            .box-stats-empty {
                margin: 0 0 20px;
                color: var(--muted);
            }

            .filters {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: end;
                margin-bottom: 12px;
            }

            .filter {
                display: flex;
                flex-direction: column;
                gap: 6px;
                min-width: 180px;
            }

            .filter span {
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.4px;
                text-transform: uppercase;
                color: var(--muted);
            }

            .filter select {
                min-height: 44px;
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 8px 12px;
                background: var(--surface);
                color: var(--text);
                font-size: 15px;
                font-weight: 600;
            }

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
                padding: 16px;
            }

            table.dataTable {
                width: 100% !important;
                border-collapse: collapse;
            }

            table.dataTable thead th {
                background: var(--header);
                color: #FFFFFF;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.3px;
                text-transform: uppercase;
                padding: 10px 12px;
            }

            table.dataTable tbody td {
                padding: 10px 12px;
                font-size: 14px;
                font-variant-numeric: tabular-nums;
                border-bottom: 1px solid var(--border);
                white-space: nowrap;
            }

            table.dataTable tbody td.name {
                white-space: normal;
                min-width: 160px;
            }

            table.dataTable tbody tr:hover td {
                background: #F8F4EA;
            }

            .dataTables_wrapper .dataTables_filter input,
            .dataTables_wrapper .dataTables_length select {
                border: 1px solid var(--border);
                border-radius: 10px;
                padding: 8px 10px;
                background: var(--surface);
                color: var(--text);
            }

            .dataTables_wrapper .dataTables_info,
            .dataTables_wrapper .dataTables_filter,
            .dataTables_wrapper .dataTables_length,
            .dataTables_wrapper .dataTables_paginate {
                color: var(--muted);
                padding-top: 12px;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button {
                border-radius: 8px !important;
            }

            .dataTables_wrapper .dataTables_paginate .paginate_button.current {
                background: var(--header) !important;
                color: #FFFFFF !important;
                border: 0 !important;
            }

            th.num, td.num {
                text-align: right;
            }
        </style>
    </head>
    <body>
        <div class="page">
            <h1>Приёмка</h1>
            <p class="lede">Какой штрихкод в каком коробе и сколько штук.</p>

            <div class="top">
                <div>
                    <div class="stats-heading">Всего</div>
                    <div class="stats">
                        <div class="stat">
                            <span>Строк</span>
                            <strong>{{ $rows }}</strong>
                        </div>
                        <div class="stat">
                            <span>Короба</span>
                            <strong>{{ $boxes }}</strong>
                        </div>
                        <div class="stat mapped">
                            <span>Штук</span>
                            <strong>{{ $qty }}</strong>
                        </div>
                        <div class="stat">
                            <span>Разных лотов</span>
                            <strong>{{ $differentLots }}</strong>
                        </div>
                        <div class="stat">
                            <span>Лотов всего</span>
                            <strong>{{ $scannedLots }}</strong>
                        </div>
                    </div>
                </div>
                <a class="export" href="{{ url('/export.xlsx') }}">Скачать Excel</a>
            </div>

            <div class="shop-stats">
                @foreach ($shopStats as $shop)
                    <div class="shop-stat">
                        <div class="shop-stat-name">{{ $shop['name'] }}</div>
                        <div class="shop-stat-nums">
                            <div>
                                <strong>{{ $shop['boxes'] }}</strong>
                                <small>короба</small>
                            </div>
                            <div>
                                <strong>{{ $shop['qty'] }}</strong>
                                <small>штук</small>
                            </div>
                            <div>
                                <strong>{{ $shop['differentLots'] }}</strong>
                                <small>разных лотов</small>
                            </div>
                            <div>
                                <strong>{{ $shop['scannedLots'] }}</strong>
                                <small>лотов всего</small>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <h2 class="section-title">По коробам</h2>
            @if ($boxStats->isEmpty())
                <p class="box-stats-empty">Пока нет коробов.</p>
            @else
                @foreach ($shopStats as $shop)
                    @php $shopBoxes = $boxStats->where('shop_slug', $shop['slug']); @endphp
                    @continue($shopBoxes->isEmpty())
                    <details class="shop-boxes" open>
                        <summary>
                            {{ $shop['name'] }}
                            <span class="shop-boxes-count">{{ $shopBoxes->count() }} коробов</span>
                        </summary>
                        <div class="box-stats">
                            @foreach ($shopBoxes as $box)
                                <div class="box-stat">
                                    <div class="box-stat-title">Короб {{ $box['box'] }}</div>
                                    <div class="box-stat-nums">
                                        <div>
                                            <strong>{{ $box['items'] }}</strong>
                                            <small>штук</small>
                                        </div>
                                        <div>
                                            <strong>{{ $box['different_lots'] }}</strong>
                                            <small>разных лотов</small>
                                        </div>
                                        <div>
                                            <strong>{{ $box['scanned_lots'] }}</strong>
                                            <small>лотов всего</small>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            @endif

            <div class="filters">
                <label class="filter">
                    <span>Магазин</span>
                    <select id="filter-shop">
                        <option value="">Все магазины</option>
                        <option value="amazon">Amazon</option>
                        <option value="noon">Noon</option>
                        <option value="namshi">Namshi</option>
                    </select>
                </label>
                <label class="filter">
                    <span>Короб</span>
                    <select id="filter-box">
                        <option value="">Все короба</option>
                    </select>
                </label>
            </div>

            <div class="table-wrap">
                <table id="catalog" class="display nowrap" style="width:100%">
                    <thead>
                        <tr>
                            <th>Магазин</th>
                            <th>Короб</th>
                            <th>Модель</th>
                            <th>Название</th>
                            <th>Цвет</th>
                            <th>Размер</th>
                            <th>Лот-штрихкод</th>
                            <th>Штрихкод</th>
                            <th class="num">Кол-во</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.datatables.net/2.3.2/js/dataTables.min.js"></script>
        <script>
            const boxFilters = @json($boxStats->map(fn ($box) => [
                'shop_slug' => $box['shop_slug'],
                'box' => $box['box'],
            ])->values());
            const shopSelect = document.getElementById('filter-shop');
            const boxSelect = document.getElementById('filter-box');

            function boxesForShop(shop) {
                const seen = new Set();
                const boxes = [];

                boxFilters.forEach((item) => {
                    if ((shop && item.shop_slug !== shop) || seen.has(item.box)) {
                        return;
                    }
                    seen.add(item.box);
                    boxes.push(item.box);
                });

                return boxes;
            }

            function refreshBoxOptions() {
                const shop = shopSelect.value;
                const current = boxSelect.value;
                const boxes = boxesForShop(shop);

                boxSelect.innerHTML = '';
                const all = document.createElement('option');
                all.value = '';
                all.textContent = 'Все короба';
                boxSelect.appendChild(all);

                boxes.forEach((box) => {
                    const option = document.createElement('option');
                    option.value = box;
                    option.textContent = box;
                    boxSelect.appendChild(option);
                });

                boxSelect.value = boxes.includes(current) ? current : '';
            }

            const table = new DataTable('#catalog', {
                processing: true,
                serverSide: true,
                ajax: {
                    url: @json(url('/datatable')),
                    type: 'GET',
                },
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100, 250],
                order: [[0, 'asc'], [1, 'asc']],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/2.3.2/i18n/ru.json',
                },
                columns: [
                    { data: 'shop', name: 'shop' },
                    { data: 'box_number', name: 'box_number' },
                    { data: 'model', name: 'model' },
                    { data: 'name', name: 'name', className: 'name' },
                    { data: 'color', name: 'color' },
                    { data: 'size_code', name: 'size_code' },
                    { data: 'lot_barcode', name: 'lot_barcode' },
                    { data: 'barcode', name: 'barcode' },
                    { data: 'qty', name: 'qty', className: 'num' },
                ],
            });

            function applyFilters() {
                table.column(0).search(shopSelect.value);
                table.column(1).search(boxSelect.value);
                table.draw();
            }

            refreshBoxOptions();
            shopSelect.addEventListener('change', () => {
                refreshBoxOptions();
                applyFilters();
            });
            boxSelect.addEventListener('change', applyFilters);
        </script>
    </body>
</html>
