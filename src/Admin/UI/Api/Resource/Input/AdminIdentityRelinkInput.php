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

namespace App\Admin\UI\Api\Resource\Input;

use Symfony\Component\Validator\Constraints as Assert;

final class AdminIdentityRelinkInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public ?string $userId = null;
}
