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

namespace App\Shared\Infrastructure\Validator;

use Symfony\Component\Uid\UuidV7 as UidUuidV7;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Throwable;

final class UuidV7Validator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UuidV7) {
            throw new UnexpectedTypeException($constraint, UuidV7::class);
        }

        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            $this->context->buildViolation($constraint->message)->addViolation();

            return;
        }

        try {
            UidUuidV7::fromString($value);
        } catch (Throwable) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
