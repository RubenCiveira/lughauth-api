<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class AddPkceToTemporalCodes extends AbstractMigration
{
    public function change(): void
    {
        $sql = file_get_contents(__DIR__ . '/20260413120000_AddPkceToTemporalCodes.sql');
        $filtered = preg_replace('/^\s*--.*$/m', '', $sql);
        $this->execute($filtered);
    }
}
