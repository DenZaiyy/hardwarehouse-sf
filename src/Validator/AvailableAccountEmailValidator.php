<?php

declare(strict_types=1);

namespace App\Validator;

use App\Repository\UserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class AvailableAccountEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof AvailableAccountEmail) {
            throw new UnexpectedTypeException($constraint, AvailableAccountEmail::class);
        }

        if (!\is_string($value) || '' === $value) {
            return;
        }

        if (null !== $this->userRepository->findOneBy(['email' => $value])) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
