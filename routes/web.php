<?php

use App\Controllers\Web\StudentController;
use App\Controllers\Web\AdminController;
use App\Controllers\Web\AuthController;
use App\Controllers\HomeController;
use App\Controllers\TestController;
use App\Middleware\APIKeyMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\SecurityHeaders;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
| These routes return HTML views or handle browser-based requests.
| Make sure to pass controllers as [ControllerClass::class, 'methodName'].
|--------------------------------------------------------------------------
*/

$router->middleware([\App\Middleware\CsrfMiddleware::class]);

$router->get('/web', [HomeController::class, 'index'], /* , [AuthMiddleware::class] */);
$router->get('/about', [HomeController::class, 'about']);

$router->get('/web/login', [AuthController::class, 'index']);
$router->get('/web/register', [AuthController::class, 'registerForm'], [AuthMiddleware::class, SecurityHeaders::class]);
$router->get('/web/logout', [AuthController::class, 'logout']/* , [AuthMiddleware::class] */);

$router->get('/web/admin', [AdminController::class, 'index'], [AuthMiddleware::class]);
$router->get('/web/users', [AdminController::class, 'users'], [AuthMiddleware::class]);

$router->get('/web/students', [StudentController::class, 'index'], [/* APIKeyMiddleware::class,  */AuthMiddleware::class]);
$router->get('/web/students/create', [StudentController::class, 'create'], [/* APIKeyMiddleware::class,  */AuthMiddleware::class]);

//Testing routes
$router->get('/web/test/mail', [TestController::class, 'mail']);
$router->get('/web/test/sms', [TestController::class, 'sms']);
$router->get('/web/test/pdf', [TestController::class, 'pdfReport']);
/* $router->get('/report/queue', [ReportController::class, 'queueReport']);
$router->get('/report/download', [ReportController::class, 'downloadReport']); */