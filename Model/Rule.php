<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Sansec\Shield\Model\ConditionFactory;

class Rule
{
    public string $action;

    /** @var Condition[] */
    public array $conditions = [];

    public function __construct(ConditionFactory $conditionFactory, array $data)
    {
        $this->action = $data['action'];
        $this->conditions = array_map(fn($c) => $conditionFactory->create(['data' => $c]), $data['conditions'] ?? []);
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
        $matched = true;

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

            $matches = match ($condition->type) {
                'regex' => (bool)preg_match('/' . str_replace('/', '\/', $condition->value) . '/i', $value),
                'contains' => stripos($value, $condition->value) !== false,
                'equals' => strcasecmp($value, $condition->value) === 0,
                default => false
            };

            if (!$matches) {
                $matched = false;
                break;
            }
        }

        return $matched;
    }
}
