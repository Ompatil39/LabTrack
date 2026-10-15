<?php

declare(strict_types=1);

namespace LabTrack;

/**
 * Atomic, race-condition-free asset tag generator.
 *
 * Uses MySQL's LAST_INSERT_ID() upsert trick on asset_tag_sequences
 * to atomically increment a per-prefix-per-year counter. This means
 * even concurrent requests cannot produce duplicate tags.
 *
 * Tag format: PREFIX-YYYY-NNNN  (e.g. MON-2026-0004, PC-2026-0023)
 */
final class AssetTagGenerator
{
    /**
     * Maps asset_type ENUM values to tag prefixes.
     */
    private const PREFIX_MAP = [
        'PC'          => 'PC',
        'Monitor'     => 'MON',
        'Keyboard'    => 'KBD',
        'Mouse'       => 'MSE',
        'CPU'         => 'CPU',
        'Printer'     => 'PRT',
        'Switch'      => 'SW',
        'AccessPoint' => 'AP',
        'Other'       => 'AST',
    ];

    /**
     * Generate the next unique asset tag for the given type.
     *
     * @param \mysqli $conn      Active mysqli connection
     * @param string  $assetType One of the assets.asset_type ENUM values
     * @param int|null $year     Year to use (defaults to current year)
     * @return string            Tag in the format PREFIX-YYYY-NNNN
     * @throws \InvalidArgumentException If assetType is not recognized
     * @throws \RuntimeException         If the upsert or retrieval fails
     */
    public static function nextTag(\mysqli $conn, string $assetType, ?int $year = null): string
    {
        $prefix = self::PREFIX_MAP[$assetType] ?? null;
        if ($prefix === null) {
            throw new \InvalidArgumentException(
                "Unrecognized asset type '{$assetType}'. Expected one of: " . implode(', ', array_keys(self::PREFIX_MAP))
            );
        }

        $year = $year ?? (int) date('Y');

        // Atomic upsert:
        // On INSERT: LAST_INSERT_ID(1) sets the session LAST_INSERT_ID to 1 and inserts 1.
        // On UPDATE: LAST_INSERT_ID(last_val + 1) sets the session LAST_INSERT_ID to the new value and updates last_val.
        // This guarantees SELECT LAST_INSERT_ID() on this connection reliably returns the exact allocated sequence,
        // eliminating any race window between upsert and retrieval.
        $upsertSql = '
            INSERT INTO asset_tag_sequences (prefix, seq_year, last_val)
            VALUES (?, ?, LAST_INSERT_ID(1))
            ON DUPLICATE KEY UPDATE last_val = LAST_INSERT_ID(last_val + 1)
        ';

        $stmt = $conn->prepare($upsertSql);
        if (!$stmt) {
            throw new \RuntimeException('AssetTagGenerator: failed to prepare upsert: ' . $conn->error);
        }

        $stmt->bind_param('si', $prefix, $year);
        if (!$stmt->execute()) {
            $error = $stmt->error;
            $stmt->close();
            throw new \RuntimeException('AssetTagGenerator: upsert failed: ' . $error);
        }
        $stmt->close();

        // Atomically retrieve the sequence allocated to this connection
        $res = $conn->query('SELECT LAST_INSERT_ID() AS seq');
        if (!$res) {
            throw new \RuntimeException('AssetTagGenerator: failed to fetch sequence: ' . $conn->error);
        }

        $row = $res->fetch_assoc();
        $res->free();

        $seq = (int) ($row['seq'] ?? 0);
        if ($seq <= 0) {
            throw new \RuntimeException("AssetTagGenerator: invalid sequence allocated for {$prefix}/{$year}");
        }

        return sprintf('%s-%d-%04d', $prefix, $year, $seq);
    }

    /**
     * Returns all recognized asset type -> prefix mappings.
     */
    public static function getPrefixMap(): array
    {
        return self::PREFIX_MAP;
    }
}
