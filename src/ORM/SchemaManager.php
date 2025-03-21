<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Exception;
use PDOException;
use ReflectionException;
use Sdstudios\MapamundiOrm\Database\DBCore;
use ReflectionClass;
use ReflectionProperty;

/**
 * Se encarga de crear y/o actualizar las tablas basándose en la información
 * obtenida de los atributos Entity, Column, ForeignKey, etc.
 */
class SchemaManager
{
    /**
     * Crea la tabla (si no existe) para una clase de entidad dada.
     *
     * @param string $entityClass Nombre FQCN de la clase (ej: App\Models\User)
     *
     * @return bool
     * @throws ReflectionException
     * @throws Exception
     */
    public static function createTableFromEntity(string $entityClass): bool
    {
        // 1. Obtener la meta-información de la clase
        $reflection = new ReflectionClass($entityClass);

        // Verificar si realmente está anotada con #[Entity]
        $entityAttr = $reflection->getAttributes(Entity::class);
        if (empty($entityAttr)) {
            // No es una entidad, no hacemos nada
            return false;
        }

        // Instanciamos la metadata del atributo Entity
        /** @var Entity $entityMeta */
        $entityMeta = $entityAttr[0]->newInstance();
        // Si definió tableName, lo tomamos; si no, derivamos de la clase
        $tableName = $entityMeta->tableName ?? self::deriveTableName($entityClass);

        // 2. Recorrer las propiedades para buscar #[Column] y #[ForeignKey]
        $columns = [];
        $foreignKeys = [];

        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);
        foreach ($properties as $prop) {
            // Buscar la meta Column
            $colAttr = $prop->getAttributes(Column::class);
            if (!empty($colAttr)) {
                /** @var Column $colMeta */
                $colMeta = $colAttr[0]->newInstance();

                // Respetar el nombre si se definió; si no, usar el nombre de la propiedad
                $colName = $colMeta->name ?? $prop->getName();

                $columns[] = [
                    'name'          => $colName,
                    'type'          => $colMeta->type,
                    'length'        => $colMeta->length,
                    'nullable'      => $colMeta->nullable,
                    'primaryKey'    => $colMeta->primaryKey,
                    'autoIncrement' => $colMeta->autoIncrement,
                ];
            }

            // Buscar la meta ForeignKey (si la hubiese)
            $fkAttr = $prop->getAttributes(ForeignKey::class);
            if (!empty($fkAttr)) {
                /** @var ForeignKey $fkMeta */
                $fkMeta = $fkAttr[0]->newInstance();

                // El nombre de la columna local podría inferirse de Column o de la propiedad
                $fkColName = null;
                if (!empty($colAttr)) {
                    // Reutiliza la info de la columna
                    $fkColName = $columns[count($columns) - 1]['name'] ?? $prop->getName();
                } else {
                    // Si no hay #[Column], usa el nombre de la propiedad
                    $fkColName = $prop->getName();
                }

                // Añadir a la lista de FKs
                $foreignKeys[] = [
                    'columnName'       => $fkColName,
                    'referenceTable'   => $fkMeta->referenceTable,
                    'referenceColumn'  => $fkMeta->referenceColumn
                ];
            }
        }

        // 3. Generar la sentencia SQL: CREATE TABLE ...
        $sql = self::buildCreateTableSQL($tableName, $columns, $foreignKeys);

        // 4. Ejecutar en la base de datos
        $conn = DBCore::getInstance()->getConnection();

        // Podrías chequear si la tabla ya existe y tal vez "alterarla" en lugar de crearla.
        // Para simplificar, aquí solo hacemos CREATE TABLE IF NOT EXISTS.
        try {
            $conn->exec($sql);
            return true;
        } catch (PDOException $e) {
            // Maneja el error a tu manera
            echo "Error creando tabla '$tableName': " . $e->getMessage();
            return false;
        }
    }

    /**
     * Construye la sentencia CREATE TABLE IF NOT EXISTS a partir de la información de columnas y fks.
     */
    private static function buildCreateTableSQL(
        string $tableName,
        array $columns,
        array $foreignKeys
    ): string {
        $columnsSQL = [];

        // 1. Definir columnas
        $primaryKeys = [];
        foreach ($columns as $col) {
            $typeSQL = strtoupper($col['type'] ?? 'VARCHAR');
            $length = $col['length'] ? "({$col['length']})" : '';

            $colDef = "`{$col['name']}` $typeSQL$length";

            if (!$col['nullable']) {
                $colDef .= " NOT NULL";
            }
            if ($col['autoIncrement']) {
                $colDef .= " AUTO_INCREMENT";
            }
            $columnsSQL[] = $colDef;

            if ($col['primaryKey']) {
                $primaryKeys[] = $col['name'];
            }
        }

        // 2. Definir PRIMARY KEY
        if (!empty($primaryKeys)) {
            $pkList = array_map(fn($pk) => "`$pk`", $primaryKeys);
            $columnsSQL[] = "PRIMARY KEY (" . implode(', ', $pkList) . ")";
        }

        // 3. Definir Foreign Keys
        foreach ($foreignKeys as $fk) {
            if (!$fk['referenceTable'] || !$fk['referenceColumn']) {
                continue;
            }
            $columnsSQL[] = "FOREIGN KEY (`{$fk['columnName']}`)
                             REFERENCES `{$fk['referenceTable']}`(`{$fk['referenceColumn']}`)";
        }

        // 4. Construir la sentencia final
        return "CREATE TABLE IF NOT EXISTS `$tableName` (\n  "
            . implode(",\n  ", $columnsSQL)
            . "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    }

    /**
     * Deriva el nombre de la tabla a partir del nombre de la clase,
     * en caso de que no se haya especificado en el atributo Entity.
     */
    private static function deriveTableName(string $className): string
    {
        $parts = explode('\\', $className);
        return strtolower(end($parts));
    }

    /**
     * (Opcional) Para drop de tabla, etc.
     * @throws Exception
     */
    public static function dropTable(string $tableName): bool
    {
        $conn = DBCore::getInstance()->getConnection();
        try {
            $conn->exec("DROP TABLE IF EXISTS `$tableName`");
            return true;
        } catch (PDOException $e) {
            echo "Error al eliminar la tabla '$tableName': " . $e->getMessage();
            return false;
        }
    }
}