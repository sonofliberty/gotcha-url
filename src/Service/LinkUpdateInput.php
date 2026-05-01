<?php

namespace App\Service;

final class LinkUpdateInput
{
    private function __construct(
        public readonly bool $hasLabel = false,
        public readonly ?string $label = null,
        public readonly bool $hasTrackingEnabled = false,
        public readonly bool $trackingEnabled = true,
        public readonly bool $hasMarkdownContent = false,
        public readonly ?string $markdownContent = null,
        public readonly bool $hasTargetUrl = false,
        public readonly ?string $targetUrl = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return !$this->hasLabel
            && !$this->hasTrackingEnabled
            && !$this->hasMarkdownContent
            && !$this->hasTargetUrl;
    }

    public static function none(): self
    {
        return new self();
    }

    public static function label(?string $label): self
    {
        return new self(hasLabel: true, label: $label);
    }

    public static function trackingEnabled(bool $trackingEnabled): self
    {
        return new self(hasTrackingEnabled: true, trackingEnabled: $trackingEnabled);
    }

    public static function markdownContent(?string $markdownContent): self
    {
        return new self(hasMarkdownContent: true, markdownContent: $markdownContent);
    }

    public static function targetUrl(?string $targetUrl): self
    {
        return new self(hasTargetUrl: true, targetUrl: $targetUrl);
    }

    /**
     * @param array<string, mixed> $data API JSON payload (snake_case keys).
     * @throws \InvalidArgumentException if `tracking_enabled` is present and not a boolean.
     */
    public static function fromArray(array $data): self
    {
        $hasLabel = array_key_exists('label', $data);
        $label = ($hasLabel && $data['label'] !== null) ? trim((string) $data['label']) : null;

        $hasTrackingEnabled = array_key_exists('tracking_enabled', $data);
        $trackingEnabled = true;
        if ($hasTrackingEnabled) {
            if (!is_bool($data['tracking_enabled'])) {
                throw new \InvalidArgumentException('tracking_enabled must be a boolean.');
            }
            $trackingEnabled = $data['tracking_enabled'];
        }

        $hasMarkdownContent = array_key_exists('content', $data);
        $markdownContent = ($hasMarkdownContent && $data['content'] !== null) ? (string) $data['content'] : null;

        $hasTargetUrl = array_key_exists('target_url', $data);
        $targetUrl = ($hasTargetUrl && $data['target_url'] !== null) ? trim((string) $data['target_url']) : null;

        return new self(
            hasLabel: $hasLabel,
            label: $label,
            hasTrackingEnabled: $hasTrackingEnabled,
            trackingEnabled: $trackingEnabled,
            hasMarkdownContent: $hasMarkdownContent,
            markdownContent: $markdownContent,
            hasTargetUrl: $hasTargetUrl,
            targetUrl: $targetUrl,
        );
    }
}
