<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute]
class ForeignKey
{
    public function __construct(
        public string $refTable,
        public string $refColumn,
        public bool   $onDeleteCascade = false
    ) {}
}