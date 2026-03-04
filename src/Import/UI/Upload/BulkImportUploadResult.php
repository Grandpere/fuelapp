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

namespace App\Import\UI\Upload;

use App\Import\Domain\ImportJob;

final class BulkImportUploadResult
{
    /** @var list<array{id:string,status:string,filename:string,source:string}> */
    private array $accepted = [];

    /** @var list<array{filename:string,reason:string,source:string}> */
    private array $rejected = [];

    public function addAccepted(ImportJob $job, string $filename, string $source): void
    {
        $this->accepted[] = [
            'id' => $job->id()->toString(),
            'status' => $job->status()->value,
            'filename' => $filename,
            'source' => $source,
        ];
    }

    public function addRejected(string $filename, string $reason, string $source): void
    {
        $this->rejected[] = [
            'filename' => $filename,
            'reason' => $reason,
            'source' => $source,
        ];
    }

    public function acceptedCount(): int
    {
        return count($this->accepted);
    }

    public function rejectedCount(): int
    {
        return count($this->rejected);
    }

    /** @return list<array{id:string,status:string,filename:string,source:string}> */
    public function accepted(): array
    {
        return $this->accepted;
    }

    /** @return list<array{filename:string,reason:string,source:string}> */
    public function rejected(): array
    {
        return $this->rejected;
    }
}
