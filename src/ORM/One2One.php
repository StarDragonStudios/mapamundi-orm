<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class One2One
{
    /**
     * @param string $targetEntity  Clase/entidad a la que hace referencia
     * @param bool   $ownerSide     Indica si esta clase "posee" la FK (true) o si está en la otra parte (false)
     */
    public function __construct(
        public string $targetEntity,
        public bool $ownerSide = true
    ) {
    }
}