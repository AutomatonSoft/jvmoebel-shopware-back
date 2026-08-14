<?php declare(strict_types=1);

namespace Jv\Import\Integration\Csv;

final class SemicolonCsvReader
{
    /**
     * @param list<string> $requiredHeaders
     *
     * @return \Generator<int, array<string, string>>
     */
    public function rows(string $file, array $requiredHeaders): \Generator
    {
        $handle = fopen($file, 'rb');
        if (false === $handle) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" cannot be read.', $file));
        }

        try {
            $headers = fgetcsv($handle, 0, ';', '"', '\\');
            if (false === $headers) {
                throw new \InvalidArgumentException(sprintf('CSV file "%s" is empty.', $file));
            }
            $headers = $this->headers($headers, $file);
            $missing = array_values(array_diff($requiredHeaders, $headers));
            if ([] !== $missing) {
                throw new \InvalidArgumentException(sprintf('CSV file "%s" is missing required column(s): %s.', $file, implode(', ', $missing)));
            }

            $line = 1;
            while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                ++$line;
                if ([null] === $row) {
                    continue;
                }
                if (count($headers) !== count($row)) {
                    throw new \InvalidArgumentException(sprintf('CSV file "%s" has %d columns on line %d; expected %d.', $file, count($row), $line, count($headers)));
                }

                /** @var array<string, string> $record */
                $record = array_combine($headers, array_map(static fn (mixed $value): string => trim((string) $value), $row));
                yield $line => $record;
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param list<string> $headers
     *
     * @return list<string>
     */
    private function headers(array $headers, string $file): array
    {
        $headers = array_map(static fn (string $header): string => trim($header), $headers);
        if (str_starts_with($headers[0], "\xEF\xBB\xBF")) {
            // fgetcsv cannot recognize an opening enclosure when it follows the BOM.
            $headers[0] = trim(substr($headers[0], 3), '"');
        }

        if (in_array('', $headers, true) || count($headers) !== count(array_unique($headers))) {
            throw new \InvalidArgumentException(sprintf('CSV file "%s" has empty or duplicate column names.', $file));
        }

        return $headers;
    }
}
