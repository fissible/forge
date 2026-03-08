<?php

declare(strict_types=1);

namespace Fissible\Forge\Console;

use Fissible\Drift\RouteInspectorInterface;
use Fissible\Forge\SpecGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(name: 'accord:generate', description: 'Scaffold an OpenAPI spec from API routes')]
class GenerateCommand extends Command
{
    public function __construct(
        private readonly RouteInspectorInterface $inspector,
        private readonly SpecGenerator $generator,
        private readonly string $basePath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('version', null, InputOption::VALUE_REQUIRED, 'URI version to generate for', 'v1')
            ->addOption('title', null, InputOption::VALUE_REQUIRED, 'API title for the spec info block', 'API')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output path (default: {base}/resources/openapi/{version}.yaml)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing spec file');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $version = $input->getOption('version');
        $title   = $input->getOption('title');
        $force   = (bool) $input->getOption('force');

        $outputPath = $input->getOption('output')
            ?? $this->basePath . '/resources/openapi/' . $version . '.yaml';

        if (file_exists($outputPath) && !$force) {
            $output->writeln("<error>Spec already exists: {$outputPath}</error>");
            $output->writeln('Use --force to overwrite.');
            return Command::FAILURE;
        }

        $number = ltrim($version, 'v');
        $routes = array_values(array_filter(
            $this->inspector->getRoutes(),
            fn($r) => preg_match('/^\/v' . $number . '(?:\/|$)/', $r->path),
        ));

        if (empty($routes)) {
            $output->writeln("<comment>No routes found for {$version}. Nothing to generate.</comment>");
            return Command::SUCCESS;
        }

        $spec = $this->generator->generate($routes, $version, $title);
        $yaml = Yaml::dump($spec, indent: 2, flags: Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE | Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);

        @mkdir(dirname($outputPath), recursive: true);
        file_put_contents($outputPath, $yaml);

        $output->writeln("<info>Spec written:</info> {$outputPath}");
        $output->writeln(sprintf('  %d route(s) documented.', count($routes)));
        $output->writeln('  Response schemas are scaffolded as empty objects — fill these in with your actual response structure.');

        return Command::SUCCESS;
    }
}
