<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Exception;
use PDO;
use ReflectionClass;
use ReflectionProperty;


abstract class Model
{
    protected static PDO $pdo;

    public static function setConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function tableName(): string
    {
        $shortName = new ReflectionClass(static::class)->getShortName();
        return strtolower($shortName) . 's';
    }

    public function save(): void
    {
        // Ejemplo mínimo de INSERT/UPDATE
        $refClass = new ReflectionClass(static::class);
        $props = $refClass->getProperties();

        $columns = [];
        $values = [];
        $placeholders = [];
        $pkName = null;
        $pkValue = null;

        foreach ($props as $prop) {
            $attr = $prop->getAttributes(Column::class);
            if (count($attr) > 0) {
                /** @var Column $columnMeta */
                $columnMeta = $attr[0]->newInstance();
                $colName = $prop->getName();
                $colValue = $this->$colName;

                $columns[] = $colName;
                $values[] = $colValue;
                $placeholders[] = '?';

                if ($columnMeta->primaryKey) {
                    $pkName = $colName;
                    $pkValue = $colValue;
                }
            }
        }

        $table = static::tableName();

        if ($pkName && $pkValue) {
            // UPDATE
            $sets = [];
            $updateValues = [];
            foreach ($columns as $i => $col) {
                if ($col !== $pkName) {
                    $sets[] = "`$col` = ?";
                    $updateValues[] = $values[$i];
                }
            }
            $sql = "UPDATE `$table` SET " . implode(", ", $sets) . " WHERE `$pkName` = ?";
            $stmt = self::$pdo->prepare($sql);
            $updateValues[] = $pkValue;
            $stmt->execute($updateValues);
        } else {
            // INSERT
            $colList = implode(', ', array_map(fn($c) => "`$c`", $columns));
            $valList = implode(', ', $placeholders);
            $sql = "INSERT INTO `$table` ($colList) VALUES ($valList)";
            $stmt = self::$pdo->prepare($sql);
            $stmt->execute($values);

            if ($pkName) {
                $this->$pkName = self::$pdo->lastInsertId();
            }
        }
    }

    /**
     * @throws Exception
     */
    public static function find(int $id): ?static
    {
        $table = static::tableName();
        $pkName = static::getPrimaryKeyName();
        if (!$pkName) throw new Exception("No primary key column found in " . static::class);

        $sql = "SELECT * FROM `$table` WHERE `$pkName` = ?";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $instance = new static();
        foreach ($row as $key => $val) {
            if (property_exists($instance, $key)) {
                $instance->$key = $val;
            }
        }
        return $instance;
    }

    protected static function getPrimaryKeyName(): ?string
    {
        $refClass = new ReflectionClass(static::class);
        foreach ($refClass->getProperties() as $prop) {
            $attr = $prop->getAttributes(Column::class);
            if (count($attr) > 0) {
                /** @var Column $columnMeta */
                $columnMeta = $attr[0]->newInstance();
                if ($columnMeta->primaryKey) {
                    return $prop->getName();
                }
            }
        }
        return null;
    }

    public function loadRelations(): void
    {
        $refClass = new ReflectionClass($this);
        $props = $refClass->getProperties();

        foreach ($props as $prop) {
            // Revisar si la propiedad tiene un atributo de relación
            $relationAttrs = $prop->getAttributes(One2One::class)
                ?: $prop->getAttributes(One2Many::class)
                    ?: $prop->getAttributes(Many2One::class)
                        ?: $prop->getAttributes(Many2Many::class);

            if (count($relationAttrs) === 0) {
                continue;
            }

            $relation = $relationAttrs[0]->newInstance(); // El primer attribute

            match (true) {
                $relation instanceof One2One => $this->loadOne2One($prop, $relation),
                $relation instanceof Many2One => $this->loadMany2One($prop, $relation),
                $relation instanceof One2Many => $this->loadOne2Many($prop, $relation),
                $relation instanceof Many2Many => $this->loadMany2Many($prop, $relation),
                default => null
            };
        }
    }

