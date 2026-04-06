<?php

use App\Support\ApiExceptionResponses;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PhpAmqpLib\Exception\AMQPExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (ApiExceptionResponses::wantsApiJson($request)) {
                return ApiExceptionResponses::validation($e);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (ApiExceptionResponses::wantsApiJson($request)) {
                return ApiExceptionResponses::unauthenticated($e);
            }
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (ApiExceptionResponses::wantsApiJson($request)) {
                return ApiExceptionResponses::notFound($e, $request);
            }
        });

        $exceptions->render(function (AMQPExceptionInterface $e, Request $request) {
            if (ApiExceptionResponses::wantsApiJson($request)) {
                return ApiExceptionResponses::messageBrokerUnavailable($e);
            }
        });
    })->create();
