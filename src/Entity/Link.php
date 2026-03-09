<?php

namespace App\Entity;

use App\Repository\LinkRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: LinkRepository::class)]
class Link
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'links')]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 10, options: ['default' => 'redirect'])]
    private string $type = 'redirect';

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $targetUrl = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 50000)]
    private ?string $markdownContent = null;

    #[Assert\Regex(pattern: '/^[a-zA-Z0-9]{3,10}$/')]
    #[ORM\Column(length: 10, unique: true)]
    private string $slug;

    #[Assert\Length(max: 100)]
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $label = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    /** @var Collection<int, Visit> */
    #[ORM\OneToMany(targetEntity: Visit::class, mappedBy: 'link', orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $visits;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
        $this->visits = new ArrayCollection();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function isPage(): bool
    {
        return $this->type === 'page';
    }

    public function isRedirect(): bool
    {
        return $this->type === 'redirect';
    }

    public function getTargetUrl(): ?string
    {
        return $this->targetUrl;
    }

    public function setTargetUrl(?string $targetUrl): static
    {
        $this->targetUrl = $targetUrl;
        return $this;
    }

    public function getMarkdownContent(): ?string
    {
        return $this->markdownContent;
    }

    public function setMarkdownContent(?string $markdownContent): static
    {
        $this->markdownContent = $markdownContent;
        return $this;
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->type === 'redirect') {
            if (empty($this->targetUrl)) {
                $context->buildViolation('A target URL is required for redirect links.')
                    ->atPath('targetUrl')
                    ->addViolation();
            } elseif (!filter_var($this->targetUrl, FILTER_VALIDATE_URL)) {
                $context->buildViolation('Please enter a valid URL.')
                    ->atPath('targetUrl')
                    ->addViolation();
            }
        } elseif ($this->type === 'page') {
            if (empty($this->markdownContent)) {
                $context->buildViolation('Content is required for page links.')
                    ->atPath('markdownContent')
                    ->addViolation();
            }
        }
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return Collection<int, Visit> */
    public function getVisits(): Collection
    {
        return $this->visits;
    }

    public function getVisitCount(): int
    {
        return $this->visits->count();
    }
}
