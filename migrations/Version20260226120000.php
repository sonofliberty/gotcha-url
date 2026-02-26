<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add comprehensive device info fields to visit table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visit ADD device_pixel_ratio VARCHAR(10) DEFAULT NULL, ADD color_depth SMALLINT DEFAULT NULL, ADD touch_support TINYINT(1) DEFAULT NULL, ADD max_touch_points SMALLINT DEFAULT NULL, ADD hardware_concurrency SMALLINT DEFAULT NULL, ADD device_memory VARCHAR(10) DEFAULT NULL, ADD connection_type VARCHAR(20) DEFAULT NULL, ADD do_not_track TINYINT(1) DEFAULT NULL, ADD viewport_width SMALLINT DEFAULT NULL, ADD viewport_height SMALLINT DEFAULT NULL, ADD vendor VARCHAR(64) DEFAULT NULL, ADD pdf_viewer_enabled TINYINT(1) DEFAULT NULL, ADD webgl_renderer VARCHAR(256) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE visit DROP device_pixel_ratio, DROP color_depth, DROP touch_support, DROP max_touch_points, DROP hardware_concurrency, DROP device_memory, DROP connection_type, DROP do_not_track, DROP viewport_width, DROP viewport_height, DROP vendor, DROP pdf_viewer_enabled, DROP webgl_renderer');
    }
}
