<?php declare(strict_types=1);

namespace Jv\Seo\Service\CategoryMapping;

use Jv\Seo\Service\CategoryMapping\Dto\CategoryMappingScore;
use Jv\Seo\Service\CategoryMapping\Dto\CategoryMappingScoreInput;

final readonly class CategoryMappingScoreCalculator
{
    public function calculate(CategoryMappingScoreInput $input): CategoryMappingScore
    {
        $legacyCoverage = $this->percentage($input->matchedProducts, $input->legacyEligibleProducts);
        $targetPurity = $this->percentage($input->matchedProducts, $input->newCategoryProducts);
        $semanticSimilarity = $this->semanticSimilarity($input);
        $hierarchyConsistency = $this->hierarchyConsistency($input->legacyPath, $input->newPath);
        $googleTaxonomySimilarity = $this->googleTaxonomySimilarity($input->googleTaxonomies, $input->newPath);

        return new CategoryMappingScore(
            (int) round(
                (0.45 * $legacyCoverage)
                + (0.10 * $targetPurity)
                + (0.25 * $semanticSimilarity)
                + (0.15 * $hierarchyConsistency)
                + (0.05 * $googleTaxonomySimilarity),
            ),
            $legacyCoverage,
            $targetPurity,
            $semanticSimilarity,
            $hierarchyConsistency,
            $googleTaxonomySimilarity,
        );
    }

    private function percentage(int $part, int $total): int
    {
        return 0 === $total ? 0 : min(100, (int) round(100 * $part / $total));
    }

    private function semanticSimilarity(CategoryMappingScoreInput $input): int
    {
        $name = $this->similarity($input->legacyName, $input->newName);
        $metaTitle = $this->similarity($input->legacyMetaTitle, $input->newMetaTitle);
        $metaDescription = $this->similarity($input->legacyMetaDescription, $input->newMetaDescription);
        $url = $this->similarity($input->legacyUrlKey, $input->newSeoPathInfo);
        $combined = (int) round((0.70 * $name) + (0.15 * $metaTitle) + (0.10 * $metaDescription) + (0.05 * $url));

        return max($name, $combined);
    }

    /** @param list<string> $legacyPath
     * @param list<string> $newPath
     */
    private function hierarchyConsistency(array $legacyPath, array $newPath): int
    {
        array_pop($legacyPath);
        array_pop($newPath);
        $legacyPath = $this->meaningfulPath($legacyPath);
        $newPath = $this->meaningfulPath($newPath);
        if ([] === $legacyPath || [] === $newPath) {
            return 0;
        }

        return $this->similarity(implode(' ', $legacyPath), implode(' ', $newPath));
    }

    /**
     * @param list<array{path: string, count: int}> $googleTaxonomies
     * @param list<string>                          $newPath
     */
    private function googleTaxonomySimilarity(array $googleTaxonomies, array $newPath): int
    {
        if ([] === $googleTaxonomies || [] === $newPath) {
            return 0;
        }

        $targetPath = implode(' ', $this->meaningfulPath($newPath));
        $weightedSimilarity = 0;
        $products = 0;
        foreach ($googleTaxonomies as $taxonomy) {
            if ($taxonomy['count'] <= 0) {
                continue;
            }
            $weightedSimilarity += $this->similarity($taxonomy['path'], $targetPath) * $taxonomy['count'];
            $products += $taxonomy['count'];
        }

        return 0 === $products ? 0 : (int) round($weightedSimilarity / $products);
    }

    /** @param list<string> $path
     * @return list<string>
     */
    private function meaningfulPath(array $path): array
    {
        return array_values(array_filter($path, function (string $part): bool {
            return !in_array($this->normalize($part), ['home', 'jvmöbel', 'jvmoebel', 'jv moebel', 'jvmobel'], true);
        }));
    }

    private function similarity(string $left, string $right): int
    {
        $left = $this->normalize($left);
        $right = $this->normalize($right);
        if ('' === $left || '' === $right) {
            return 0;
        }
        if ($left === $right) {
            return 100;
        }

        return max(
            $this->dice($this->tokens($left), $this->tokens($right)),
            $this->dice($this->bigrams($left), $this->bigrams($right)),
        );
    }

    private function normalize(string $value): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = mb_strtolower($value, 'UTF-8');
        $value = str_replace('&', ' und ', $value);
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    }

    /** @return list<string> */
    private function tokens(string $value): array
    {
        $tokens = preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        if (false === $tokens) {
            return [];
        }

        return array_values(array_unique(array_map($this->stem(...), $tokens)));
    }

    private function stem(string $token): string
    {
        foreach (['ern', 'en', 'er', 'es', 'e', 'n', 's'] as $suffix) {
            if (mb_strlen($token, 'UTF-8') > mb_strlen($suffix, 'UTF-8') + 3 && str_ends_with($token, $suffix)) {
                return mb_substr($token, 0, -mb_strlen($suffix, 'UTF-8'), 'UTF-8');
            }
        }

        return $token;
    }

    /** @return list<string> */
    private function bigrams(string $value): array
    {
        $characters = mb_str_split(str_replace(' ', '', $value));
        if (count($characters) < 2) {
            return $characters;
        }

        $bigrams = [];
        for ($index = 0, $last = count($characters) - 1; $index < $last; ++$index) {
            $bigrams[] = $characters[$index].$characters[$index + 1];
        }

        return $bigrams;
    }

    /**
     * Sørensen-Dice similarity for multisets.
     *
     * @param list<string> $left
     * @param list<string> $right
     */
    private function dice(array $left, array $right): int
    {
        if ([] === $left || [] === $right) {
            return 0;
        }

        $rightCounts = array_count_values($right);
        $intersection = 0;
        foreach ($left as $value) {
            if (($rightCounts[$value] ?? 0) <= 0) {
                continue;
            }
            ++$intersection;
            --$rightCounts[$value];
        }

        return (int) round(200 * $intersection / (count($left) + count($right)));
    }
}
