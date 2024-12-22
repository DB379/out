<?php

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use Exception;

class SkladisteUlazModel
{
    private PDO $db;

    // Maksimalni broj zapisa po stranici za paginaciju
    private int $maxLimit = 100;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        // Postavljanje PDO Error Mode-a na Exception
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    /**
     * Validacija podataka za dodavanje alata.
     *
     * @param array $data
     * @throws InvalidArgumentException
     */
    private function validateAlatData(array $data): void
    {
        $requiredFields = ['naziv_alata', 'kategorija', 'kolicina'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new InvalidArgumentException("Polje {$field} je obavezno.");
            }
        }

        // Validacija formata serijskog broja ako je dostavljen
        if (!empty($data['serijski_broj_alata']) && !preg_match('/^[a-zA-Z0-9\-]+$/', $data['serijski_broj_alata'])) {
            throw new InvalidArgumentException("Serijski broj alata sadrži nedozvoljene znakove.");
        }

        // Validacija količine
        if (!is_numeric($data['kolicina']) || $data['kolicina'] < 0) {
            throw new InvalidArgumentException("Polje kolicina mora biti pozitivan broj.");
        }

        // Daljnja validacija prema potrebama aplikacije
    }

    /**
     * Sanitizacija podataka.
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = trim($value);
                // Opcionalno: htmlspecialchars za sprečavanje XSS ako se podaci prikazuju korisnicima
                 $sanitized[$key] = htmlspecialchars($sanitized[$key], ENT_QUOTES, 'UTF-8');
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    /**
     * Prikazuje sve zapise iz 'ulaz_skladiste' s paginacijom, filtriranjem i sortiranjem.
     */
    public function getAllUlazSkladiste(int $page, int $limit, string $filter = '', string $sort = '', string $order = 'asc'): array
    {
        return $this->fetchUlazSkladiste($page, $limit, $filter, $sort, $order, []);
    }

    /**
     * Prikazuje sve zapise iz 'ulaz_skladiste' osim onih sa stanjem_artikla 'Servis'.
     */
    public function getAllUlazSkladiste1(int $page, int $limit, string $filter = '', string $sort = '', string $order = 'asc'): array
    {
        return $this->fetchUlazSkladiste($page, $limit, $filter, $sort, $order, ['Servis']);
    }

    /**
     * Prikazuje sve zapise iz 'ulaz_skladiste' osim 'Servis' i redova koji počinju sa 'Zadužen'.
     */
    public function getAllUlazSkladiste2(int $page, int $limit, string $filter = '', string $sort = '', string $order = 'asc'): array
    {
        return $this->fetchUlazSkladiste($page, $limit, $filter, $sort, $order, ['Servis', 'Zaduzen%']);
    }

    /**
     * Prikazuje sve zapise iz 'ulaz_skladiste' osim redova koji počinju sa 'Zadužen'.
     */
    public function getAllUlazSkladiste3(int $page, int $limit, string $filter = '', string $sort = '', string $order = 'asc'): array
    {
        return $this->fetchUlazSkladiste($page, $limit, $filter, $sort, $order, ['Zaduzen%']);
    }

    /**
     * Opća metoda za dohvaćanje podataka iz 'ulaz_skladiste' s parametriziranim isključenjima.
     */
    private function fetchUlazSkladiste(int $page, int $limit, string $filter, string $sort, string $order, array $excludeStates): array
    {
        try {
            // Paginacija
            $offset = ($page - 1) * $limit;
            if ($limit > $this->maxLimit) {
                $limit = $this->maxLimit;
            }

            // Whitelist za sortiranje
            $allowedSortColumns = ['naziv_alata', 'kategorija', 'serijski_broj_alata', 'kolicina', 'stanje_artikla'];
            if (!in_array($sort, $allowedSortColumns)) {
                $sort = 'serijski_broj_alata'; // Podrazumevano sortiranje
            }

            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

            // Osnovni upit
            $query = "SELECT datum_knjizenja, naziv_alata, kategorija, serijski_broj_alata, kolicina, stanje_artikla 
                      FROM ulaz_skladiste
                      WHERE 1=1"; // Početni uslov

            $params = [];

            // Dodavanje filtera za isključenja
            if (!empty($excludeStates)) {
                foreach ($excludeStates as $key => $state) {
                    $query .= " AND stanje_artikla NOT LIKE :state$key";
                    $params[":state$key"] = $state;
                }
            }

            // Dodavanje filtera za pretragu
            if (!empty($filter)) {
                $query .= " AND (naziv_alata LIKE :filter OR kategorija LIKE :filter OR serijski_broj_alata LIKE :filter)";
                $params[':filter'] = '%' . $filter . '%';
            }

            $query .= " ORDER BY $sort $order LIMIT :limit OFFSET :offset";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Dohvatanje ukupnog broja zapisa za paginaciju
            $countQuery = "SELECT COUNT(*) as total FROM ulaz_skladiste WHERE 1=1";

            $countParams = [];

            if (!empty($excludeStates)) {
                foreach ($excludeStates as $key => $state) {
                    $countQuery .= " AND stanje_artikla NOT LIKE :state$key";
                    $countParams[":state$key"] = $state;
                }
            }

            if (!empty($filter)) {
                $countQuery .= " AND (naziv_alata LIKE :filter OR kategorija LIKE :filter OR serijski_broj_alata LIKE :filter)";
                $countParams[':filter'] = '%' . $filter . '%';
            }

            $countStmt = $this->db->prepare($countQuery);
            foreach ($countParams as $key => $value) {
                $countStmt->bindValue($key, $value, PDO::PARAM_STR);
            }

            $countStmt->execute();
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'data' => $records,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new Exception("Došlo je do pogreške pri dohvaćanju podataka iz skladišta.");
        }
    }

    /**
     * Provjera da li alat s danim serijskim brojem već postoji.
     */
    public function alatExists(string $serijskiBrojAlata): bool
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ulaz_skladiste WHERE serijski_broj_alata = :serijski_broj_alata");
            $stmt->execute([':serijski_broj_alata' => $serijskiBrojAlata]);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new Exception("Došlo je do pogreške pri provjeri postojanja alata.");
        }
    }

    /**
     * Dodavanje novog alata u 'ulaz_skladiste' s validacijom i transakcijama.
     */
    public function addAlat(array $data): void
    {
        // Sanitizacija i validacija podataka
        $data = $this->sanitizeData($data);
        $this->validateAlatData($data);

        try {
            $this->db->beginTransaction();

            // Provjera duplikata
            if ($this->alatExists($data['serijski_broj_alata'])) {
                throw new Exception("Alat s ovim serijskim brojem već postoji.");
            }

            // Dohvaćanje sljedećeg serijskog broja ako nije dostavljen
            if (empty($data['serijski_broj_alata'])) {
                $stmt = $this->db->prepare("
                    SELECT MAX(CAST(REGEXP_REPLACE(serijski_broj_alata, '[^0-9]', '') AS UNSIGNED)) + 1 AS next_serial 
                    FROM ulaz_skladiste 
                    WHERE serijski_broj_alata REGEXP '^[0-9]+$'
                ");
                $stmt->execute();
                $nextSerial = $stmt->fetchColumn();
                $data['serijski_broj_alata'] = $nextSerial ?: '1'; // Počinje od 1 ako nema postojećih
            }

            // Ubacivanje novog alata
            $stmt = $this->db->prepare("
                INSERT INTO ulaz_skladiste (datum_knjizenja, naziv_alata, kategorija, serijski_broj_alata, kolicina, stanje_artikla)
                VALUES (:datum_knjizenja, :naziv_alata, :kategorija, :serijski_broj_alata, :kolicina, :stanje_artikla)
            ");
            $stmt->execute([
                ':datum_knjizenja' => date('Y-m-d H:i:s'), // Trenutni datum i vrijeme
                ':naziv_alata' => $data['naziv_alata'],
                ':kategorija' => $data['kategorija'],
                ':serijski_broj_alata' => $data['serijski_broj_alata'],
                ':kolicina' => $data['kolicina'],
                ':stanje_artikla' => $data['stanje_artikla'] ?? 'Raspolozivo' // Postavljanje zadane vrijednosti
            ]);

            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log($e->getMessage());
            throw new Exception("Došlo je do pogreške pri dodavanju alata.");
        }
    }

    /**
     * Dohvaćanje kombinovanih podataka iz 'ulaz_skladiste' i 'ulaz_opsti_alat' s paginacijom, filtriranjem i sortiranjem.
     */
    public function getCombinedData(int $page, int $limit, string $filter = '', string $sort = '', string $order = 'asc'): array
    {
        try {
            $offset = ($page - 1) * $limit;
            if ($limit > $this->maxLimit) {
                $limit = $this->maxLimit;
            }

            // Whitelist za sortiranje
            $allowedSortColumns = ['naziv', 'sifra', 'kategorija', 'kolicina', 'datum_knjizenja', 'stanje_artikla'];
            if (!in_array($sort, $allowedSortColumns)) {
                $sort = 'kategorija'; // Podrazumevano sortiranje
            }

            $order = strtolower($order) === 'desc' ? 'DESC' : 'ASC';

            // Definicija specifičnog redoslijeda za kategorije (opciono)
            $categoryOrder = "
                CASE 
                    WHEN kategorija = 'Alat' THEN 1
                    WHEN kategorija = 'Opšti Alat' THEN 2
                    WHEN kategorija = 'Potrošni Materijal' THEN 3
                    ELSE 4
                END
            ";

            // Ujednačavanje kolona iz obe tabele
            $baseQuery = "
                (
                    SELECT datum_knjizenja, naziv_alata AS naziv, serijski_broj_alata AS sifra, kategorija, kolicina, stanje_artikla
                    FROM ulaz_skladiste
                    WHERE 1=1
                )
                UNION ALL
                (
                    SELECT datum_knjizenja, naziv_alata AS naziv, sifra_alata AS sifra, kategorija, kolicina_u_skladistu AS kolicina, '' AS stanje_artikla
                    FROM ulaz_opsti_alat
                    WHERE 1=1
                )
            ";

            $filterQuery = '';
            $params = [];

            if (!empty($filter)) {
                $filterQuery = "WHERE (naziv LIKE :filter OR sifra LIKE :filter OR kategorija LIKE :filter)";
                $params[':filter'] = '%' . $filter . '%';
            }

            // Definisanje ORDER BY klauzule
            if ($sort === 'kategorija' || $sort === 'sifra') {
                $orderBy = "ORDER BY $categoryOrder, sifra $order";
            } else {
                $orderBy = "ORDER BY $sort $order";
            }

            $finalQuery = "
                SELECT * FROM (
                    $baseQuery
                ) AS combined
                $filterQuery
                $orderBy
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($finalQuery);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            if (!empty($filter)) {
                $stmt->bindValue(':filter', '%' . $filter . '%', PDO::PARAM_STR);
            }

            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Izračunavanje ukupnog broja zapisa
            $countQuery = "
                SELECT COUNT(*) as total FROM (
                    $baseQuery
                ) AS combined
                $filterQuery
            ";

            $countStmt = $this->db->prepare($countQuery);
            if (!empty($filter)) {
                $countStmt->bindValue(':filter', '%' . $filter . '%', PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'data' => $records,
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (PDOException $e) {
            error_log($e->getMessage());
            throw new Exception("Došlo je do pogreške pri dohvaćanju kombinovanih podataka.");
        }
    }
}
