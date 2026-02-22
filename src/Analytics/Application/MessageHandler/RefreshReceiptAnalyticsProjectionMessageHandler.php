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

namespace App\Analytics\Application\MessageHandler;

use App\Analytics\Application\Aggregation\ReceiptAnalyticsProjectionRefresher;
use App\Analytics\Application\Message\RefreshReceiptAnalyticsProjectionMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class RefreshReceiptAnalyticsProjectionMessageHandler
{
    public function __construct(private ReceiptAnalyticsProjectionRefresher $refresher)
    {
    }

    public function __invoke(RefreshReceiptAnalyticsProjectionMessage $message): void
    {
        $this->refresher->refresh();
    }
}
