<?php

namespace Sansec\Shield\Test;

use Magento\Framework\App\RequestInterface;

// Stub because Mock tracks calls and exhausts mem
class RequestStub implements RequestInterface
{
    private $content;
    private $method;
    private $uri;
    private $headers;
    private $params;
    private $cookies;
    private $post;

    public function __construct(
        string $content = '',
        string $method  = 'POST',
        string $uri     = '/test',
        array $headers  = [],
        array $params   = [],
        array $cookies  = [],
        array $post     = [],
    ) {
        $this->content = $content;
        $this->method = $method;
        $this->uri = $uri;
        $this->headers = $headers;
        $this->params = $params;
        $this->cookies = $cookies;
        $this->post = new \Laminas\Stdlib\Parameters($post);
    }

    public function getContent()
    {
        return $this->content;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getRequestUri()
    {
        return $this->uri;
    }

    public function getHeader($name)
    {
        return $this->headers[$name] ?? '';
    }

    public function getModuleName()
    {
        return '';
    }

    public function setModuleName($name)
    {
        return $this;
    }

    public function getActionName()
    {
        return '';
    }

    public function setActionName($name)
    {
        return $this;
    }

    public function getControllerName()
    {
        return '';
    }

    public function setControllerName($name)
    {
        return $this;
    }

    public function getParam($key, $default = null)
    {
        return $this->params[$key] ?? $default;
    }

    public function setParams(array $params)
    {
        return $this;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getCookie($name, $default = null)
    {
        return $this->cookies[$name] ?? $default;
    }

    public function isSecure()
    {
        return false;
    }

    public function getPost()
    {
        return $this->post;
    }
}
