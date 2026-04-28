<?php

declare(strict_types=1);

/*
 * This file is part of a FuelApp project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PublicFuelStation\Infrastructure\Console;

use App\PublicFuelStation\Application\Import\PublicFuelStationImporter;
use App\PublicFuelStation\Application\Repository\PublicFuelStationSyncRunRepository;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

#[AsCommand(
    name: 'app:public-fuel-stations:sync',
    description: 'Sync public fuel stations from the official data.gouv.fr fuel prices feed.',
)]
final class SyncPublicFuelStationsCommand extends Command
{
    private const DEFAULT_SOURCE_URL = 'https://www.data.gouv.fr/api/1/datasets/r/edd67f5b-46d0-4663-9de9-e5db1c880160';

    public function __construct(
        private readonly PublicFuelStationImporter $importer,
        private readonly PublicFuelStationSyncRunRepository $syncRunRepository,
        private readonly HttpClientInterface $httpClient,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'CSV source URL or local path.', self::DEFAULT_SOURCE_URL)
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Optional maximum number of rows to import.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $source = $this->readStringOption($input, 'source') ?? self::DEFAULT_SOURCE_URL;
        try {
            $limit = $this->readPositiveIntOption($input, 'limit');
        } catch (RuntimeException $e) {
            $output->writeln(sprintf('<error>Public fuel stations sync failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        }

        $runId = $this->syncRunRepository->start($source);
        $preparedPath = null;

        try {
            $preparedPath = $this->prepareSource($source);
            $result = $this->importer->importFile($preparedPath, $limit);
            $status = $result->rejectedCount > 0 ? 'partial' : 'success';
            $this->syncRunRepository->finish($runId, $status, $result->processedCount, $result->upsertedCount, $result->rejectedCount);

            $output->writeln(sprintf(
                'Public fuel stations sync %s: %d processed, %d upserted, %d rejected.',
                $status,
                $result->processedCount,
                $result->upsertedCount,
                $result->rejectedCount,
            ));

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->syncRunRepository->finish($runId, 'failed', 0, 0, 0, $e->getMessage());
            $output->writeln(sprintf('<error>Public fuel stations sync failed: %s</error>', $e->getMessage()));

            return Command::FAILURE;
        } finally {
            if (null !== $preparedPath && is_file($preparedPath)) {
                @unlink($preparedPath);
            }
        }
    }

    private function prepareSource(string $source): string
    {
        $raw = is_file($source)
            ? file_get_contents($source)
            : $this->httpClient->request('GET', $source, ['timeout' => 30])->getContent();

        if (!is_string($raw) || '' === $raw) {
            throw new RuntimeException('Fuel station source is empty.');
        }

        $decoded = $this->maybeDecodeGzip($raw);
        $tmpPath = tempnam(sys_get_temp_dir(), 'fuelapp-public-stations-');
        if (false === $tmpPath) {
            throw new RuntimeException('Unable to create temporary public fuel station import file.');
        }

        file_put_contents($tmpPath, $decoded);

        return $tmpPath;
    }

    private function maybeDecodeGzip(string $content): string
    {
        if (!str_starts_with($content, "\x1F\x8B")) {
            return $content;
        }

        $decoded = gzdecode($content);
        if (false === $decoded) {
            throw new RuntimeException('Unable to decode gzip-compressed public fuel station source.');
        }

        return $decoded;
    }

    private function readStringOption(InputInterface $input, string $name): ?string
    {
        $value = $input->getOption($name);
        if (!is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function readPositiveIntOption(InputInterface $input, string $name): ?int
    {
        $value = $input->getOption($name);
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_scalar($value) || !ctype_digit((string) $value)) {
            throw new RuntimeException(sprintf('Option "%s" must be a positive integer.', $name));
        }

        $intValue = (int) $value;
        if ($intValue <= 0) {
            throw new RuntimeException(sprintf('Option "%s" must be a positive integer.', $name));
        }

        return $intValue;
    }
}
