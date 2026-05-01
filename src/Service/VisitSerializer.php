<?php

namespace App\Service;

use App\Entity\Visit;

class VisitSerializer
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Visit $visit): array
    {
        return [
            'id' => $visit->getId()->toRfc4122(),
            'link_id' => $visit->getLink()->getId()->toRfc4122(),
            'created_at' => $visit->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'ip_address' => $visit->getIpAddress(),
            'user_agent' => $visit->getUserAgent(),
            'referrer' => $visit->getReferrer(),
            'country_code' => $visit->getCountryCode(),
            'city' => $visit->getCity(),
            'screen_resolution' => $visit->getScreenResolution(),
            'viewport_width' => $visit->getViewportWidth(),
            'viewport_height' => $visit->getViewportHeight(),
            'device_pixel_ratio' => $visit->getDevicePixelRatio(),
            'color_depth' => $visit->getColorDepth(),
            'timezone' => $visit->getTimezone(),
            'language' => $visit->getLanguage(),
            'platform' => $visit->getPlatform(),
            'vendor' => $visit->getVendor(),
            'cookies_enabled' => $visit->getCookiesEnabled(),
            'do_not_track' => $visit->getDoNotTrack(),
            'pdf_viewer_enabled' => $visit->getPdfViewerEnabled(),
            'touch_support' => $visit->getTouchSupport(),
            'max_touch_points' => $visit->getMaxTouchPoints(),
            'hardware_concurrency' => $visit->getHardwareConcurrency(),
            'device_memory' => $visit->getDeviceMemory(),
            'connection_type' => $visit->getConnectionType(),
            'webgl_renderer' => $visit->getWebglRenderer(),
        ];
    }
}
