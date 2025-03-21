<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Many2Many
{
    /**
     * @param string      $targetEntity         Clase/entidad del lado opuesto
     * @param string|null $joinTable            Tabla intermedia
     * @param string|null $joinColumn           Columna local en la tabla intermedia
     * @param string|null $inverseJoinColumn    Columna de la otra entidad en la tabla intermedia
     */
    public function __construct(
        public string $targetEntity,
        public ?string $joinTable = null,
        public ?string $joinColumn = null,
        public ?string $inverseJoinColumn = null
    ) {
    }
}
