<?php

namespace App\Dto\Form\Contact;

use Symfony\Component\Validator\Constraints as Assert;

class ContactFormDto
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 100)]
        public ?string $name = null,
        #[Assert\NotBlank]
        #[Assert\Email]
        public ?string $email = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 255)]
        public ?string $subject = null,
        #[Assert\NotBlank]
        #[Assert\Length(max: 1000)]
        public ?string $message = null,
    ) { }
}
