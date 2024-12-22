
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lager Firme</title>
    <link rel="stylesheet" href="/public/resources/css/junior.css">

</head>

<body>
    <div class="container">
        <h1>Lager Firme</h1>
        <button id="excelBtn" class="excel-btn">
            Izvezi u Excel
            <div class="spinnerz" id="exportSpinner"></div>
        </button>

        <div class="filters">
            <input type="text" id="filterInput" placeholder="Pretraga po nazivu, kategoriji, ili serijskom broju">
            <button class="btn search" id="searchButton">Pretraži</button>
        </div>

        <table id="sharedTable">
            <thead>
                <tr>
                    <th data-column="naziv">Naziv Alata <span id="nazivSortIcon" class="sort-indicator"></span></th>
                    <th data-column="kategorija">Kategorija <span id="kategorijaSortIcon" class="sort-indicator"></span></th>
                    <th data-column="sifra">Serijski Broj Alata <span id="sifraSortIcon" class="sort-indicator"></span></th>
                    <th data-column="kolicina">Količina <span id="kolicinaSortIcon" class="sort-indicator"></span></th>
                    <th data-column="stanje_artikla">Stanje u Firmi <span id="stanje_artiklaSortIcon" class="sort-indicator"></span></th>
                </tr>
            </thead>
            <tbody>
                <!-- Dinamički se ubacuje -->
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
    <script src="/public/resources/script/lager_firme.js" defer></script>

</body>

</html>