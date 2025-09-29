<?php
namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): void { $this->map('GET', $pattern, $handler); }
    public function post(string $pattern, callable|array $handler): void { $this->map('POST', $pattern, $handler); }

    private function map(string $method, string $pattern, callable|array $handler): void
    {
        [$regex, $paramNames] = $this->compile($pattern);
        $this->routes[] = ['method'=>strtoupper($method), 'regex'=>$regex, 'params'=>$paramNames, 'handler'=>$handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $method = strtoupper($method);
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            if (preg_match($r['regex'], $path, $m)) {
                array_shift($m);
                $args = $this->bind($r['params'], $m);
                $this->invoke($r['handler'], $args);
                return;
            }
        }
        http_response_code(404); echo 'Not Found';
    }

    private function compile(string $pattern): array
    {
        $pattern = rtrim($pattern, '/');
        $params = [];
        $regex = preg_replace_callback('#\{([a-zA-Z_]\w*)(?::([^}]+))?\}#', function($m) use (&$params) {
            $params[] = $m[1];
            return '(' . ($m[2] ?? '[^/]+') . ')';
        }, $pattern);
        return ['#^' . $regex . '/?$#', $params];
    }

    private function bind(array $names, array $values): array
    {
        $args = [];
        foreach ($names as $i => $n) $args[$n] = $values[$i] ?? null;
        return $args;
    }

    private function invoke(callable|array $h, array $args): void
    {
        if (is_array($h)) { [$c,$m] = $h; (new $c())->$m(...array_values($args)); return; }
        $h(...array_values($args));
    }
}
