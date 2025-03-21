<?php

namespace Sdstudios\MapamundiOrm\Database;
use Exception;
use PDO;
use PDOException;

class DBCore
{
    private static ?DBCore $instance = null;
    private PDO $connection {
        get => $this->connection;
    }

    private function __construct(DBConfig $config)
    {
        try {
            $this->connection = new PDO(
                $config->getDSN(),
                $config->user,
                $config->pass
            );
            $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            // Manejo simple del error, podrías mejorarlo
            die("Error de conexión: " . $e->getMessage());
        }
    }

    public static function init(DBConfig $config): void
    {
        if (self::$instance === null) {
            self::$instance = new DBCore($config);
        }
    }

    /**
     * @throws Exception
     */
    public static function getInstance(): DBCore
    {
        if (self::$instance === null) {
            throw new Exception("DBCore no ha sido inicializado. Llama a DBCore::init() primero.");
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->connection;
    }
}