<?php

/**
 * SKSL Router
 *
 * A minimal front-controller router.
 * Maps HTTP method + URI path to a controller action.
 *
 * Usage:
 *   $router = new Router();
 *   $router->get('/services', [ServiceController::class, 'index']);
 *   $router->post('/api/login', [AuthController::class, 'login']);
 *   $router->dispatch();
 */

declare(strict_types=1);

class Router
{
    /** @var array<string, array<string, array{0: string, 1: string}>> */
    private array $routes = [];

    // ── Route registration ────────────────────────────────────────────────

    public function get(string $path, array|Closure $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, array|Closure $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, array|Closure $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, array|Closure $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    private function add(string $method, string $path, array|Closure $handler): void
    {
        $this->routes[$method][$path] = $handler;
    }

    // ── Dispatch ──────────────────────────────────────────────────────────

    public function dispatch(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $uri    = $this->getUri();

        // Support method override for PUT/DELETE from HTML forms
        if ($method === 'POST' && isset($_POST['_method'])) {
            $override = strtoupper($_POST['_method']);
            if (in_array($override, ['PUT', 'DELETE'], true)) {
                $method = $override;
            }
        }

        if (!isset($this->routes[$method])) {
            $this->notFound();
            return;
        }

        // Try exact match first
        if (isset($this->routes[$method][$uri])) {
            $this->callHandler($this->routes[$method][$uri], []);
            return;
        }

        // Try pattern matching for routes with parameters (e.g. /bookings/{id})
        foreach ($this->routes[$method] as $pattern => $handler) {
            $params = $this->matchPattern($pattern, $uri);
            if ($params !== null) {
                $this->callHandler($handler, $params);
                return;
            }
        }

        $this->notFound();
    }

    /**
     * Extract and normalize the request URI relative to the app base path.
     */
    private function getUri(): string
    {
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        $uri = rawurldecode($uri ?? '/');

        // Remove the script base path if app is in a subdirectory
        $base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }

        $uri = '/' . ltrim($uri, '/');

        // Remove trailing slash (unless it's just /)
        if ($uri !== '/') {
            $uri = rtrim($uri, '/');
        }

        return $uri;
    }

    /**
     * Match a route pattern with named parameters.
     * Pattern: /bookings/{id}  →  returns ['id' => '123']
     *
     * @return array<string, string>|null  Null if no match.
     */
    private function matchPattern(string $pattern, string $uri): ?array
    {
        // Convert {param} to a named capture group
        $regex = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $uri, $matches)) {
            // Return only named matches
            return array_filter($matches, fn($k) => is_string($k), ARRAY_FILTER_USE_KEY);
        }

        return null;
    }

    /**
     * Call a route handler — either a Closure or a [ClassName, methodName] array.
     *
     * @param array{0: string, 1: string}|Closure $handler
     * @param array<string, string>               $params  URL parameters
     */
    private function callHandler(array|Closure $handler, array $params): void
    {
        if ($handler instanceof Closure) {
            $handler($params);
            return;
        }

        [$class, $method] = $handler;

        if (!class_exists($class)) {
            error_log("Router: Controller class '$class' not found.");
            $this->serverError();
            return;
        }

        $controller = new $class();

        if (!method_exists($controller, $method)) {
            error_log("Router: Method '$method' not found on '$class'.");
            $this->serverError();
            return;
        }

        $controller->$method($params);
    }

    // ── Error responses ───────────────────────────────────────────────────

    private function notFound(): void
    {
        http_response_code(404);

        // If the request expects JSON, respond with JSON
        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Not found.']);
            return;
        }

        // Otherwise render a simple 404 page
        if (file_exists(__DIR__ . '/app/views/pages/404.php')) {
            require __DIR__ . '/app/views/pages/404.php';
        } else {
            echo '<h1>404 — Page Not Found</h1>';
        }
    }

    private function serverError(): void
    {
        http_response_code(500);

        if ($this->isApiRequest()) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Internal server error.']);
            return;
        }

        echo '<h1>500 — Internal Server Error</h1>';
    }

    private function isApiRequest(): bool
    {
        $uri    = $this->getUri();
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_starts_with($uri, '/api') || str_contains($accept, 'application/json');
    }
}
