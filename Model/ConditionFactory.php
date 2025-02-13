<?php

namespace Sansec\Shield\Model;

class ConditionFactory
{
    /**
     * @param array $data
     * @return Condition
     */
    public function create(array $data = [])
    {
        return new Condition(
            $data['target'],
            $data['type'],
            $data['value'],
            $data['preprocess'] ?? []
        );
    }
}
