<?php

namespace App\Controllers;

use Pecee\SimpleRouter\SimpleRouter;
use App\Models\SkladisteUlazModel;
use App\Providers\SessionTokenProvider; 

class SkladisteUlazController
{
    private SkladisteUlazModel $ulazModel;

    public function __construct(SkladisteUlazModel $ulazModel)
    {
        $this->ulazModel = $ulazModel;
    }

    // Fetch all ulaz_skladiste data
    public function fetchUlazSkladisteData()
    {
        $this->fetchData(fn($page, $limit, $filter, $sort, $order) =>
        $this->ulazModel->getAllUlazSkladiste($page, $limit, $filter, $sort, $order));
    }

    // Fetch ulaz_skladiste data (bez "Servis")
    public function fetchUlazSkladisteData1()
    {
        $this->fetchData(fn($page, $limit, $filter, $sort, $order) =>
        $this->ulazModel->getAllUlazSkladiste1($page, $limit, $filter, $sort, $order));
    }

    // Fetch ulaz_skladiste data (bez "Servis" i "Zadužen")
    public function fetchUlazSkladisteData2()
    {
        $this->fetchData(fn($page, $limit, $filter, $sort, $order) =>
        $this->ulazModel->getAllUlazSkladiste2($page, $limit, $filter, $sort, $order));
    }

    // Fetch ulaz_skladiste data (bez "Zadužen")
    public function fetchUlazSkladisteData3()
    {
        $this->fetchData(fn($page, $limit, $filter, $sort, $order) =>
        $this->ulazModel->getAllUlazSkladiste3($page, $limit, $filter, $sort, $order));
    }

    // Helper metoda za dohvaćanje podataka
    private function fetchData(callable $fetchMethod)
    {
        header('Content-Type: application/json');
        $page = max((int)($_GET['page'] ?? 1), 1);
        $limit = (int)($_GET['limit'] ?? 15);
        $limit = $limit > 0 && $limit <= 100 ? $limit : 15;
        $filter = isset($_GET['filter']) ? trim(substr($_GET['filter'], 0, 100)) : '';
        $sort = $_GET['sort'] ?? '';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        try {
            $data = $fetchMethod($page, $limit, $filter, $sort, $order);

            // Sanitizacija podataka za frontend
            $data['data'] = array_map(function ($row) {
                return array_map(fn($value) => htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $row);
            }, $data['data']);

            echo json_encode($data);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Greška prilikom dohvatanja podataka: ' . $e->getMessage()]);
        }
    }

    public function addNewAlat()
    {
        header('Content-Type: application/json');

        // Čitanje sirovog inputa
        $rawInput = file_get_contents('php://input');
        $postData = json_decode($rawInput, true);


        // Validacija i sanitizacija unosa
        $postData['naziv_alata'] = $this->sanitizeString($postData['naziv_alata'] ?? '');
        $postData['kategorija'] = $this->sanitizeString($postData['kategorija'] ?? 'Alat');
        $postData['serijski_broj_alata'] = $this->sanitizeString($postData['serijski_broj_alata'] ?? '');
        $kolicina = filter_var($postData['kolicina'] ?? null, FILTER_VALIDATE_INT);

        // Validacija unosa
        if (!$this->validateAlatInput($postData['naziv_alata'], $kolicina)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nevažeći podaci za alat.']);
            return;
        }

        try {
            $this->ulazModel->addAlat([
                'naziv_alata' => $postData['naziv_alata'],
                'kategorija' => $postData['kategorija'],
                'serijski_broj_alata' => $postData['serijski_broj_alata'],
                'kolicina' => $kolicina,
                'stanje_artikla' => $postData['stanje_artikla'] ?? ''
            ]);

            $tokenProvider = SimpleRouter::router()->getCsrfVerifier()->getTokenProvider();
            if ($tokenProvider instanceof SessionTokenProvider) {
                $tokenProvider->regenerate();
                $newToken = $tokenProvider->getToken();
            }

            
            echo json_encode(['success' => 'Uspešno dodan novi alat.', 'csrf_token' => $newToken]);
 
        } catch (\Exception $e) {
            http_response_code(400);
            echo json_encode(['error' => 'Greška: ' . $e->getMessage()]);
        }
    }


// Izmenjena validacija da ne zahteva serijski broj
private function validateAlatInput(string $nazivAlata, ?int $kolicina): bool
{
    if (empty($nazivAlata)) {
        http_response_code(400);
        echo json_encode(['error' => 'Naziv alata je obavezan.']);
        return false;
    }

    if (!preg_match('/^[a-zA-Z0-9\s\-]+$/u', $nazivAlata)) {
        http_response_code(400);
        echo json_encode(['error' => 'Naziv alata sadrži nedozvoljene znakove.']);
        return false;
    }

    if ($kolicina === null || $kolicina <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Količina mora biti pozitivan broj.']);
        return false;
    }

    return true;
}

    // Sanitizacija stringa
    private function sanitizeString(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    // Dohvat kombinovanih podataka
    public function fetchCombinedData()
    {
        header('Content-Type: application/json');
        $page = max((int)($_GET['page'] ?? 1), 1);
        $limit = (int)($_GET['limit'] ?? 15);
        $limit = $limit > 0 && $limit <= 100 ? $limit : 15;
        $filter = isset($_GET['filter']) ? trim(substr($_GET['filter'], 0, 100)) : '';
        $sort = $_GET['sort'] ?? '';
        $order = strtolower($_GET['order'] ?? 'asc') === 'desc' ? 'desc' : 'asc';

        try {
            $data = $this->ulazModel->getCombinedData($page, $limit, $filter, $sort, $order);

            // Sanitizacija podataka za frontend
            $data['data'] = array_map(function ($row) {
                return array_map(fn($value) => htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8'), $row);
            }, $data['data']);

            echo json_encode($data);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Greška prilikom dohvatanja podataka: ' . $e->getMessage()]);
        }
    }
}
