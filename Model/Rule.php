<?php

namespace Sansec\Shield\Model;

use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface as Logger;

class Rule
{
    /** @var string */
    public $action;

    /** @var Condition[] */
    public $conditions = [];

    /** @var IP */
    private $ip;

    /** @var Logger */
    private $logger;

    public function __construct(
        IP $ip,
        Logger $logger,
        string $action,
        array $conditions = []
    ) {
        $this->ip = $ip;
        $this->logger = $logger;
        $this->action = $action;
        $this->conditions = $conditions;
    }

    private function extractTargetValue(string $target, RequestInterface $request)
    {
        $parts = explode('.', $target);
        if ($parts[0] !== 'req') {
            return '';
        }

        switch ($parts[1]) {
            case 'body':
                return $request->getContent();
            case 'uri':
                return $request->getRequestUri();
            case 'method':
                return $request->getMethod();
            case 'header':
                return count($parts) === 3 ? $request->getHeader($parts[2], '') : '';
            case 'param':
                return count($parts) === 3 ? $request->getParam($parts[2], '') : '';
            case 'cookie':
                return count($parts) === 3 ? $request->getCookie($parts[2], '') : '';
            case 'files':
                return $request->getFiles()->toString();
            case 'post':
                return $request->getPost()->toString();
            case 'query':
                return $request->getQuery()->toString();
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
                case 'decode_unicode':
                    $value = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function ($m) {
                        return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UTF-16BE');
                    }, $value);
            }
        }
        return $value;
    }

    private function extractScalarValues($value): array
    {
        if (!is_array($value)) {
            return is_scalar($value) ? [(string)$value] : [];
        }

        $values = [];
        foreach ($value as $item) {
            foreach ($this->extractScalarValues($item) as $scalarValue) {
                $values[] = $scalarValue;
            }
        }
        return $values;
    }

    private function scalarValueMatchesCondition(string $value, Condition $condition): bool
    {
        if (strlen($value) > 0) {
            $value = $this->preprocessTargetValue($value, $condition);
        }

        switch ($condition->type) {
            case 'regex':
                $result = preg_match('/' . str_replace('/', '\/', $condition->value) . '/', $value);
                if ($result === false) {
                    $this->logPcreFailure($condition);
                }
                return (bool)$result;
            case 'contains':
                return strpos($value, $condition->value) !== false;
            case 'equals':
                return strcmp($value, $condition->value) === 0;
            default:
                return false;
        }
    }

    private function logPcreFailure(Condition $condition): void
    {
        $this->logger->warning('Regex condition failed with a PCRE error.', [
            'error' => function_exists('preg_last_error_msg') ? preg_last_error_msg() : preg_last_error(),
            'target' => $condition->target,
            'pattern' => $condition->value
        ]);
    }

    private function targetValueMatchesCondition($value, Condition $condition): bool
    {
        if ($condition->type === 'network') {
            if (!is_array($value)) {
                return false;
            }

            foreach ($value as $ip) {
                if ($this->ip->ipMatchesCidr($ip, $condition->value)) {
                    return true;
                }
            }
            return false;
        }

        foreach ($this->extractScalarValues($value) as $scalarValue) {
            if ($this->scalarValueMatchesCondition($scalarValue, $condition)) {
                return true;
            }
        }
        return false;
    }

    public function matches(RequestInterface $request): bool
    {
        try {
            foreach ($this->conditions as $condition) {
                $value = $this->extractTargetValue($condition->target, $request);
                if (empty($value) || !$this->targetValueMatchesCondition($value, $condition)) {
                    return false;
                }
            }
            return count($this->conditions) > 0;
        } catch (\Throwable $e) {
            $this->logger->warning('Failed matching rule.', [
                'message' => $e->getMessage(),
                'action' => $this->action,
                'conditions' => $this->conditions
            ]);
            return false;
        }
    }
}
