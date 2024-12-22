async function exportToExcel() {
    const excelBtn = document.getElementById('excelBtn');
    const spinner = document.getElementById('exportSpinner');

    try {
        // Onemogući dugme i prikaži spinner
        excelBtn.disabled = true;
        spinner.style.display = 'block';

        const filterValue = document.getElementById('filterInput').value.trim();

        // Dohvati sve podatke bez limita za export
        const response = await fetch(`${endpoint}/data?page=1&limit=999999&filter=${encodeURIComponent(filterValue)}`, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const data = await response.json();

        if (data.error) {
            throw new Error(data.error);
        }

        // Formatiraj podatke za Excel
        const rows = data.data.map(item => ({
            'Naziv Alata': item.naziv_alata || '',
            'Kategorija': item.kategorija || '',
            'Serijski Broj Alata': item.sifra || '',
            'Količina': item.kolicina || 0,
            'Stanje u Firmi': item.stanje_artikla || ''
        }));

        // Kreiraj novi Excel fajl
        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(rows, {
            header: Object.keys(rows[0])
        });

        // Postavi širine kolona
        ws['!cols'] = [
            { wch: 30 }, // Naziv Alata
            { wch: 20 }, // Kategorija
            { wch: 25 }, // Serijski Broj Alata
            { wch: 15 }, // Količina
            { wch: 20 }  // Stanje u Firmi
        ];

        XLSX.utils.book_append_sheet(wb, ws, "Lager Firme");

        // Generiši ime fajla sa trenutnim datumom
        const now = new Date().toISOString().slice(0, 10);
        const fileName = `lager_firme_${now}.xlsx`;

        // Sačuvaj Excel fajl
        XLSX.writeFile(wb, fileName);
    } catch (error) {
        console.error(error);
        alert('Greška prilikom izvoza podataka: ' + error.message);
    } finally {
        // Omogući dugme i sakrij spinner
        excelBtn.disabled = false;
        spinner.style.display = 'none';
    }
}

const endpoint = '/lager-firme'; // Endpoint za ovu stranicu (nema dodavanja, samo prikaz)

let currentPage = 1;
let totalRecords = 0;
let recordsPerPage = 15;

// Sort state
let currentSortColumn = '';
let currentSortOrder = 'asc'; // 'asc' ili 'desc'

function escapeHTML(value) {
    if (typeof value !== 'string') return value;
    return value.replace(/[&<>"']/g, function(match) {
        const escapeChars = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        };
        return escapeChars[match];
    });
}

// Funkcija za sortiranje
function sortTable(column) {
    if (currentSortColumn === column) {
        // Prebacivanje pravca sortiranja
        currentSortOrder = (currentSortOrder === 'asc') ? 'desc' : 'asc';
    } else {
        currentSortColumn = column;
        currentSortOrder = 'asc'; // Podrazumevani pravac sortiranja
    }
    // Ažuriranje indikatora sortiranja
    updateSortIndicators();
    // Ponovno učitavanje podataka sa novim parametrima sortiranja
    loadData(1);
}

function updateSortIndicators() {
    // Očistiti sve indikatore sortiranja
    const sortIcons = document.querySelectorAll('.sort-indicator');
    sortIcons.forEach(icon => {
        icon.textContent = '';
    });

    if (currentSortColumn) {
        const sortIcon = document.getElementById(`${currentSortColumn}SortIcon`);
        if (sortIcon) {
            sortIcon.textContent = (currentSortOrder === 'asc') ? '▲' : '▼';
        }
    }
}

// Logika za učitavanje, filtriranje i paginaciju
function loadData(page) {
    currentPage = page;
    const filterValue = document.getElementById('filterInput').value.trim();

    // Sastavljanje URL parametara
    let url = `${endpoint}/data?page=${page}&limit=${recordsPerPage}`;
    if (filterValue) {
        url += `&filter=${encodeURIComponent(filterValue)}`;
    }
    if (currentSortColumn) {
        url += `&sort=${encodeURIComponent(currentSortColumn)}&order=${encodeURIComponent(currentSortOrder)}`;
    }

    fetch(url, {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
            
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            totalRecords = data.total;
            const totalPages = Math.ceil(totalRecords / recordsPerPage);
            document.getElementById('currentPage').innerText = `Stranica: ${data.page} od ${totalPages}`;

            const tbody = document.querySelector('#sharedTable tbody');
            tbody.innerHTML = '';
            data.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                <td>${escapeHTML(row.naziv || '')}</td>
                <td>${escapeHTML(row.kategorija || '')}</td>
                <td>${escapeHTML(row.sifra || '')}</td>
                <td>${escapeHTML(row.kolicina || '')}</td>
                <td>${escapeHTML(row.stanje_artikla || '')}</td>
            `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Došlo je do greške prilikom učitavanja podataka.');
        });
}

function prevPage() {
    if (currentPage > 1) {
        loadData(currentPage - 1);
    }
}

function nextPage() {
    if (currentPage < Math.ceil(totalRecords / recordsPerPage)) {
        loadData(currentPage + 1);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    // Dodaj događaj za Enter na polju za pretragu
    const filterInput = document.getElementById('filterInput');
    filterInput.addEventListener('keypress', (event) => {
        if (event.key === 'Enter') {
            loadData(1); // Pokreni pretragu kada se pritisne Enter
        }
    });

    // Selektuj dugmad preko ID-jeva
    const searchButton = document.getElementById('searchButton');
    const excelBtn = document.getElementById('excelBtn');
    const prevPageButton = document.getElementById('prevPageButton');
    const nextPageButton = document.getElementById('nextPageButton');

    // Dodaj event listener za Pretraži Dugme
    searchButton.addEventListener('click', () => loadData(1));

    // Dodaj event listener za Izvoz u Excel Dugme
    excelBtn.addEventListener('click', exportToExcel);

    // Dodaj event listenere za Paginacija Dugmad
    prevPageButton.addEventListener('click', prevPage);
    nextPageButton.addEventListener('click', nextPage);

    // Dodaj event listenere za naslove kolona za sortiranje
    const tableHeaders = document.querySelectorAll('#sharedTable th[data-column]');
    tableHeaders.forEach(header => {
        const column = header.getAttribute('data-column');
        header.addEventListener('click', () => sortTable(column));
    });

    // Inicijalno učitavanje podataka
    loadData(1);
});
