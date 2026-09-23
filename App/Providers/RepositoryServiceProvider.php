<?php
declare(strict_types=1);

namespace App\Providers;

use App\Core\Container;
use App\Core\Interfaces\ServiceProviderInterface;
use App\Core\Cache;
use PDO;
use App\Repositories\StudentRepository;
use App\Repositories\UserRepository;
use App\Repositories\AcademicSetupRepository;
use App\Repositories\AcademicYearRepository;
use App\Repositories\SubjectRepository;
use App\Repositories\ClassRepository;

class RepositoryServiceProvider implements ServiceProviderInterface
{
    public function register(Container $app): void
    {
        $app->singleton(StudentRepository::class, fn($app) =>
            new StudentRepository(
                $app->resolve(PDO::class),
                $app->resolve(Cache::class)
            )
        );
        $app->singleton(UserRepository::class, fn($app) =>
            new UserRepository(
                $app->resolve(PDO::class),
                $app->resolve(Cache::class)
            )
        );
        $app->singleton(AcademicSetupRepository::class, fn($app) =>
            new AcademicSetupRepository()
        );
        $app->singleton(AcademicYearRepository::class, fn($app) =>
            new AcademicYearRepository(
                $app->resolve(PDO::class),
                $app->resolve(Cache::class)
            )
        );
        $app->singleton(SubjectRepository::class, fn($app) =>
            new SubjectRepository(
                $app->resolve(PDO::class),
                $app->resolve(Cache::class)
            )
        );
        $app->singleton(ClassRepository::class, fn($app) =>
            new ClassRepository(
                $app->resolve(PDO::class),
                $app->resolve(Cache::class)
            )
        );
    }
}
