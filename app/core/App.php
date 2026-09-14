<?php
declare(strict_types=1);

namespace App\Core;

use App\Core\Exceptions\BusinessException;
use App\Core\Exceptions\HttpException;
use App\Core\Exceptions\ValidationException;
use Throwable;

/**
 * Application kernel: bootstrap, dispatch, error handling.
 */
final class App
{
    private static ?App $instance = null;
    private Router $router;
    private Request $request;
    private bool $booted = false;

    public static function instance(): App
    {
        return self::$instance ??= new self();
    }

    public function boot(string $rootPath): self
    {
        if ($this->booted) {
            return $this;
        }
        require_once $rootPath . '/app/core/Env.php';
        require_once $rootPath . '/app/core/Config.php';
        require_once $rootPath . '/app/core/Autoloader.php';

        Env::load($rootPath . '/.env');
        Autoloader::register($rootPath . '/app');
        Config::boot($rootPath . '/config');
        require_once $rootPath . '/app/helpers/functions.php';

        date_default_timezone_set((string)Config::get('app.timezone', 'Asia/Tehran'));
        mb_internal_encoding('UTF-8');

        $debug = (bool)Config::get('app.debug', false);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
            if (!(error_reporting() & $no)) {
                return false;
            }
            throw new \ErrorException($str, 0, $no, $file, $line);
        });
        set_exception_handler(function (Throwable $e): void {
            $this->renderThrowable($e)->send();
        });
        register_shutdown_function(static function (): void {
            $err = error_get_last();
            if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                Logger::error('Fatal error', $err);
            }
        });

        $this->router  = new Router();
        $this->request = Request::capture();
        $this->booted  = true;
        return $this;
    }

    public function router(): Router
    {
        return $this->router;
    }

    public function request(): Request
    {
        return $this->request;
    }

    public function loadRoutes(string $routesPath): self
    {
        $router = $this->router;
        require $routesPath . '/web.php';
        require $routesPath . '/api.php';
        return $this;
    }

    public function run(): void
    {
        $this->handle($this->request)->send();
    }

    public function handle(Request $request): Response
    {
        try {
            Session::start($request->isSecure());

            // Redirect to installer when the app is not installed yet.
            $installed = is_file((string)Config::get('app.installed_flag'));
            $path      = $request->path();
            if (!$installed && !str_starts_with($path, '/install') && !str_starts_with($path, '/assets')) {
                return Response::redirect(url('/install'));
            }

            $matched = $this->router->match($request->method(), $path);
            foreach ($matched['params'] as $k => $v) {
                $request->setAttribute($k, $v);
            }

            $handler = $matched['handler'];
            $core    = function (Request $req) use ($handler, $matched): Response {
                return $this->callHandler($handler, $req, $matched['params']);
            };

            $pipeline = array_reduce(
                array_reverse($matched['middleware']),
                function (callable $next, string $mw): callable {
                    return function (Request $req) use ($mw, $next): Response {
                        $name  = $mw;
                        $args  = [];
                        if (str_contains($mw, ':')) {
                            [$name, $argStr] = explode(':', $mw, 2);
                            $args = explode(',', $argStr);
                        }
                        $class = str_contains($name, '\\')
                            ? $name
                            : 'App\\Middleware\\' . $name . (str_ends_with($name, 'Middleware') ? '' : 'Middleware');
                        /** @var \App\Middleware\MiddlewareInterface $obj */
                        $obj = new $class(...$args);
                        return $obj->handle($req, $next);
                    };
                },
                $core
            );

            return $pipeline($request);
        } catch (Throwable $e) {
            return $this->renderThrowable($e, $request);
        }
    }

    private function callHandler(mixed $handler, Request $request, array $params): Response
    {
        if (is_callable($handler)) {
            $result = $handler($request, ...array_values($params));
        } else {
            [$class, $method] = is_array($handler) ? $handler : explode('@', (string)$handler);
            if (!str_starts_with($class, 'App\\Controllers\\') && !str_starts_with($class, '\\')) {
                $class = 'App\\Controllers\\' . $class;
            }
            $class = ltrim($class, '\\');
            if (!class_exists($class)) {
                throw new HttpException(404, 'کنترلر یافت نشد.');
            }
            $controller = new $class();
            if (!method_exists($controller, $method)) {
                throw new HttpException(404, 'اکشن یافت نشد.');
            }
            $result = $controller->{$method}($request, ...array_values($params));
        }

        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result)) {
            return Response::json($result);
        }
        return Response::html((string)$result);
    }

    public function renderThrowable(Throwable $e, ?Request $request = null): Response
    {
        $request ??= $this->request ?? Request::capture();
        $debug = (bool)Config::get('app.debug', false);

        if ($e instanceof ValidationException) {
            Logger::info('Validation failed', ['path' => $request->path()]);
            if ($request->wantsJson()) {
                return Response::error('VALIDATION_ERROR', $e->getMessage(), 422, $e->errors());
            }
            Session::flashErrors($e->errors());
            Session::flashInput($request->all());
            Session::flash('error', $e->getMessage());
            $back = $request->header('Referer') ?: url('/');
            return Response::redirect($back);
        }

        if ($e instanceof BusinessException) {
            Logger::warning('Business rule violation', ['message' => $e->getMessage(), 'path' => $request->path()]);
            if ($request->wantsJson()) {
                return Response::error($e->errorCode(), $e->getMessage(), $e->status(), $e->details());
            }
            Session::flash('error', $e->getMessage());
            return Response::redirect($request->header('Referer') ?: url('/'));
        }

        if ($e instanceof HttpException) {
            $status = $e->status();
            if ($status >= 500) {
                Logger::error('HTTP error', ['status' => $status, 'message' => $e->getMessage()]);
            }
            if ($request->wantsJson()) {
                $code = match ($status) {
                    401 => 'UNAUTHENTICATED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    405 => 'METHOD_NOT_ALLOWED',
                    419 => 'CSRF_TOKEN_MISMATCH',
                    429 => 'TOO_MANY_REQUESTS',
                    default => 'ERROR',
                };
                return Response::error($code, $e->getMessage(), $status, $e->details());
            }
            return $this->errorPage($status, $e->getMessage());
        }

        Logger::error('Unhandled exception', [
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
            'trace'   => $debug ? $e->getTraceAsString() : null,
        ]);

        if ($request->wantsJson()) {
            return Response::error(
                'SERVER_ERROR',
                $debug ? $e->getMessage() : 'خطای داخلی سرور رخ داده است.',
                500
            );
        }
        return $this->errorPage(500, $debug ? $e->getMessage() : 'خطای داخلی سرور رخ داده است.', $debug ? $e : null);
    }

    public function errorPage(int $status, string $message, ?Throwable $e = null): Response
    {
        try {
            $html = View::render('errors/error', [
                'status'    => $status,
                'message'   => $message,
                'exception' => $e,
                'title'     => 'خطای ' . $status,
            ]);
        } catch (Throwable) {
            $html = '<!doctype html><html dir="rtl" lang="fa"><meta charset="utf-8">'
                  . '<title>خطای ' . $status . '</title><body style="font-family:sans-serif;text-align:center;padding:4rem">'
                  . '<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        }
        return Response::html($html, $status);
    }
}
