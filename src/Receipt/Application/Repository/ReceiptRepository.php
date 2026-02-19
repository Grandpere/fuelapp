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

namespace App\Receipt\Application\Repository;

use App\Receipt\Domain\Receipt;

interface ReceiptRepository
{
    public function save(Receipt $receipt): void;

    public function get(string $id): ?Receipt;

    /** @return iterable<Receipt> */
    public function all(): iterable;

    /** @return iterable<Receipt> */
    public function paginate(int $page, int $perPage): iterable;

    public function countAll(): int;
}
