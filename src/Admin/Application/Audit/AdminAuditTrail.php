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

interface AdminAuditTrail
{
    /**
     * @param array<string, mixed> $diffSummary
     * @param array<string, mixed> $metadata
     */
    public function record(string $action, string $targetType, string $targetId, array $diffSummary = [], array $metadata = []): void;
}
