<?php

namespace Sdstudios\MapamundiOrm\Database;

use PDO;

class DBCore
{
    private PDO $pdo;

    public function __construct(DBConfig $config)
    {
        $dsn = "mysql:host={$config->host};dbname={$config->dbName};port={$config->port};charset=utf8";
        $this->pdo = new PDO($dsn, $config->user, $config->password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_CLASS);
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}