<?php

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use Exception;

class LagerSkladistaModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function getLagerSkladistaData(int $page = 1, int $limit = 15, string $filter = ''): array
    {
        $this->validatePaginationParameters($page, $limit);
        $filter = htmlspecialchars(trim($filter));

        try {
            $offset = ($page - 1) * $limit;

            $query = "
                SELECT 
                    combined.*, 
                    COALESCE(ulaz.total_ulaz, 0) AS ulaz,
                    COALESCE(izlaz.total_izlaz, 0) AS izlaz
                FROM (
                    SELECT 
                        naziv_alata AS naziv, 
                        serijski_broj_alata AS broj, 
                        kategorija AS kategorija_artikla, 
                        kolicina, 
                        stanje_artikla
                    FROM ulaz_skladiste
                    WHERE stanje_artikla != 'Servis' AND stanje_artikla NOT LIKE 'Zadužen %'

                    UNION ALL

                    SELECT 
                        naziv_materijala AS naziv, 
                        sifra_materijala AS broj, 
                        kategorija AS kategorija_artikla, 
                        kolicina_u_skladistu AS kolicina, 
                        '' AS stanje_artikla
                    FROM ulaz_materijala

                    UNION ALL

                    SELECT 
                        naziv_alata AS naziv,
                        sifra_alata AS broj,
                        kategorija AS kategorija_artikla,
                        kolicina_u_skladistu AS kolicina,
                        '' AS stanje_artikla
                    FROM ulaz_opsti_alat
                ) AS combined
                LEFT JOIN (
                    SELECT sifra, SUM(total_ulaz) AS total_ulaz FROM (
                        SELECT sifra_materijala AS sifra, SUM(kolicina) AS total_ulaz
                        FROM istorija_dodavanja_materijala
                        GROUP BY sifra_materijala
                        UNION ALL
                        SELECT sifra_alata AS sifra, SUM(kolicina) AS total_ulaz
                        FROM istorija_dodavanja_opsti_alat
                        GROUP BY sifra_alata
                    ) ulaz_sub
                    GROUP BY sifra
                ) AS ulaz ON ulaz.sifra = combined.broj
                LEFT JOIN (
                    SELECT sifra, SUM(total_izlaz) AS total_izlaz FROM (
                        SELECT sifra_materijala AS sifra, SUM(kolicina) AS total_izlaz
                        FROM istorija_izlaza_materijala
                        GROUP BY sifra_materijala
                        UNION ALL
                        SELECT sifra_alata AS sifra, SUM(kolicina) AS total_izlaz
                        FROM istorija_izlaza_opsti_alat
                        GROUP BY sifra_alata
                    ) izlaz_sub
                    GROUP BY sifra
                ) AS izlaz ON izlaz.sifra = combined.broj
            ";

            $params = [];

            if (!empty($filter)) {
                $query .= " WHERE naziv LIKE :filter OR broj LIKE :filter OR kategorija_artikla LIKE :filter ";
                $params[':filter'] = '%' . $filter . '%';
            }

            $query .= "
                ORDER BY 
                    CASE 
                        WHEN kategorija_artikla = 'Alat' THEN 1
                        WHEN kategorija_artikla = 'Opšti Alat' THEN 2
                        WHEN kategorija_artikla = 'Potrošni Materijal' THEN 3
                        ELSE 4
                    END,
                    broj ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($query);

            if (!empty($filter)) {
                $stmt->bindValue(':filter', $params[':filter'], PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Poboljšano brojanje ukupnog broja redaka koristeći podupit
            $countQuery = "SELECT COUNT(*) as total FROM (" . substr($query, 0, strrpos($query, "LIMIT")) . ") AS count_query";
            $countStmt = $this->db->prepare($countQuery);
             if (!empty($filter)) {
                $countStmt->bindValue(':filter', $params[':filter'], PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];


            return [
                'data' => $records,
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
            ];
        } catch (PDOException $e) {
            error_log('Get Lager Skladista Data Error: ' . $e->getMessage());
            throw new Exception("Došlo je do pogreške pri dohvaćanju podataka.");
        }
    }

    public function getArtikalHistory(string $broj): array
    {
        $this->validateSerijskiBroj($broj);
        $broj = trim($broj);

        try {
            $query = "
                SELECT datum, kolicina, napomena, 'ulaz' AS tip FROM istorija_dodavanja_materijala WHERE sifra_materijala = :broj
                UNION ALL
                SELECT datum, kolicina, napomena, 'ulaz' AS tip FROM istorija_dodavanja_opsti_alat WHERE sifra_alata = :broj
                UNION ALL
                SELECT datum, kolicina, napomena, 'izlaz' AS tip FROM istorija_izlaza_materijala WHERE sifra_materijala = :broj
                UNION ALL
                SELECT datum, kolicina, napomena, 'izlaz' AS tip FROM istorija_izlaza_opsti_alat WHERE sifra_alata = :broj
                ORDER BY datum DESC
            ";

            $stmt = $this->db->prepare($query);
            $stmt->bindValue(':broj', $broj, PDO::PARAM_STR);
            $stmt->execute();
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $ulaz = array_filter($history, fn($item) => $item['tip'] === 'ulaz');
            $izlaz = array_filter($history, fn($item) => $item['tip'] === 'izlaz');

            return [
                'ulaz' => $ulaz,
                'izlaz' => $izlaz,
            ];
        } catch (PDOException $e) {
            error_log('Get Artikal History Error: ' . $e->getMessage());
            throw new Exception("Došlo je do pogreške pri dohvaćanju istorije artikla.");
        }
    }

    private function validatePaginationParameters(int $page, int $limit): void
    {
        if ($page < 1) {
            throw new InvalidArgumentException("Broj stranice mora biti pozitivan cijeli broj.");
        }

        if ($limit < 1 || $limit > 100) {
            throw new InvalidArgumentException("Limit mora biti između 1 i 100.");
        }
    }

    private function validateSerijskiBroj(string $serijskiBroj): void
    {
        if (empty($serijskiBroj)) {
            throw new InvalidArgumentException("Serijski broj artikla ne može biti prazan.");
        }

        if (!preg_match('/^[a-zA-Z0-9\-]+$/', $serijskiBroj)) {
            throw new InvalidArgumentException("Serijski broj alata sadrži nedozvoljene znakove.");
        }
    }
}

/*

<?php

namespace App\Models;

use PDO;
use PDOException;
use InvalidArgumentException;
use Exception;

class LagerSkladistaModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    // Fetch combined data with pagination and filtering
    public function getLagerSkladistaData(int $page = 1, int $limit = 15, string $filter = ''): array
    {
        $this->validatePaginationParameters($page, $limit);
        $filter = trim($filter);

        try {
            $offset = ($page - 1) * $limit;

            $query = "
                SELECT 
                    combined.*, 
                    COALESCE(ulaz.total_ulaz, 0) AS ulaz,
                    COALESCE(izlaz.total_izlaz, 0) AS izlaz
                FROM (
                    SELECT 
                        naziv_alata AS naziv, 
                        serijski_broj_alata AS broj, 
                        kategorija AS kategorija_artikla, 
                        kolicina, 
                        stanje_artikla
                    FROM ulaz_skladiste
                    WHERE stanje_artikla != 'Servis' AND stanje_artikla NOT LIKE 'Zadužen %'

                    UNION ALL

                    SELECT 
                        naziv_materijala AS naziv, 
                        sifra_materijala AS broj, 
                        kategorija AS kategorija_artikla, 
                        kolicina_u_skladistu AS kolicina, 
                        '' AS stanje_artikla
                    FROM ulaz_materijala

                    UNION ALL

                    SELECT 
                        naziv_alata AS naziv,
                        sifra_alata AS broj,
                        kategorija AS kategorija_artikla,
                        kolicina_u_skladistu AS kolicina,
                        '' AS stanje_artikla
                    FROM ulaz_opsti_alat
                ) AS combined
                LEFT JOIN (
                    SELECT sifra, SUM(total_ulaz) AS total_ulaz FROM (
                        SELECT sifra_materijala AS sifra, SUM(kolicina) AS total_ulaz
                        FROM istorija_dodavanja_materijala
                        GROUP BY sifra_materijala
                        UNION ALL
                        SELECT sifra_alata AS sifra, SUM(kolicina) AS total_ulaz
                        FROM istorija_dodavanja_opsti_alat
                        GROUP BY sifra_alata
                    ) ulaz_sub
                    GROUP BY sifra
                ) AS ulaz ON ulaz.sifra = combined.broj
                LEFT JOIN (
                    SELECT sifra, SUM(total_izlaz) AS total_izlaz FROM (
                        SELECT sifra_materijala AS sifra, SUM(kolicina) AS total_izlaz
                        FROM istorija_izlaza_materijala
                        GROUP BY sifra_materijala
                        UNION ALL
                        SELECT sifra_alata AS sifra, SUM(kolicina) AS total_izlaz
                        FROM istorija_izlaza_opsti_alat
                        GROUP BY sifra_alata
                    ) izlaz_sub
                    GROUP BY sifra
                ) AS izlaz ON izlaz.sifra = combined.broj
            ";

            $params = [];

            if (!empty($filter)) {
                $query .= " WHERE naziv LIKE :filter OR broj LIKE :filter OR kategorija_artikla LIKE :filter ";
                $params[':filter'] = '%' . $filter . '%';
            }

            $query .= "
                ORDER BY 
                    CASE 
                        WHEN kategorija_artikla = 'Alat' THEN 1
                        WHEN kategorija_artikla = 'Opšti Alat' THEN 2
                        WHEN kategorija_artikla = 'Potrošni Materijal' THEN 3
                        ELSE 4
                    END,
                    broj ASC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($query);

            if (!empty($filter)) {
                $stmt->bindValue(':filter', $params[':filter'], PDO::PARAM_STR);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Counting total rows
            $countQuery = "
                SELECT COUNT(*) as total FROM (
                    SELECT 
                        naziv_alata AS naziv, 
                        serijski_broj_alata AS broj, 
                        kategorija AS kategorija_artikla, 
                        kolicina, 
                        stanje_artikla
                    FROM ulaz_skladiste
                    WHERE stanje_artikla != 'Servis' AND stanje_artikla NOT LIKE 'Zadužen %'

                    UNION ALL

                    SELECT 
                        naziv_materijala AS naziv, 
                        sifra_materijala AS broj, 
                        kategorija AS kategorija_artikla, 
                        kolicina_u_skladistu AS kolicina, 
                        '' AS stanje_artikla
                    FROM ulaz_materijala

                    UNION ALL

                    SELECT 
                        naziv_alata AS naziv,
                        sifra_alata AS broj,
                        kategorija AS kategorija_artikla,
                        kolicina_u_skladistu AS kolicina,
                        '' AS stanje_artikla
                    FROM ulaz_opsti_alat
                ) AS combined
            ";

            if (!empty($filter)) {
                $countQuery .= " WHERE naziv LIKE :filter OR broj LIKE :filter OR kategorija_artikla LIKE :filter ";
            }

            $countStmt = $this->db->prepare($countQuery);
            if (!empty($filter)) {
                $countStmt->bindValue(':filter', $params[':filter'], PDO::PARAM_STR);
            }
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            return [
                'data' => $records,
                'total' => (int)$total,
                'page' => $page,
                'limit' => $limit,
            ];
            
        }
    

        // Fetch history for a specific artikal
        public function getArtikalHistory(string $broj): array
        {
            $this->validateSerijskiBroj($broj);
            $broj = trim($broj);

            try {
                // Ulaz istorija
                $ulazQuery = "
                    SELECT datum, kolicina, napomena FROM istorija_dodavanja_materijala
                    WHERE sifra_materijala = :broj

                    UNION ALL

                    SELECT datum, kolicina, napomena FROM istorija_dodavanja_opsti_alat
                    WHERE sifra_alata = :broj

                    ORDER BY datum DESC
                ";

                // Izlaz istorija
                $izlazQuery = "
                    SELECT datum, kolicina, napomena FROM istorija_izlaza_materijala
                    WHERE sifra_materijala = :broj

                    UNION ALL

                    SELECT datum, kolicina, napomena FROM istorija_izlaza_opsti_alat
                    WHERE sifra_alata = :broj

                    ORDER BY datum DESC
                ";

                $ulazStmt = $this->db->prepare($ulazQuery);
                $ulazStmt->bindValue(':broj', $broj, PDO::PARAM_STR);
                $ulazStmt->execute();
                $ulaz = $ulazStmt->fetchAll(PDO::FETCH_ASSOC);

                $izlazStmt = $this->db->prepare($izlazQuery);
                $izlazStmt->bindValue(':broj', $broj, PDO::PARAM_STR);
                $izlazStmt->execute();
                $izlaz = $izlazStmt->fetchAll(PDO::FETCH_ASSOC);

                return [
                    'ulaz' => $ulaz,
                    'izlaz' => $izlaz,
                ];
            } catch (PDOException $e) {
                error_log('Get Artikal History Error: ' . $e->getMessage());
                throw new Exception("Došlo je do pogreške pri dohvaćanju istorije artikla.");
            }
        }

        // Validate pagination parameters
        private function validatePaginationParameters(int $page, int $limit): void
        {
            if ($page < 1) {
                throw new InvalidArgumentException("Broj stranice mora biti pozitivan cijeli broj.");
            }

            if ($limit < 1 || $limit > 100) {
                throw new InvalidArgumentException("Limit mora biti između 1 i 100.");
            }
        }

        // Validate serijski broj
        private function validateSerijskiBroj(string $serijskiBroj): void
        {
            if (empty($serijskiBroj)) {
                throw new InvalidArgumentException("Serijski broj artikla ne može biti prazan.");
            }

            if (!preg_match('/^[a-zA-Z0-9\-]+$/', $serijskiBroj)) {
                throw new InvalidArgumentException("Serijski broj alata sadrži nedozvoljene znakove.");
            }
        }
    }


*/