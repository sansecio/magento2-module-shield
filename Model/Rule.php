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
        $this->ip = $ip;
        foreach ($conditions as $condition) {
            $this->conditions[] = $conditionFactory->create($condition);
        }
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

    private function preprocessTargetValue(string $value, Condition $condition): string
    {
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
                case 'html_entity_decode':
                    $value = html_entity_decode($value);
                    break;
                case 'rawurldecode':
                    $value = rawurldecode($value);
                    break;
                case 'hex2bin':
                    $value = hex2bin($value);
                    break;
                case 'strip_tags':
                    $value = strip_tags($value);
                    break;
            }
        }
        return $value;
    }

    private function targetValueMatchesCondition(mixed $value, Condition $condition): bool
    {
        $matches = false;
        switch ($condition->type) {
            case 'regex':
                $matches = (bool)preg_match('/' . str_replace('/', '\/', $condition->value) . '/', $value);
                break;
            case 'contains':
                $matches = strpos($value, $condition->value) !== false;
                break;
            case 'equals':
                if (is_array($value)) {
                    $matches = in_array($condition->value, $value);
                } else {
                    $matches = strcmp($value, $condition->value) === 0;
                }
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
        return $matches;
    }

    public function matches(RequestInterface $request): bool
    {
        foreach ($this->conditions as $condition) {
            $value = $this->extractTargetValue($condition->target, $request);
            if (is_string($value)) {
                $value = $this->preprocessTargetValue($value, $condition);
            }
            if (!$this->targetValueMatchesCondition($value, $condition)) {
                return false;
            }
        }
        return true;
    }
}
