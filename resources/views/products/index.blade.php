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
                <a class="export" href="{{ url('/export.xlsx') }}">Скачать Excel</a>
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
            new DataTable('#catalog', {
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
        </script>
    </body>
</html>
