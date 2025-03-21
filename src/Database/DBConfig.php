<?php

namespace Sdstudios\MapamundiOrm\Database;

class DBConfig
{
    public string $host {
        get => $this->host;
        set => $this->host = $value;
    }
    public string $user {
        get => $this->user;
        set => $this->user = $value;
    }
    public string $password {
        get => $this->password;
        set => $this->password = $value;
    }
    public string $dbName {
        get => $this->dbName;
        set => $this->dbName = $value;
    }
    public int $port {
        get => $this->port;
        set => $this->port = $value;
    }

    public function __construct(string $host, string $user, string $password, string $dbName, int $port = 3306)
    {
        $this->host = $host;
        $this->user = $user;
        $this->password = $password;
        $this->dbName = $dbName;
        $this->port = $port;

    }
}