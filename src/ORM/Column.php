<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class Column
{
    public function __construct(
        public ?string $type = null,
        public ?string $name = null,
        public bool   $primaryKey = false,
        public bool   $autoIncrement = false,
        public int    $length = 0,
        public bool   $nullable = false,
        public string $default = '',
        public bool   $unique = false,
    ) {}
}