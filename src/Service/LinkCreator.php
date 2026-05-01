<?php

namespace App\Service;

use App\Entity\Link;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LinkCreator
{
    private const ALLOWED_TYPES = ['redirect', 'page'];

    /** Reserved top-level path segments that would shadow /{slug} routing. */
    public const RESERVED_SLUGS = ['login', 'logout', 'register', 'dashboard'];

    public function __construct(
        private readonly SlugGenerator $slugGenerator,
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function isSlugReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::RESERVED_SLUGS, true);
    }

    public function create(
        User $user,
        string $type,
        ?string $targetUrl,
        ?string $markdownContent,
        ?string $label,
        ?string $customSlug,
        bool $trackingEnabled = true,
    ): Link|ConstraintViolationListInterface {
        $customSlug = ($customSlug !== null && $customSlug !== '') ? $customSlug : null;
        $label = ($label !== null && $label !== '') ? $label : null;

        if (!in_array($type, self::ALLOWED_TYPES, true)) {
            return $this->violation('type', sprintf('Type must be one of: %s.', implode(', ', self::ALLOWED_TYPES)), $type);
        }

        if ($customSlug !== null && $this->isSlugReserved($customSlug)) {
            return $this->violation('slug', sprintf('Slug "%s" is reserved.', $customSlug), $customSlug);
        }

        $link = new Link();
        $link->setUser($user);
        $link->setType($type);
        $link->setSlug($customSlug ?? $this->slugGenerator->generate());
        $link->setLabel($label);
        $link->setTrackingEnabled($trackingEnabled);

        if ($link->isPage()) {
            $link->setMarkdownContent($markdownContent);
        } else {
            $link->setTargetUrl($targetUrl);
        }

        $errors = $this->validator->validate($link);
        if (count($errors) > 0) {
            return $errors;
        }

        $this->em->persist($link);
        $this->em->flush();

        return $link;
    }

    private function violation(string $path, string $message, ?string $invalidValue): ConstraintViolationList
    {
        return new ConstraintViolationList([
            new ConstraintViolation($message, null, [], null, $path, $invalidValue),
        ]);
    }
}
