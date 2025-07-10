## Dumping requests

```php
function v($expression, $return = FALSE)
{
    $export = var_export($expression, TRUE);
    $patterns = [
        "/array \(/" => '[',
        "/^([ ]*)\)(,?)$/m" => '$1]$2',
        "/=>[ ]?\n[ ]+\[/" => '=> [',
        "/([ ]*)(\'[^\']+\') => ([\[\'])/" => '$1$2 => $3',
    ];
    $export = preg_replace(array_keys($patterns), array_values($patterns), $export);
    if ((bool)$return) return $export;
    else echo $export;
}
$r = [
    'content' => file_get_contents('php://input') ?? '',
    'method'  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
    'uri'     => $_SERVER['REQUEST_URI'] ?? '/',
    'headers' => getallheaders() ?? [],
    'params'  => array_merge($_GET ?? [], $_POST ?? []),
    'cookies' => $_COOKIE ?? [],
];
file_put_contents(
    '/tmp/request.log',
    v($r, true) . PHP_EOL,
    FILE_APPEND
);
```
