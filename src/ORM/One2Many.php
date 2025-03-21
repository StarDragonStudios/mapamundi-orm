<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class One2Many
{
    /**
     * @param string $targetEntity  Clase/entidad del lado "muchos"
     * @param string $mappedBy      El atributo en la entidad target que referencia a esta (inversa)
     */
    public function __construct(
        public string $targetEntity,
        public string $mappedBy
    ) {
    }
}