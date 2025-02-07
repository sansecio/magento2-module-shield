<?php

namespace Sansec\Shield\Model;

class Condition
{
    /** @var string */
    public $target;

    /** @var string */
    public $type;

    /** @var string */
    public $value;

    /** @var array */
    public $preprocess = [];

    public function __construct(string $target, string $type, string $value, array $preprocess = [])
    {
        $this->target = $target;
        $this->type = $type;
        $this->value = $value;
        $this->preprocess = $preprocess;
    }
}
