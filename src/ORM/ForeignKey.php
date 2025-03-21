<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ForeignKey
{
    /**
     * @param string|null $referenceTable Nombre de la tabla referenciada
     * @param string|null $referenceColumn Columna en la tabla referenciada
     * @param bool $onDeleteCascade
     */
    public function __construct(
        public ?string $referenceTable = null,
        public ?string $referenceColumn = null,
        public bool   $onDeleteCascade = false
    ) {}
}