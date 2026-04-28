<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SynchSchemaFor20260428110000 extends AbstractMigration
{
    public function change(): void
    {
        $sql = file_get_contents(__DIR__ . '/20260428110000_SynchSchemaFor20260428110000.sql');
        // Eliminar líneas que comienzan con '--'
        $filtered = preg_replace('/^\s*--.*$/m', '', $sql);
        $this->execute($filtered);
    }
}
