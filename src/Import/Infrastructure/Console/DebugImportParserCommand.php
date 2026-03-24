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

namespace App\Import\Infrastructure\Console;

use App\Import\Application\Ocr\OcrExtraction;
use App\Import\Application\Parsing\ReceiptOcrParser;
use App\Import\Application\Repository\ImportJobRepository;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import:debug-parse', description: 'Re-run receipt parser on an existing import job OCR payload')]
final class DebugImportParserCommand extends Command
{
    public function __construct(
        private readonly ImportJobRepository $importJobRepository,
        private readonly ReceiptOcrParser $receiptParser,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('job-id', InputArgument::OPTIONAL, 'Import job UUID to inspect')
            ->addOption('filename', null, InputOption::VALUE_REQUIRED, 'Use latest job matching original filename when job-id is omitted')
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty print JSON output');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $jobIdArg = $input->getArgument('job-id');
        $jobId = is_string($jobIdArg) ? trim($jobIdArg) : '';

        $filenameOption = $input->getOption('filename');
        $filename = is_string($filenameOption) ? trim($filenameOption) : '';

        if ('' === $jobId && '' === $filename) {
            $output->writeln('<error>Provide either <comment>job-id</comment> or <comment>--filename</comment>.</error>');

            return Command::INVALID;
        }

        $job = '' !== $jobId
            ? $this->importJobRepository->getForSystem($jobId)
            : $this->findLatestByFilename($filename);

        if (null === $job) {
            $output->writeln('<error>Import job not found.</error>');

            return Command::FAILURE;
        }

        $payloadRaw = $job->errorPayload();
        if (null === $payloadRaw || '' === trim($payloadRaw)) {
            $output->writeln('<error>Import job has no OCR payload to parse.</error>');

            return Command::FAILURE;
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode($payloadRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $output->writeln(sprintf('<error>Invalid payload JSON: %s</error>', $exception->getMessage()));

            return Command::FAILURE;
        }

        $text = $this->readString($payload, 'text');
        if ('' === trim($text)) {
            $output->writeln('<error>Payload does not contain OCR text.</error>');

            return Command::FAILURE;
        }

        $provider = $this->readString($payload, 'provider');
        if ('' === $provider) {
            $provider = 'unknown_provider';
        }

        $pages = $this->readStringList($payload, 'pages');

        $draft = $this->receiptParser->parse(new OcrExtraction($provider, $text, $pages, $payload));
        $result = [
            'jobId' => $job->id()->toString(),
            'originalFilename' => $job->originalFilename(),
            'status' => $job->status()->value,
            'text' => $text,
            'parsedDraft' => $draft->toArray(),
        ];

        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if (true === $input->getOption('pretty')) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $encoded = json_encode($result, $flags);
        if (!is_string($encoded)) {
            $output->writeln('<error>Unable to serialize parser output.</error>');

            return Command::FAILURE;
        }

        $output->writeln($encoded);

        return Command::SUCCESS;
    }

    private function findLatestByFilename(string $filename): ?\App\Import\Domain\ImportJob
    {
        if ('' === $filename) {
            return null;
        }

        $latest = null;
        foreach ($this->importJobRepository->allForSystem() as $job) {
            if ($job->originalFilename() !== $filename) {
                continue;
            }

            if (null === $latest || $job->createdAt()->getTimestamp() > $latest->createdAt()->getTimestamp()) {
                $latest = $job;
            }
        }

        return $latest;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function readString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;

        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return list<string>
     */
    private function readStringList(array $payload, string $key): array
    {
        $value = $payload[$key] ?? null;
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ('' === $trimmed) {
                continue;
            }

            $result[] = $trimmed;
        }

        return $result;
    }
}
