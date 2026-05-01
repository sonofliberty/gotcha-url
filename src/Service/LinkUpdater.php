<?php

namespace App\Service;

use App\Entity\Link;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class LinkUpdater
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function update(Link $link, LinkUpdateInput $input): Link|ConstraintViolationListInterface
    {
        if ($input->hasMarkdownContent && $link->isRedirect()) {
            return ViolationFactory::single('markdownContent', 'Content can only be set on page links.', $input->markdownContent);
        }

        if ($input->hasTargetUrl && $link->isPage()) {
            return ViolationFactory::single('targetUrl', 'Target URL can only be set on redirect links.', $input->targetUrl);
        }

        if ($input->hasLabel) {
            $label = $input->label;
            $link->setLabel(($label === null || $label === '') ? null : $label);
        }

        if ($input->hasTrackingEnabled) {
            $link->setTrackingEnabled($input->trackingEnabled);
        }

        if ($input->hasMarkdownContent) {
            $link->setMarkdownContent($input->markdownContent);
        }

        if ($input->hasTargetUrl) {
            $link->setTargetUrl($input->targetUrl);
        }

        $errors = $this->validator->validate($link);
        if (count($errors) > 0) {
            return $errors;
        }

        $this->em->flush();

        return $link;
    }
}
