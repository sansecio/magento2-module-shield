<?php

namespace Sansec\Shield\Model;

class Condition
{
    public string $target;
    public string $type;
    public string $value;
    public array $preprocess = [];

    public function __construct(array $data)
    {
        $this->target = $data['target'];
        $this->type = $data['type'];
        $this->value = $data['value'];
        $this->preprocess = $data['preprocess'] ?? [];
    }
}
