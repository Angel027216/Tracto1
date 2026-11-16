<?php
class PensionadosModel
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listar(string $busqueda = ''): array
    {
        $sql = "SELECT
                    c.id_camion,
                    c.placa,
                    c.modelo,
                    c.estado AS estado_camion,
                    u.nombre AS pensionado,
                    l.nom_linea,
                    p.estado_cuenta,
                    p.proximo_pago
                FROM camiones c
                LEFT JOIN usuarios u ON u.id = c.id_usuario
                LEFT JOIN linea l ON l.id_linea = c.id_linea
                LEFT JOIN pensionados p
                    ON p.id_camion = c.id_camion
                    AND p.id_usuario = c.id_usuario
                WHERE (? = '' OR LOWER(COALESCE(l.nom_linea, 'Sin línea')) LIKE LOWER(?))
                ORDER BY COALESCE(l.nom_linea, 'Sin línea'), c.id_camion DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$busqueda, '%' . $busqueda . '%']);

            return $stmt->fetchAll();
    }
}
