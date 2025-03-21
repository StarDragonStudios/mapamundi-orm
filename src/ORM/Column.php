<?php

namespace Sdstudios\MapamundiOrm\ORM;

use Attribute;

#[Attribute]
class Column
{
    public function __construct(
        public string $type,
        public bool   $primaryKey = false,
        public bool   $autoIncrement = false,
        public int    $length = 0,
        public bool   $nullable = false,
        public string $default = '',
        public bool   $unique = false
    ) {}
}