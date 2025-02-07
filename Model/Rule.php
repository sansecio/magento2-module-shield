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

    public function __construct(ConditionFactory $conditionFactory, string $action, array $conditions = [])
    {
        $this->action = $action;
        $this->conditions = array_map(function ($condition) use ($conditionFactory) {
            return $conditionFactory->create($condition);
        }, $conditions);
    }

    private function extractTargetValue(string $target, RequestInterface $request): string
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
            default:
                return '';
        }
    }

    public function matches(RequestInterface $request): bool
    {
        foreach ($this->conditions as $condition) {
            $value = $this->extractTargetValue($condition->target, $request);

            foreach ($condition->preprocess as $process) {
                switch ($process) {
                    case 'urldecode':
                        $value = urldecode($value);
                    case 'strip_non_alpha':
                        $value = preg_replace('/[^a-zA-Z]/', '', $value);
                }
            }

            $matches = false;
            switch ($condition->type) {
                case 'regex':
                    $matches = (bool)preg_match('/' . str_replace('/', '\/', $condition->value) . '/i', $value);
                    break;
                case 'contains':
                    $matches = stripos($value, $condition->value) !== false;
                    break;
                case 'equals':
                    $matches = strcasecmp($value, $condition->value) === 0;
                    break;
            }

            if (!$matches) {
                return false;
            }
        }

        return true;
    }
}
