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

namespace App\Admin\Application\Audit;

interface AdminAuditContext
{
    public function actorId(): ?string;

    public function actorEmail(): ?string;

    public function correlationId(): string;

    /** @return array<string, mixed> */
    public function metadata(): array;
}
