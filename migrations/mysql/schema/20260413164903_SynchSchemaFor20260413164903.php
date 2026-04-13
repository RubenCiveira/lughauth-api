<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SynchSchemaFor20260413164903 extends AbstractMigration
{
    public function change(): void
    {
        $sql = file_get_contents(__DIR__ . '/20260413164903_SynchSchemaFor20260413164903.sql');
        // Eliminar líneas que comienzan con '--'
        $filtered = preg_replace('/^\s*--.*$/m', '', $sql);
        $filtered = preg_replace_callback(
        '/\bCREATE\s+TABLE\s+(`?_[_a-zA-Z0-9]+`?)\b/i',
        function ($matches) {
            return "CREATE TABLE IF NOT EXISTS " . $matches[1];
        },
        $filtered
    );        $this->execute($filtered);
    }
}
