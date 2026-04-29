<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SynchSchemaFor20260429170000 extends AbstractMigration
{
    public function change(): void
    {
        $sql = file_get_contents(__DIR__ . '/20260429170000_SynchSchemaFor20260429170000.sql');
        $this->execute($sql);
    }
}
