<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Throwable;

use Illuminate\Database\QueryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;

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
                return  Response()->json(['error' => $errors, 'code' => 422], 422);
            }

            if ($exception instanceof ModelNotFoundException) {
                return  Response()->json(['error' => 'No se encontro ningun registro.', 'code' => 404], 404);
            }

            if ($exception instanceof AuthenticationException) {
                return $this->unauthenticated($request, $exception);
            }

            if ($exception instanceof AuthorizationException) {
                return  Response()->json(['error' => 'No posee permisos para ejecutar esta acción handler.', 'code' => 403], 403);
            }

            if ($exception instanceof NotFoundHttpException) {
                return  Response()->json(['error' => 'URL Invalida.', 'code' => 404], 404);
            }

            if ($exception instanceof MethodNotAllowedHttpException) {
                return  Response()->json(['error' => 'Peticion no valida.', 'code' => 405], 405);
            }

            if ($exception instanceof TokenExpiredException ) {
                return  Response()->json(['error' => 'La sessión expiró, vuelva a iniciar sesión', 'code' => 401], 401);
            }

            if ($exception instanceof JWTException) {
                return  Response()->json(['error' => $exception->getMessage(), 'code' => $exception]);
            }

            if ($exception instanceof QueryException) {
                return  Response()->json(['error' => $exception->getMessage(), 'code' => 500], 500);
                // return  Response()->json(['error' => 'Hubo un problema con la base de datos', 'code' => 500], 500);
            }

            if ($exception instanceof HttpException) {
                return  Response()->json(['error' => $exception->getMessage(), 'code' => $exception->getStatusCode()], $exception->getStatusCode());
            }
        }

        return parent::render($request, $exception);
    }
}
