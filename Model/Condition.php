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

    public function __construct(array $data)
    {
        $this->target = $data['target'];
        $this->type = $data['type'];
        $this->value = $data['value'];
        $this->preprocess = $data['preprocess'] ?? [];
    }
}
