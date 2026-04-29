<?php

declare(strict_types=1);

/*
 * This file is part of a FuelApp project.
 *
 * (c) Lorenzo Marozzo <lorenzo.marozzo@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Unit\Station\Infrastructure\Suggestion;

use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestion;
use App\PublicFuelStation\Application\Search\PublicFuelStationSuggestionReader;
use App\Station\Application\Search\StationSearchCandidate;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Application\Suggestion\StationSuggestionQuery;
use App\Station\Infrastructure\Suggestion\CombinedStationSuggestionReader;
use PHPUnit\Framework\TestCase;

final class CombinedStationSuggestionReaderTest extends TestCase
{
    public function testItReturnsInternalSuggestionsBeforePublicSuggestions(): void
    {
        $reader = new CombinedStationSuggestionReader(
            new class implements StationSearchReader {
                public function search(StationSearchQuery $query): array
                {
                    return [new StationSearchCandidate('station-1', 'PETRO EST', 'LECLERC', '51120', 'SEZANNE', null, null)];
                }
            },
            new class implements PublicFuelStationSuggestionReader {
                public function search(StationSuggestionQuery $query, int $limit): array
                {
                    return [new PublicFuelStationSuggestion('public-1', '40 Rue Robert Schuman', '40 Rue Robert Schuman', '5751', 'FRISANGE', 49569000, 4230000)];
                }

                public function getBySourceId(string $sourceId): ?PublicFuelStationSuggestion
                {
                    return null;
                }
            },
        );

        $results = $reader->search(new StationSuggestionQuery('sezanne', null, null, '51120', 'SEZANNE'));

        self::assertCount(2, $results);
        self::assertSame('station', $results[0]->sourceType);
        self::assertSame('public', $results[1]->sourceType);
    }
}
