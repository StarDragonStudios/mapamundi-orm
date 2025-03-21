<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Exception;
use PDO;
use ReflectionClass;
use ReflectionException;
use Sdstudios\MapamundiOrm\ORM\Column;

readonly class SchemaManager
{
    public function __construct(private PDO $pdo) { }

    /**
     * Sincroniza una tabla para la clase $modelClass.
     * - Crea las columnas definidas con #[Column].
     * - Crea las FKs definidas con #[ManyToOne] o #[OneToOne], si la FK está en ESTA tabla.
     * - Crea tablas pivote para #[ManyToMany].
     * @throws ReflectionException
     */
    public function syncTable(string $modelClass): void
    {
        /** @var Model $modelClass */
        $tableName = $modelClass::tableName();
        $refClass = new ReflectionClass($modelClass);

        $columnsSql = [];
        $constraints = [];  // para FOREIGN KEY, etc.

        // 1. Recorrer propiedades para Column
        foreach ($refClass->getProperties() as $prop) {
            // (a) Columns
            $colAttrs = $prop->getAttributes(Column::class);
            if (count($colAttrs) > 0) {
                $colMeta = $colAttrs[0]->newInstance();
                $propName = $prop->getName();
                $colDef = $this->buildColumnDefinition($propName, $colMeta);
                $columnsSql[] = $colDef;
            }

            // (b) Relaciones: ManyToOne, OneToOne => Generan FKs en ESTA tabla (si la FK existe aquí)
            $m2oAttrs = $prop->getAttributes(Many2One::class);
            if (count($m2oAttrs) > 0) {
                /** @var Many2One $rel */
                $rel = $m2oAttrs[0]->newInstance();
                // La FK se supone que está en ESTA tabla, en $rel->foreignKey
                // Apunta a $rel->target::tableName() .($rel->ownerKey)
                $this->addForeignKeyConstraint(
                    $constraints,
                    $tableName,
                    $rel->foreignKey,
                    $rel->target::tableName(),
                    $rel->ownerKey,
                    $rel->onDeleteCascade
                );
            }

            $o2oAttrs = $prop->getAttributes(One2One::class);
            if (count($o2oAttrs) > 0) {
                /** @var One2One $rel */
                $rel = $o2oAttrs[0]->newInstance();
                // Igual que ManyToOne, si la FK está en la tabla actual
                // (depende de la semántica, pero asumimos $rel->foreignKey está aquí)
                $this->addForeignKeyConstraint(
                    $constraints,
                    $tableName,
                    $rel->foreignKey,
                    $rel->target::tableName(),
                    $rel->localKey,
                    $rel->onDeleteCascade
                );
            }

            // (c) OneToMany => la FK está en la tabla hija, NO la creamos aquí
            // (d) ManyToMany => creamos la tabla pivote
            $m2mAttrs = $prop->getAttributes(Many2Many::class);
            if (count($m2mAttrs) > 0) {
                /** @var Many2Many $rel */
                $rel = $m2mAttrs[0]->newInstance();
                $this->createPivotTable($modelClass, $rel);
            }
        }

        // 2. Generar la sentencia CREATE TABLE para la tabla principal
        //    Agregar constraints (FKs) al final
        $createSql = "CREATE TABLE IF NOT EXISTS `$tableName` (\n  "
            . implode(",\n  ", array_merge($columnsSql, $constraints))
            . "\n) ENGINE=InnoDB;";

        // 3. Ejecutar
        $this->pdo->exec($createSql);
    }

    /**
     * Construye la parte SQL de la definición de una columna (p. ej. `id INT NOT NULL AUTO_INCREMENT PRIMARY KEY`).
     */
    private function buildColumnDefinition(string $propName, Column $colMeta): string
    {
        $sqlType = match ($colMeta->type) {
            'int'      => 'INT',
            'varchar'  => "VARCHAR({$colMeta->length})",
            'text'     => 'TEXT',
            default    => strtoupper($colMeta->type),
        };

        $colDef = "`$propName` $sqlType";
        $colDef .= $colMeta->nullable ? ' NULL' : ' NOT NULL';

        if ($colMeta->default !== '') {
            $colDef .= " DEFAULT '{$colMeta->default}'";
        }
        if ($colMeta->unique) {
            $colDef .= ' UNIQUE';
        }
        if ($colMeta->primaryKey) {
            $colDef .= ' PRIMARY KEY';
        }
        if ($colMeta->autoIncrement) {
            $colDef .= ' AUTO_INCREMENT';
        }

        return $colDef;
    }

    /**
     * Añade una definición de FOREIGN KEY al array $constraints.
     */
    private function addForeignKeyConstraint(
        array &$constraints,
        string $tableName,
        string $localCol,
        string $refTable,
        string $refCol,
        bool $onDeleteCascade
    ): void {
        // Nombre arbitrario del constraint
        $constraintName = "fk_{$tableName}_{$localCol}_{$refTable}_{$refCol}";

        $fkDef = "CONSTRAINT `$constraintName` FOREIGN KEY (`$localCol`) "
            . "REFERENCES `$refTable`(`$refCol`)";
        if ($onDeleteCascade) {
            $fkDef .= " ON DELETE CASCADE";
        }
        $constraints[] = $fkDef;
    }

    /**
     * Crea la tabla pivote para ManyToMany, si no existe.
     * Ej: CREATE TABLE roles_users(
     *        user_id INT,
     *        role_id INT,
     *        PRIMARY KEY(user_id, role_id),
     *        FOREIGN KEY ...
     *      )
     */
    private function createPivotTable(string $modelClass, Many2Many $rel): void
    {
        // Nombre de la tabla pivote
        $pivotTable = $rel->pivot;

        // La PK local (ej. 'id' en el modelo actual)
        $localKey = $rel->localKey;
        // La PK en la tabla destino
        $relatedKey = $rel->relatedKey;

        // Nombre de la tabla de la otra clase
        $relatedTable = $rel->target::tableName();

        // Nombre de la tabla actual
        /** @var Model $modelClass */
        $tableName = $modelClass::tableName();

        // Columnas en la pivote: foreignPivotKey, relatedPivotKey
        // Ej. user_id INT NOT NULL, role_id INT NOT NULL
        $pivotCols = [];
        $pivotCols[] = "`{$rel->foreignPivotKey}` INT NOT NULL";
        $pivotCols[] = "`{$rel->relatedPivotKey}` INT NOT NULL";

        // Primary key compuesta
        $pivotPK = "PRIMARY KEY (`{$rel->foreignPivotKey}`, `{$rel->relatedPivotKey}`)";

        // Foreign keys
        $constraints = [];

        // FK a la tabla actual
        $constraints[] = "CONSTRAINT `fk_{$pivotTable}_{$rel->foreignPivotKey}_{$tableName}_{$localKey}` "
            . "FOREIGN KEY (`{$rel->foreignPivotKey}`) "
            . "REFERENCES `{$tableName}`(`{$localKey}`)"
            . ($rel->onDeleteCascade ? " ON DELETE CASCADE" : "");

        // FK a la tabla destino
        $constraints[] = "CONSTRAINT `fk_{$pivotTable}_{$rel->relatedPivotKey}_{$relatedTable}_{$relatedKey}` "
            . "FOREIGN KEY (`{$rel->relatedPivotKey}`) "
            . "REFERENCES `{$relatedTable}`(`{$relatedKey}`)"
            . ($rel->onDeleteCascade ? " ON DELETE CASCADE" : "");

        // Construir la sentencia CREATE TABLE (simplificado)
        $createSql = "CREATE TABLE IF NOT EXISTS `$pivotTable` (\n  "
            . implode(",\n  ", array_merge($pivotCols, [$pivotPK], $constraints))
            . "\n) ENGINE=InnoDB;";

        $this->pdo->exec($createSql);
    }
}