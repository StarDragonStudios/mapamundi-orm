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

    /**
     * @throws Exception
     */
    public function __construct()
    {
        $this->save();
        $this->refresh();
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
     * Inserta/Actualiza el registro en la base de datos.
     * Tras un INSERT exitoso, se llama a refresh() para emular
     * el comportamiento de Hibernate que rellena valores por defecto.
     *
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
                    // Obtener ID autogenerado (si lo hay)
                    $lastId = $conn->lastInsertId();
                    if ($lastId) {
                        $this->attributes[$pk] = $lastId;
                    }

                    // Ya no es nuevo
                    $this->isNewRecord = false;

                    // Refrescamos para obtener columnas generadas por la BD
                    $this->refresh();
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
            static $alreadyTried = false;
            // Si la tabla no existe, intentamos crearla y reintentar
            if ($this->isTableNotFound($pdo_e) && !$alreadyTried) {
                $alreadyTried = true;
                SchemaManager::createTableFromEntity(static::class);
                return $this->save();
            } else {
                throw $pdo_e;
            }
        }
    }

    /**
     * Borra el registro de la base de datos.
     *
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
     * Devuelve el registro con el PK = $id, o null si no existe.
     *
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
     * Retorna todos los registros de la tabla.
     *
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

    /**
     * Determina el nombre de la tabla usando el atributo #[Entity]
     * o, si no existe, usa el nombre de la clase en minúsculas.
     */
    public static function getTableName(): string
    {
        $reflection = new ReflectionClass(static::class);

        $entityAttrs = $reflection->getAttributes(Entity::class);
        if (!empty($entityAttrs)) {
            /** @var Entity $entityInstance */
            $entityInstance = $entityAttrs[0]->newInstance();
            if (!empty($entityInstance->tableName)) {
                return $entityInstance->tableName;
            }
        }

        $path = explode('\\', static::class);
        return strtolower(end($path));
    }

    /**
     * Comprueba si el error de PDO indica que la tabla no existe.
     */
    protected function isTableNotFound(PDOException $e): bool
    {
        $sqlState = $e->getCode();
        return ($sqlState === '42S02')
            || str_contains($e->getMessage(), 'doesn\'t exist')
            || str_contains($e->getMessage(), 'no such table');
    }

    /**
     * "Refresca" los atributos de este objeto haciendo un SELECT
     * de la fila actual con base en su primary key. Útil para cargar
     * valores por defecto o generados tras un insert.
     *
     * @throws Exception
     */
    public function refresh(): void
    {
        $pk = static::$primaryKey;
        if (!isset($this->attributes[$pk])) {
            // No podemos refrescar si no hay PK
            return;
        }

        $conn = DBCore::getInstance()->getConnection();
        $table = static::getTableName();

        $sql = "SELECT * FROM `$table` WHERE `$pk` = :id LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bindValue(':id', $this->attributes[$pk]);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $this->fill($row);
        }
    }
}
