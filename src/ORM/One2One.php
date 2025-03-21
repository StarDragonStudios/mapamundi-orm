<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute]
class One2One
{
    public function __construct(
        public string $target,
        public string $foreignKey,
        public string $localKey = 'id',
        public bool $onDeleteCascade = false
    ) {}
}