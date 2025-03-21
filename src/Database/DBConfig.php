<?php

namespace Sdstudios\MapamundiOrm\Database;

class DBConfig
{
    protected string $host;
    protected string $dbname;
    public string $user {
        get => $this->user;
    }
    public string $pass {
        get => $this->pass;
    }
    protected string $driver;
    protected ?string $charset;

    public function __construct(
        string $host = 'localhost',
        string $dbname = 'test',
        string $user = 'root',
        string $pass = '',
        string $driver = 'mysql',
        ?string $charset = 'utf8mb4'
    ) {
        $this->host = $host;
        $this->dbname = $dbname;
        $this->user = $user;
        $this->pass = $pass;
        $this->driver = $driver;
        $this->charset = $charset;
    }

    public function getDSN(): string
    {
        if ($this->driver === 'mysql') {
            return "$this->driver:host=$this->host;dbname=$this->dbname;charset=$this->charset";
        }

        return "$this->driver:host=$this->host;dbname=$this->dbname";
    }

}