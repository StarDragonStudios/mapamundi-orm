<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Exception;
use PDOException;
use ReflectionClass;
use Sdstudios\MapamundiOrm\Database\DBCore;
use PDO;

abstract class Model
{
    protected array $attributes = [];
    protected bool $isNewRecord = true;

    protected static string $primaryKey = 'id';

    public function __construct(array $data = [])
    {
        $this->fill($data);
    }

    public function fill(array $data): void
    {
        foreach ($data as $key => $value) {
            $this->set($key, $value);
        }
    }

    public function set(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    public function get(string $key): mixed
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @throws Exception
     */
    public function save(): bool
    {
        $conn = DBCore::getInstance()->getConnection();
        $table = static::getTableName();
        $pk = static::$primaryKey;

        try {
            $columns = array_keys($this->attributes);
            if ($this->isNewRecord) {
                // INSERT
                $placeholders = array_map(fn($col) => ":$col", $columns);

                $sql = "INSERT INTO `$table` (" . implode(', ', $columns) . ")
                    VALUES (" . implode(', ', $placeholders) . ")";
                $stmt = $conn->prepare($sql);

                foreach ($this->attributes as $col => $val) {
                    $stmt->bindValue(":$col", $val);
                }

                $result = $stmt->execute();
                if ($result) {
                    $lastId = $conn->lastInsertId();
                    if ($lastId) {
                        $this->attributes[$pk] = $lastId;
                    }
                    $this->isNewRecord = false;
                }

                return $result;
            } else {
                // UPDATE
                $setClause = [];

                foreach ($columns as $col) {
                    if ($col === $pk) continue;
                    $setClause[] = "`$col` = :$col";
                }

                $sql = "UPDATE `$table` 
                    SET " . implode(', ', $setClause) . "
                    WHERE `$pk` = :pk_val";
                $stmt = $conn->prepare($sql);

                foreach ($this->attributes as $col => $val) {
                    if ($col === $pk) continue;
                    $stmt->bindValue(":$col", $val);
                }
                $stmt->bindValue(':pk_val', $this->attributes[$pk]);

                return $stmt->execute();
            }
        } catch (PDOException $pdo_e) {
            if ($this->isTableNotFound($pdo_e)) {
                SchemaManager::createTableFromEntity(static::class);
                return $this->save();
            } else {
                throw $pdo_e;
            }
        }
    }

    /**
     * @throws Exception
     */
    public function delete(): bool
    {
        $conn = DBCore::getInstance()->getConnection();
        $table = static::getTableName();
        $pk = static::$primaryKey;

        if (!isset($this->attributes[$pk])) {
            return false;
        }

        $sql = "DELETE FROM `$table` WHERE `$pk` = :pk";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':pk', $this->attributes[$pk]);
        $result = $stmt->execute();

        if ($result) {
            $this->isNewRecord = true;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public static function find(int $id): ?static
    {
        $conn = DBCore::getInstance()->getConnection();
        $table = static::getTableName();
        $pk = static::$primaryKey;

        $sql = "SELECT * FROM `$table` WHERE `$pk` = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $id);
        $stmt->execute();

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($data) {
            $model = new static($data);
            $model->isNewRecord = false;
            return $model;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    public static function all(): array
    {
        $conn = DBCore::getInstance()->getConnection();
        $table = static::getTableName();
        $stmt = $conn->prepare("SELECT * FROM `$table`");
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_map(function ($row) {
            $model = new static($row);
            $model->isNewRecord = false;
            return $model;
        }, $rows);
    }

    public static function getTableName(): string
    {
        // Usamos ReflectionClass para inspeccionar la clase hija
        $reflection = new ReflectionClass(static::class);

        // Buscar si la clase tiene el atributo Entity
        $entityAttrs = $reflection->getAttributes(Entity::class);
        if (!empty($entityAttrs)) {
            /** @var Entity $entityInstance */
            $entityInstance = $entityAttrs[0]->newInstance();
            if (!empty($entityInstance->tableName)) {
                return $entityInstance->tableName;
            }
        }

        // Si no está anotado con Entity o no define tableName,
        // tomamos el nombre de la clase en minúsculas como fallback.
        $path = explode('\\', static::class);
        return strtolower(end($path));
    }

    protected function isTableNotFound(PDOException $e): bool
    {
        $sqlState = $e->getCode();
        return ($sqlState === '42S02')
            || str_contains($e->getMessage(), 'doesn\'t exist')
            || str_contains($e->getMessage(), 'no such table');
    }
}