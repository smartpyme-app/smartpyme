<?php

namespace App\Exceptions;

use Fruitcake\Cors\CorsService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Exceptions\TokenExpiredException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     *
     * @throws \Exception
     */
    public function report(Throwable $exception)
    {
        // Filtrar warnings de deprecación de PHP 8.1+ con Laravel 8.0
        if ($exception instanceof \ErrorException && 
            strpos($exception->getMessage(), 'Implicitly marking parameter') !== false) {
            return;
        }
        
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $exception)
    {
        if ($request->wantsJson()) {
            if ($exception instanceof ValidationException) {
                $errors = $exception->validator->messages()->all();

                return $this->applyCorsToApi($request, response()->json(['error' => $errors, 'code' => 422], 422));
            }

            if ($exception instanceof ModelNotFoundException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404)
                );
            }

            if ($exception instanceof AuthenticationException) {
                return $this->applyCorsToApi(
                    $request,
                    $this->unauthenticated($request, $exception)
                );
            }

            if ($exception instanceof AuthorizationException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => 'No posee permisos para ejecutar esta acción handler.', 'code' => 403], 403)
                );
            }

            if ($exception instanceof NotFoundHttpException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => 'URL Invalida.', 'code' => 404], 404)
                );
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => 'Peticion no valida.', 'code' => 405], 405)
                );
            }

            if ($exception instanceof TokenExpiredException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => 'La sessión expiró, vuelva a iniciar sesión', 'code' => 401], 401)
                );
            }

            if ($exception instanceof JWTException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => $exception->getMessage(), 'code' => $exception])
                );
            }

            if ($exception instanceof QueryException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(['error' => $exception->getMessage(), 'code' => 500], 500)
                );
            }

            if ($exception instanceof HttpException) {
                return $this->applyCorsToApi(
                    $request,
                    response()->json(
                        ['error' => $exception->getMessage(), 'code' => $exception->getStatusCode()],
                        $exception->getStatusCode()
                    )
                );
            }
        }

        $parent = parent::render($request, $exception);

        if ($this->shouldApplyCorsToRequest($request)) {
            return $this->applyCorsToApi($request, $parent);
        }

        return $parent;
    }

    private function shouldApplyCorsToRequest(Request $request): bool
    {
        $p = ltrim($request->path(), '/');

        return str_starts_with($p, 'api/') || $p === 'api';
    }

    /**
     * El middleware global HandleCors no aplica a respuestas generadas en render();
     * sin esto, errores/401 en /api quedan sin encabezados y el navegador muestra CORS.
     */
    private function applyCorsToApi(Request $request, Response $response): Response
    {
        if (! $this->shouldApplyCorsToRequest($request)) {
            return $response;
        }

        $cors = app(CorsService::class);
        $cors->setOptions(config('cors'));

        return $cors->addActualRequestHeaders($response, $request);
    }
}
