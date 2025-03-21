<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute]
class Many2One
{
    /**
     * @param string $target Entidad/PHP class a la que apunta (ej. 'App\Models\User')
     * @param string $foreignKey Nombre de la columna local que guarda la FK (ej. 'user_id')
     * @param string $ownerKey Nombre de la columna en la tabla destino (ej. 'id')
     */
    public function __construct(
        public string $target,
        public string $foreignKey,
        public string $ownerKey = 'id',
        public bool $onDeleteCascade = false
    ) {}
}