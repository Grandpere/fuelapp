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

namespace App\Station\Infrastructure\Search;

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Station\Application\Search\StationSearchCandidate;
use App\Station\Application\Search\StationSearchQuery;
use App\Station\Application\Search\StationSearchReader;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

final readonly class DoctrineStationSearchReader implements StationSearchReader
{
    public function __construct(
        private EntityManagerInterface $em,
        private TokenStorageInterface $tokenStorage,
    ) {
    }

    public function search(StationSearchQuery $query): array
    {
        $qb = $this->em->getRepository(StationEntity::class)->createQueryBuilder('s')
            ->select('DISTINCT s')
            ->leftJoin(ReceiptEntity::class, 'r', 'WITH', 'r.station = s');

        $this->applyReadableByCurrentUser($qb, 'r');
        $this->applyFreeTextFilter($qb, $query);
        $qb->setMaxResults($this->fetchLimit($query));

        $entities = $qb->getQuery()->getResult();
        if (!is_iterable($entities)) {
            return [];
        }

        $scoredResults = [];
        foreach ($entities as $entity) {
            if (!$entity instanceof StationEntity) {
                continue;
            }

            $candidate = new StationSearchCandidate(
                $entity->getId()->toRfc4122(),
                $entity->getName(),
                $entity->getStreetName(),
                $entity->getPostalCode(),
                $entity->getCity(),
                $entity->getLatitudeMicroDegrees(),
                $entity->getLongitudeMicroDegrees(),
            );

            $score = $this->scoreCandidate($candidate, $query);
            if ($score <= 0) {
                continue;
            }

            $scoredResults[] = [
                'candidate' => $candidate,
                'score' => $score,
            ];
        }

        usort(
            $scoredResults,
            static fn (array $left, array $right): int => [$right['score'], $left['candidate']->name, $left['candidate']->city, $left['candidate']->streetName]
                <=> [$left['score'], $right['candidate']->name, $right['candidate']->city, $right['candidate']->streetName],
        );

        return array_map(
            static fn (array $row): StationSearchCandidate => $row['candidate'],
            array_slice($scoredResults, 0, max(1, $query->limit)),
        );
    }

    private function applyReadableByCurrentUser(QueryBuilder $qb, string $receiptAlias): void
    {
        $currentUserId = $this->currentUserId();
        if (null === $currentUserId) {
            $qb->andWhere('1 = 0');

            return;
        }

        $qb->andWhere(sprintf('IDENTITY(%s.owner) = :currentOwnerId', $receiptAlias))
            ->setParameter('currentOwnerId', $currentUserId);
    }

    private function applyFreeTextFilter(QueryBuilder $qb, StationSearchQuery $query): void
    {
        $terms = $this->searchTerms($query);
        if ([] === $terms) {
            return;
        }

        $orConditions = [];
        foreach ($terms as $index => $term) {
            $param = 'term'.$index;
            $orConditions[] = sprintf(
                '(LOWER(s.name) LIKE :%1$s OR LOWER(s.streetName) LIKE :%1$s OR LOWER(s.city) LIKE :%1$s OR s.postalCode LIKE :%1$s)',
                $param,
            );
            $qb->setParameter($param, '%'.$term.'%');
        }

        $qb->andWhere(implode(' OR ', $orConditions));
    }

    /**
     * @return list<string>
     */
    private function searchTerms(StationSearchQuery $query): array
    {
        $parts = array_filter([
            $query->freeText,
            $query->name,
            $query->streetName,
            $query->postalCode,
            $query->city,
        ], static fn (?string $value): bool => null !== $value && '' !== trim($value));

        if ([] === $parts) {
            return [];
        }

        $merged = mb_strtolower(implode(' ', array_map(static fn (string $value): string => trim($value), $parts)));

        /** @var list<string> $terms */
        $terms = array_values(array_filter(preg_split('/\s+/', $merged) ?: []));

        return array_values(array_unique($terms));
    }

    private function scoreCandidate(StationSearchCandidate $candidate, StationSearchQuery $query): int
    {
        $score = 0;
        $matchedTerms = 0;
        $terms = $this->searchTerms($query);
        $freeText = mb_strtolower(trim((string) ($query->freeText ?? '')));
        $haystack = mb_strtolower(implode(' ', [
            $candidate->name,
            $candidate->streetName,
            $candidate->postalCode,
            $candidate->city,
        ]));

        foreach ($terms as $term) {
            if (str_contains($haystack, $term)) {
                ++$matchedTerms;
                ++$score;
            }
        }

        $candidatePostal = mb_strtolower(trim($candidate->postalCode));
        $candidateCity = mb_strtolower(trim($candidate->city));
        $candidateName = mb_strtolower(trim($candidate->name));
        $candidateStreet = mb_strtolower(trim($candidate->streetName));

        $queryPostal = mb_strtolower(trim((string) ($query->postalCode ?? '')));
        $queryCity = mb_strtolower(trim((string) ($query->city ?? '')));
        $queryName = mb_strtolower(trim((string) ($query->name ?? '')));
        $queryStreet = mb_strtolower(trim((string) ($query->streetName ?? '')));

        if ('' !== $queryPostal && $candidatePostal === $queryPostal) {
            $score += 6;
        }

        if ('' !== $queryCity && $candidateCity === $queryCity) {
            $score += 5;
        }

        if ('' !== $queryName && str_contains($candidateName, $queryName)) {
            $score += 4;
        }

        if ('' !== $queryStreet && str_contains($candidateStreet, $queryStreet)) {
            $score += 4;
        }

        if ('' !== $freeText) {
            if (str_contains($candidateName, $freeText)) {
                $score += 5;
            } elseif (str_contains($candidateCity, $freeText) || str_contains($candidateStreet, $freeText)) {
                $score += 3;
            }
        }

        if (
            '' !== $queryPostal
            && $candidatePostal !== $queryPostal
            && '' !== $queryCity
            && $candidateCity !== $queryCity
            && $matchedTerms < 3
        ) {
            return 0;
        }

        if ($matchedTerms >= 2) {
            return $score;
        }

        if ('' !== $freeText && str_contains($candidateName, $freeText)) {
            return $score;
        }

        return $score >= 4 ? $score : 0;
    }

    private function currentUserId(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (null === $token) {
            return null;
        }

        $user = $token->getUser();
        if (!$user instanceof UserEntity) {
            return null;
        }

        return $user->getId()->toRfc4122();
    }

    private function fetchLimit(StationSearchQuery $query): int
    {
        return max(50, $query->limit * 20);
    }
}
