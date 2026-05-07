<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class SynchSchemaFor20260507174311 extends AbstractMigration
{
    public function change(): void
    {
        $sql = file_get_contents(__DIR__ . '/20260507174311_SynchSchemaFor20260507174311.sql');
        $filtered = preg_replace('/^\s*--.*$/m', '', $sql);
        $filtered = preg_replace_callback(
            '/\bCREATE\s+TABLE\s+(`?[_a-zA-Z0-9]+`?)\b/i',
            function ($matches) {
                return "CREATE TABLE IF NOT EXISTS " . $matches[1];
            },
            $filtered
        );
        $this->execute($filtered);
    }
}
