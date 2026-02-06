<?php
/**
 * StudyWS Backend - Router
 *
 * - Map incoming HTTP requests (method + path) to a handler.
 * - In-memory route table.
 */

class Router
{   
    // Request method and path.
    private string $method;
    // Request path (URL path only, no query string).
    private string $path;
    // Route table: method => [ path => [handler, protected] ]
    private array $routes = [];

    /**
     * Constructor: captures request method and path, and registers routes.
     */
    public function __construct()
    {
        // Capture request method (GET/POST/...)
        $this->method = $_SERVER['REQUEST_METHOD'];

        // Capture only the path part of the URL (no query string)
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        // Remove /api prefix if present (when Nginx proxies /api/* to PHP)
        if (strpos($this->path, '/api') === 0) {
            $this->path = substr($this->path, 4); // "/api" length = 4
        }

        // Register all routes at startup
        $this->registerRoutes();
    }

    /**
     * Registers all application routes.
     */
    private function registerRoutes(): void
    {
        // API
        $this->post('/auth/register', 'AuthController@register');
        $this->post('/auth/login', 'AuthController@login');
        $this->post('/auth/refresh', 'AuthController@refresh');
        $this->post('/auth/logout', 'AuthController@logout', true);

        $this->get('/health', fn () => ['service' => 'php-backend', 'status' => 'ok']);

        // Frontend entry
        $this->get('/', fn () => $this->serveFile(__DIR__ . '/../../frontend/index.html', 'text/html'));

        // Static frontend assets: /frontend/qualunque-cosa -> serveFrontendAsset
        $this->get('/frontend/{path}', function ($path) {
            return $this->serveFrontendAsset($path);
        });

        // SPA fallback: /qualunque-cosa -> index.html
        $this->get('/{path}', fn () => $this->serveFile(__DIR__ . '/../../frontend/index.html', 'text/html'));
    }

    /**
     * Register a GET route.
     *
     * @param string $path Exact route path (e.g., "/health").
     * @param mixed $handler Callable or "Controller@action".
     * @param bool $protected Whether the route requires authentication.
     */
    private function get(string $path, $handler, bool $protected = false): void
    {
        $this->routes['GET'][$path] = [
            'handler' => $handler,
            'protected' => $protected,
        ];
    }

    
    /**
     * Register a POST route.
     *
     * @param string $path Exact route path (e.g., "/auth/login").
     * @param mixed $handler Callable or "Controller@action".
     * @param bool $protected Whether the route requires authentication.
     */
    private function post(string $path, $handler, bool $protected = false): void
    {
        $this->routes['POST'][$path] = [
            'handler' => $handler,
            'protected' => $protected,
        ];
    }

    /**
     * Dispatch the request to a matching route handler.
     *
     * - Looks up the route table by HTTP method.
     * - Performs an exact path match.
     * - Returns 404 if no route matches.
     */
    public function dispatch(): void
    {
        // No routes registered for this HTTP method
        if (!isset($this->routes[$this->method])) {
            $this->notFound();
            return;
        }

        $methodRoutes = $this->routes[$this->method];

        // Exact match only
        if (isset($methodRoutes[$this->path])) {
            $route = $methodRoutes[$this->path];
            $this->handle($route);
            return;
        }

        $this->notFound();
    }

    /**
     * Handles a matched route:
     * - Enforces rate limiting for protected endpoints
     * - Enforces authentication when needed
     * - Executes the handler (callable or controller action)
     */
    private function handle(array $route): void
    {
        // Rate limiting gate (for specific endpoints vulnerable to brute force)
        if (!RateLimitMiddleware::checkLimit($this->path)) {
            RateLimitMiddleware::sendTooManyRequests();
            return;
        }

        // Authentication gate (only for protected routes)
        if ($route['protected']) {
            // Load AuthMiddleware
            require_once __DIR__ . '/middleware/AuthMiddleware.php';

            if (!AuthMiddleware::authenticate()) {
                AuthMiddleware::sendUnauthorized();
                return; 
            }
        }

        $handler = $route['handler'];

        // Callable route (closure)
        if (is_callable($handler)) {
            $response = call_user_func($handler);

            // Router sends JSON for simple callbacks
            $this->sendJsonResponse($response);
            return;
        }

        // Controller@action routes
        if (is_string($handler) && strpos($handler, '@') !== false) {
            [$controllerName, $actionName] = explode('@', $handler, 2);

            // Load controller
            require_once __DIR__ . '/controllers/' . $controllerName . '.php';

            // Parse JSON body and pass it to controller constructor
            $controller = new $controllerName($this->getJsonBody());

            // Execute action and send standardized controller response
            $controller->$actionName();
            $controller->sendResponse();
            return;
        }

        // Fallback if handler type is unsupported
        $this->notFound();
    }

    /**
     * Reads and parses JSON from the request body (php://input).
     *
     * Returns:
     * - [] if empty body
     * - [] if invalid JSON (and logs error)
     * - associative array otherwise
     */
    private function getJsonBody(): array
    {   
        // Read raw input
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return [];
        }

        // Decode JSON with error handling
        try {
            return json_decode($input, true, 512, JSON_THROW_ON_ERROR) ?? [];
        } 
        catch (JsonException $e) {
            // Invalid JSON should not crash the server; log and continue with empty input
            error_log('JSON decode error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Sends a 404 JSON response for unknown routes.
     */
    private function notFound(): void
    {
        http_response_code(404);

        // Send standardized JSON error response
        $this->sendJsonResponse([
            'error' => 'Not Found',
            'path' => $this->path,
        ]);
    }

    /**
     * Sends a JSON response.
     *
     * @param array $data Any associative array to encode as JSON.
     */
    private function sendJsonResponse(array $data): void
    {
        header('Content-Type: application/json');

        // Keep output clean (especially for URLs/tokens) and handle unicode nicely.
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
    }

    /**
     * Serves a static file with appropriate headers.
     *
     * @param string $filepath Path to the file
     * @param string $contentType MIME type of the file
     */
    private function serveFile(string $filepath, string $contentType): void
    {
        // Check if file exists and is not a directory
        if (!file_exists($filepath)) {
            $this->notFound();
            return;
        }

        // Set content type header and output file contents
        header('Content-Type: ' . $contentType);
        readfile($filepath);
    }

    /**
     * Serves a frontend asset file from the frontend directory.
     *
     * @param string $path Path to the asset (relative to frontend directory)
     */
    private function serveFrontendAsset(string $path): void
    {
        // Sanitize path to prevent directory traversal
        $path = str_replace('..', '', $path);
        $filepath = __DIR__ . '/../../frontend/' . $path;

        // Check if file exists and is not a directory
        if (!file_exists($filepath) || is_dir($filepath)) {
            $this->notFound();
            return;
        }

        // Determine content type based on file extension
        $extension = pathinfo($filepath, PATHINFO_EXTENSION);
        $contentType = $this->getMimeType($extension);

        header('Content-Type: ' . $contentType);
        readfile($filepath);
    }

    /**
     * Gets MIME type based on file extension.
     *
     * @param string $extension File extension (without dot)
     * @return string MIME type
     */
    private function getMimeType(string $extension): string
    {
        // Common MIME types 
        $mimeTypes = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            'eot' => 'application/vnd.ms-fontobject',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }
}
