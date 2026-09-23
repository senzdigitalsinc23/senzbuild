<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Interfaces\ServiceProviderInterface;
use App\Services\ValidationService;
use App\Services\StudentService;
use App\Services\AuthValidationService;
use App\Services\AcademicSetupService;
use App\Services\AuthService;
use App\Services\AdminService;
use App\Services\SubjectService;
use App\Services\ClassService;
use App\Services\SwaggerGenerator;
use App\Repositories\StudentRepository;
use App\Repositories\AcademicSetupRepository;
use App\Repositories\AcademicYearRepository;
use App\Repositories\UserRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\ClassRepository;
use App\Repositories\AuthUserRepository;
use App\Repositories\RefreshTokenRepository;

class ServiceServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app): void
    {
        $app->singleton(ValidationService::class, fn($app) => new ValidationService());

        $app->singleton(StudentService::class, fn($app) =>
            new StudentService(
                $app->resolve(StudentRepository::class)
            )
        );

        $app->singleton(AuthValidationService::class, fn($app) => new AuthValidationService());

        $app->singleton(AcademicSetupService::class, fn($app) =>
            new AcademicSetupService(
                $app->resolve(AcademicSetupRepository::class),
                $app->resolve(AcademicYearRepository::class),
                $app->resolve(ValidationService::class)
            )
        );

        $app->singleton(AuthService::class, fn($app) =>
            new AuthService(
                $app->resolve(AuthUserRepository::class),
                $app->resolve(RefreshTokenRepository::class)
            )
        );

        $app->singleton(AdminService::class, fn($app) =>
            new AdminService(
                $app->resolve(UserRepository::class)
            )
        );

        $app->singleton(SubjectService::class, fn($app) =>
            new SubjectService(
                $app->resolve(SubjectRepository::class)
            )
        );

        $app->singleton(ClassService::class, fn($app) =>
            new ClassService(
                $app->resolve(ClassRepository::class)
            )
        );

        $app->singleton(SwaggerGenerator::class, fn($app) => new SwaggerGenerator());
    }
}