    private function loadMany2One(ReflectionProperty $prop, Many2One $rel): void
    {
        $foreignKeyCol = $rel->foreignKey;
        $ownerKey = $rel->ownerKey;
        /** @var Model $targetClass */
        $targetClass = $rel->target;

        // El valor de la FK en *este* objeto
        $fkValue = $this->$foreignKeyCol;
        if (!$fkValue) {
            $this->{$prop->getName()} = null;
            return;
        }

        // Buscar en la tabla target: SELECT * FROM target WHERE ownerKey = $fkValue
        $sql = "SELECT * FROM `{$targetClass::tableName()}` WHERE `$ownerKey` = ?";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$fkValue]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $instance = new $targetClass();
            foreach ($row as $col => $val) {
                if (property_exists($instance, $col)) {
                    $instance->$col = $val;
                }
            }
            // Asignar la instancia a la propiedad
            $this->{$prop->getName()} = $instance;
        } else {
            $this->{$prop->getName()} = null;
        }
    }

    private function loadOne2Many(ReflectionProperty $prop, One2Many $rel): void
    {
        $localKeyValue = $this->{$rel->localKey};
        if (!$localKeyValue) {
            $this->{$prop->getName()} = [];
            return;
        }

        /** @var Model $targetClass */
        $targetClass = $rel->target;
        $tableChild = $targetClass::tableName();

        // SELECT * FROM child WHERE foreignKey = $localKeyValue
        $sql = "SELECT * FROM `$tableChild` WHERE `{$rel->foreignKey}` = ?";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$localKeyValue]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $children = [];
        foreach ($rows as $row) {
            $childInstance = new $targetClass();
            foreach ($row as $col => $val) {
                if (property_exists($childInstance, $col)) {
                    $childInstance->$col = $val;
                }
            }
            $children[] = $childInstance;
        }
        $this->{$prop->getName()} = $children;
    }

    private function loadOne2One(ReflectionProperty $prop, One2One $rel): void
    {
        $localKeyValue = $this->{$rel->localKey};
        if (!$localKeyValue) {
            $this->{$prop->getName()} = null;
            return;
        }

        /** @var Model $targetClass */
        $targetClass = $rel->target;
        $tableChild = $targetClass::tableName();

        // SELECT * FROM child WHERE foreignKey = $localKeyValue LIMIT 1
        $sql = "SELECT * FROM `$tableChild` WHERE `{$rel->foreignKey}` = ? LIMIT 1";
        $stmt = self::$pdo->prepare($sql);
        $stmt->execute([$localKeyValue]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($row) {
            $childInstance = new $targetClass();
            foreach ($row as $col => $val) {
                if (property_exists($childInstance, $col)) {
                    $childInstance->$col = $val;
                }
            }
            $this->{$prop->getName()} = $childInstance;
        } else {
            $this->{$prop->getName()} = null;
        }
    }

    private function loadMany2Many(ReflectionProperty $prop, Many2Many $rel)
    {
        $localKeyValue = $this->{$rel->localKey};
        if (!$localKeyValue) {
            $this->{$prop->getName()} = [];
            return;
        }

        /** @var Model $targetClass */
        $targetClass = $rel->target;
        $targetTable = $targetClass::tableName();

        // 1. Buscar en la tabla pivote todos los IDs de la entidad target
        $sqlPivot = "SELECT `{$rel->relatedPivotKey}` as related_id
                     FROM `{$rel->pivot}`
                     WHERE `{$rel->foreignPivotKey}` = ?";
        $stmtPivot = self::$pdo->prepare($sqlPivot);
        $stmtPivot->execute([$localKeyValue]);
        $pivotRows = $stmtPivot->fetchAll(\PDO::FETCH_COLUMN);

        if (count($pivotRows) === 0) {
            $this->{$prop->getName()} = [];
            return;
        }

        // 2. Buscar en la tabla $targetTable esos IDs
        $inPlaceholder = rtrim(str_repeat('?,', count($pivotRows)), ',');
        $sqlTarget = "SELECT * FROM `$targetTable`
                      WHERE `{$rel->relatedKey}` IN ($inPlaceholder)";
        $stmtTarget = self::$pdo->prepare($sqlTarget);
        $stmtTarget->execute($pivotRows);
        $targetRows = $stmtTarget->fetchAll(\PDO::FETCH_ASSOC);

        $relatedInstances = [];
        foreach ($targetRows as $row) {
            $instance = new $targetClass();
            foreach ($row as $col => $val) {
                if (property_exists($instance, $col)) {
                    $instance->$col = $val;
                }
            }
            $relatedInstances[] = $instance;
        }

        $this->{$prop->getName()} = $relatedInstances;
    }
}