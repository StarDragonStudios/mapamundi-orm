<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute]
class Many2Many
{
    /**
     * @param string $target   Clase relacionada (ej. 'App\Models\Role')
     * @param string $pivot    Nombre de la tabla pivote (ej. 'users_roles')
     * @param string $foreignPivotKey  Columna en la tabla pivote que apunta a este modelo (ej. 'user_id')
     * @param string $relatedPivotKey  Columna en la tabla pivote que apunta a la clase $target (ej. 'role_id')
     * @param string $localKey         PK en la tabla actual (ej. 'id')
     * @param string $relatedKey       PK en la tabla $target (ej. 'id')
     */
    public function __construct(
        public string $target,
        public string $pivot,
        public string $foreignPivotKey,
        public string $relatedPivotKey,
        public string $localKey = 'id',
        public string $relatedKey = 'id',
        public bool $onDeleteCascade = false
    ) {}
}
