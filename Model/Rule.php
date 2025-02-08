<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Model\ConditionFactory;

class Rule
{
    /** @var string */
    public $action;

    /** @var Condition[] */
    public $conditions = [];

    /** @var IP */
    private $ip;

    public function __construct(
        ConditionFactory $conditionFactory,
        IP $ip,
        string $action,
        array $conditions = []
    ) {
        $this->action = $action;
        $this->conditions = array_map(function ($condition) use ($conditionFactory) {
            return $conditionFactory->create($condition);
        }, $conditions);
    }

    private function extractTargetValue(string $target, RequestInterface $request): mixed
    {
        $parts = explode('.', $target);
        if ($parts[0] !== 'req') {
            return '';
        }

        switch ($parts[1]) {
            case 'body':
                return $request->getContent();
            case 'path':
                return $request->getRequestUri();
            case 'method':
                return $request->getMethod();
            case 'header':
                return count($parts) === 3 ? $request->getHeader($parts[2], '') : '';
            case 'ip':
                return $this->ip->collectRequestIPs();
            default:
                return '';
        }
    }

    public function matches(RequestInterface $request): bool
    {
        foreach ($this->conditions as $condition) {
            $value = $this->extractTargetValue($condition->target, $request);

            if (is_string($value)) {
                foreach ($condition->preprocess as $process) {
                    switch ($process) {
                        case 'urldecode':
                            $value = urldecode($value);
                            break;
                        case 'strtolower':
                            $value = strtolower($value);
                            break;
                        case 'strip_non_alpha':
                            $value = preg_replace('/[^a-zA-Z]/', '', $value);
                            break;
                    }
                }
            }

            $matches = false;
            switch ($condition->type) {
                case 'regex':
                    $matches = (bool)preg_match('/' . str_replace('/', '\/', $condition->value) . '/', $value);
                    break;
                case 'contains':
                    $matches = strpos($value, $condition->value) !== false;
                    break;
                case 'equals':
                    $matches = strcmp($value, $condition->value) === 0;
                    break;
                case 'ip':
                    $matches = in_array($condition->value, $value);
                    break;
                case 'network':
                    foreach ($value as $ip) {
                        if ($this->ip->ipMatchesCidr($ip, $condition->value)) {
                            $matches = true;
                            break;
                        }
                    }
                    break;
            }

            if (!$matches) {
                return false;
            }
        }

        return true;
    }
}
