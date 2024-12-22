<?php

namespace App\Controllers;

use App\Models\LagerSkladistaModel;

class LagerSkladistaController
{
    private LagerSkladistaModel $model;

    public function __construct(LagerSkladistaModel $model)
    {
        $this->model = $model;
    }

    /**
     * Fetch and return combined lager skladista data.
     */
    public function fetchLagerSkladistaData()
{
    header('Content-Type: application/json');

    // Dohvatanje parametara iz GET zahteva
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 15;
    $filter = isset($_GET['filter']) ? trim($_GET['filter']) : '';

    // Validacija parametara
    $page = max(1, $page);
    if ($limit < 1 || $limit > 100) {
        $limit = 15;
    }
    if (strlen($filter) > 100) {
        $filter = substr($filter, 0, 100);
    }

    try {
        $data = $this->model->getLagerSkladistaData($page, $limit, $filter);

        // Sanitizacija podataka pre slanja klijentu
        $data['data'] = array_map(function ($row) {
            return array_map(function ($value) {
                return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
            }, $row);
        }, $data['data']);

        echo json_encode($data);
    } catch (\Exception $e) {
        // Logovanje greške za dalje ispitivanje
        error_log($e->getMessage());

        http_response_code(500);
        echo json_encode(['error' => 'Greška prilikom dobijanja podataka o lageru skladišta.']);
    }
}
    /**
     * Fetch and return history of ulaz and izlaz for a specific artikal.
     */
    public function fetchHistory()
    {
        header('Content-Type: application/json');

        // Dohvatanje parametra 'broj' iz GET zahteva
        $broj = isset($_GET['broj']) ? trim($_GET['broj']) : '';

        if (empty($broj)) {
            http_response_code(400);
            echo json_encode(['error' => 'Nedostaje parametar "broj".']);
            return;
        }

        try {
            $history = $this->model->getArtikalHistory($broj);
            echo json_encode($history);
        } catch (\Exception $e) {
            // Logovanje greške za dalje ispitivanje
            error_log($e->getMessage());

            http_response_code(500);
            echo json_encode(['error' => 'Greška prilikom dobijanja istorije artikla.']);
        }
    }
}
