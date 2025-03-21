<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Many2One
{
    /**
     * @param string      $targetEntity    Clase/entidad a la que se referencia
     * @param string|null $foreignKeyName  Nombre de la columna en la BD que actúa como FK
     */
    public function __construct(
        public string $targetEntity,
        public ?string $foreignKeyName = null
    ) {
    }
}