<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute]
class One2Many
{
    /**
     * @param string $target Clase hija
     * @param string $foreignKey Columna en la tabla hija que apunta a la PK del padre
     * @param string $localKey Nombre de la columna PK en la tabla padre (ej. 'id')
     */
    public function __construct(
        public string $target,
        public string $foreignKey,
        public string $localKey = 'id',
        public bool $onDeleteCascade = false
    ) {}
}