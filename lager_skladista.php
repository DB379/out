<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>Lager Skladišta</title>
    <link rel="stylesheet" href="/public/resources/css/junior.css">
</head>

<body>
    <div class="container">
        <h1>Lager Skladišta</h1>
        <button id="excelBtn" class="excel-btn">
            Izvezi u Excel
            <div class="spinnerz" id="exportSpinner"></div>
        </button>
        <div class="filters">
            <input type="text" id="filterInput" placeholder="Pretraga po nazivu, broju ili kategoriji">
            <button class="btn search" id="searchButton">Pretraži</button>
        </div>
        <table id="lagerSkladistaTable">
            <thead>
                <tr>
                    <th>Naziv</th>
                    <th>Broj</th>
                    <th>Kategorija</th>
                    <th>Količina</th>
                    <th>Ulaz</th>
                    <th>Izlaz</th>
                </tr>
            </thead>
            <tbody>
                <!-- Dinamički ubačeni podaci -->
            </tbody>
        </table>
        <div class="pagination">
            <button class="btn pagination-btn" id="prevPageButton">Prethodna</button>
            <span class="pagination-info" id="currentPage"></span>
            <button class="btn pagination-btn" id="nextPageButton">Sledeća</button>
        </div>
    </div>
    <input type="hidden" name="csrf_token" value="<?= csrf_token(); ?>">
    <script src="/public/resources/script/xlsx.js"></script>
    <script src="/public/resources/script/excel.js"></script>
    <script src="/public/resources/script/lager_skladista.js" defer></script>
</body>

</html>
