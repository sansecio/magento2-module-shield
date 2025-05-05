<?php

namespace Sansec\Shield\Model;

use Magento\Framework\Serialize\SerializerInterface;

class Serializer implements SerializerInterface
{
    public function serialize($data)
    {
        $result = json_encode($data, JSON_INVALID_UTF8_SUBSTITUTE);
        if (false === $result) {
            throw new \InvalidArgumentException("Unable to serialize value. Error: " . json_last_error_msg());
        }
        return $result;
    }

    public function unserialize($string)
    {
        if ($string === null) {
            throw new \InvalidArgumentException(
                'Unable to unserialize value. Error: Parameter must be a string type, null given.'
            );
        }
        $result = json_decode($string, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException("Unable to unserialize value. Error: " . json_last_error_msg());
        }
        return $result;
    }
}
