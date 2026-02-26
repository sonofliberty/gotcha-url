<?php

namespace App\Entity;

use App\Repository\VisitRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: VisitRepository::class)]
#[ORM\Index(columns: ['link_id', 'created_at'], name: 'idx_visit_link_created')]
class Visit
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME, unique: true)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: Link::class, inversedBy: 'visits')]
    #[ORM\JoinColumn(nullable: false)]
    private Link $link;

    #[ORM\Column(length: 45)]
    private string $ipAddress;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 2048, nullable: true)]
    private ?string $referrer = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $screenResolution = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $timezone = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $platform = null;

    #[ORM\Column(nullable: true)]
    private ?bool $cookiesEnabled = null;

    #[ORM\Column(length: 2, nullable: true)]
    private ?string $countryCode = null;

    #[ORM\Column(length: 128, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $devicePixelRatio = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $colorDepth = null;

    #[ORM\Column(nullable: true)]
    private ?bool $touchSupport = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $maxTouchPoints = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $hardwareConcurrency = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $deviceMemory = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $connectionType = null;

    #[ORM\Column(nullable: true)]
    private ?bool $doNotTrack = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $viewportWidth = null;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $viewportHeight = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $vendor = null;

    #[ORM\Column(nullable: true)]
    private ?bool $pdfViewerEnabled = null;

    #[ORM\Column(length: 256, nullable: true)]
    private ?string $webglRenderer = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getLink(): Link
    {
        return $this->link;
    }

    public function setLink(Link $link): static
    {
        $this->link = $link;
        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(?string $referrer): static
    {
        $this->referrer = $referrer;
        return $this;
    }

    public function getScreenResolution(): ?string
    {
        return $this->screenResolution;
    }

    public function setScreenResolution(?string $screenResolution): static
    {
        $this->screenResolution = $screenResolution;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(?string $platform): static
    {
        $this->platform = $platform;
        return $this;
    }

    public function getCookiesEnabled(): ?bool
    {
        return $this->cookiesEnabled;
    }

    public function setCookiesEnabled(?bool $cookiesEnabled): static
    {
        $this->cookiesEnabled = $cookiesEnabled;
        return $this;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function setCountryCode(?string $countryCode): static
    {
        $this->countryCode = $countryCode;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getDevicePixelRatio(): ?string
    {
        return $this->devicePixelRatio;
    }

    public function setDevicePixelRatio(?string $devicePixelRatio): static
    {
        $this->devicePixelRatio = $devicePixelRatio;
        return $this;
    }

    public function getColorDepth(): ?int
    {
        return $this->colorDepth;
    }

    public function setColorDepth(?int $colorDepth): static
    {
        $this->colorDepth = $colorDepth;
        return $this;
    }

    public function getTouchSupport(): ?bool
    {
        return $this->touchSupport;
    }

    public function setTouchSupport(?bool $touchSupport): static
    {
        $this->touchSupport = $touchSupport;
        return $this;
    }

    public function getMaxTouchPoints(): ?int
    {
        return $this->maxTouchPoints;
    }

    public function setMaxTouchPoints(?int $maxTouchPoints): static
    {
        $this->maxTouchPoints = $maxTouchPoints;
        return $this;
    }

    public function getHardwareConcurrency(): ?int
    {
        return $this->hardwareConcurrency;
    }

    public function setHardwareConcurrency(?int $hardwareConcurrency): static
    {
        $this->hardwareConcurrency = $hardwareConcurrency;
        return $this;
    }

    public function getDeviceMemory(): ?string
    {
        return $this->deviceMemory;
    }

    public function setDeviceMemory(?string $deviceMemory): static
    {
        $this->deviceMemory = $deviceMemory;
        return $this;
    }

    public function getConnectionType(): ?string
    {
        return $this->connectionType;
    }

    public function setConnectionType(?string $connectionType): static
    {
        $this->connectionType = $connectionType;
        return $this;
    }

    public function getDoNotTrack(): ?bool
    {
        return $this->doNotTrack;
    }

    public function setDoNotTrack(?bool $doNotTrack): static
    {
        $this->doNotTrack = $doNotTrack;
        return $this;
    }

    public function getViewportWidth(): ?int
    {
        return $this->viewportWidth;
    }

    public function setViewportWidth(?int $viewportWidth): static
    {
        $this->viewportWidth = $viewportWidth;
        return $this;
    }

    public function getViewportHeight(): ?int
    {
        return $this->viewportHeight;
    }

    public function setViewportHeight(?int $viewportHeight): static
    {
        $this->viewportHeight = $viewportHeight;
        return $this;
    }

    public function getVendor(): ?string
    {
        return $this->vendor;
    }

    public function setVendor(?string $vendor): static
    {
        $this->vendor = $vendor;
        return $this;
    }

    public function getPdfViewerEnabled(): ?bool
    {
        return $this->pdfViewerEnabled;
    }

    public function setPdfViewerEnabled(?bool $pdfViewerEnabled): static
    {
        $this->pdfViewerEnabled = $pdfViewerEnabled;
        return $this;
    }

    public function getWebglRenderer(): ?string
    {
        return $this->webglRenderer;
    }

    public function setWebglRenderer(?string $webglRenderer): static
    {
        $this->webglRenderer = $webglRenderer;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
