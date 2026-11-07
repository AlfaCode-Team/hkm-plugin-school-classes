<?php

declare(strict_types=1);

namespace Plugins\SchoolClasses;

use AlfacodeTeam\PhpServicePlatform\Kernel\Contracts\ModuleContract;
use Plugins\SchoolClasses\API\Contracts\SchoolClassesContract;
use Plugins\SchoolClasses\Application\Services\SchoolClassesService;
use AlfacodeTeam\PhpServicePlatform\Kernel\Container\ModuleContainer;
use AlfacodeTeam\PhpServicePlatform\Kernel\Events\EventBus;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Cli\CliPipeline;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Http\HttpPipeline;
use AlfacodeTeam\PhpServicePlatform\Kernel\Pipelines\Worker\WorkerPipeline;

/**
 * SchoolClasses — wires the 'school.classes' domain.
 *
 * The class catalog (12 sections), classes, subjects, sub-subjects and teacher-to-class grants.
 *
 * Keep this module focused on ONE business domain. Wire internal bindings with
 * $container->bindInternal(); publish cross-module contracts with $container->bind().
 */
final class Provider implements ModuleContract
{
    public function solves(): string
    {
        return 'school.classes';
    }

    /** @return list<string> module DOMAINS — mirrors module.json "requires" */
    public function requires(): array
    {
        return [
            'database.management',
            'school.directory',
        ];
    }

    /** @return list<class-string> */
    public function exposes(): array
    {
        return [SchoolClassesContract::class];
    }

    public function register(ModuleContainer $container): void
    {
        // Public — resolvable by any module that declares 'school.classes'.
        // The catalogue half is pure declared data, so this needs no
        // repository, no DatabasePort and no SchoolScope yet.
        $container->bind(
            SchoolClassesContract::class,
            static fn(): SchoolClassesContract => new SchoolClassesService(),
        );
    }

    public function boot(HttpPipeline $http, CliPipeline $cli, WorkerPipeline $worker, EventBus $events): void
    {
        // Register pipeline hooks / event subscriptions here.
    }
}
