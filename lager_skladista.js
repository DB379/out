const endpoint = '/lager-skladista'; // Endpoint za ovu stranicu

let currentPage = 1;
let totalRecords = 0;
let recordsPerPage = 15;

// Funkcija za escapovanje HTML karaktera
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

// Logika za učitavanje, filtriranje i paginaciju
function loadData(page) {
    currentPage = page;
    const filterValue = document.getElementById('filterInput').value.trim();

    // Sastavljanje URL parametara bez sortiranja
    let url = `${endpoint}/data?page=${page}&limit=${recordsPerPage}`;
    if (filterValue) {
        url += `&filter=${encodeURIComponent(filterValue)}`;
    }

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            totalRecords = data.total;
            const totalPages = Math.ceil(totalRecords / recordsPerPage);
            document.getElementById('currentPage').innerText = `Stranica: ${data.page} od ${totalPages}`;

            const tbody = document.querySelector('#lagerSkladistaTable tbody');
            tbody.innerHTML = '';
            data.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${escapeHTML(row.naziv || '')}</td>
                    <td>${escapeHTML(row.broj || '')}</td>
                    <td>${escapeHTML(row.kategorija_artikla || '')}</td>
                    <td>${escapeHTML(row.kolicina || '')}</td>
                    <td>${escapeHTML(row.ulaz || '')}</td>
                    <td>${escapeHTML(row.izlaz || '')}</td>
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

    // Inicijalno učitavanje podataka
    loadData(1);
});