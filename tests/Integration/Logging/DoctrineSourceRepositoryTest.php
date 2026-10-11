<?php

declare(strict_types=1);

namespace Watchdog\Tests\Integration\Logging;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Watchdog\Logging\Domain\ProjectId;
use Watchdog\Logging\Domain\Source\Source;
use Watchdog\Logging\Domain\Source\SourceRepository;
use Watchdog\Logging\Domain\Source\SourceToken;

final class DoctrineSourceRepositoryTest extends KernelTestCase
{
    private const string PROJECT = '00000000-0000-7000-a000-000000000001';

    private SourceRepository $sources;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->sources = self::getContainer()->get(SourceRepository::class);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    private function source(string $name, SourceToken $token, string $projectId = self::PROJECT, string $createdAt = '2026-01-01 12:00:00'): Source
    {
        $source = Source::create($this->sources->nextIdentity(), ProjectId::fromString($projectId), $name, $token, new \DateTimeImmutable($createdAt));
        $this->sources->save($source);

        return $source;
    }

    public function testSavedSourceIsHydratedAndFoundByTokenHash(): void
    {
        $token = SourceToken::generate();
        $id = $this->source('API', $token)->id();
        $this->em->clear();

        $source = $this->sources->ofTokenHash($token->hash());

        self::assertNotNull($source);
        self::assertTrue($id->equals($source->id()));
        self::assertSame('API', $source->name());
        self::assertSame($token->prefix(), $source->tokenPrefix());
        self::assertFalse($source->isRevoked());
        self::assertNull($this->sources->ofTokenHash(SourceToken::generate()->hash()));
    }

    public function testDatabaseNeverContainsThePlainToken(): void
    {
        $token = SourceToken::generate();
        $this->source('API', $token);

        $rows = $this->em->getConnection()->fetchAllAssociative('SELECT * FROM log_source');

        self::assertStringNotContainsString($token->value, json_encode($rows, \JSON_THROW_ON_ERROR));
    }

    public function testRevocationIsPersisted(): void
    {
        $source = $this->source('API', SourceToken::generate());
        $source->revoke(new \DateTimeImmutable('2026-02-01 08:00:00'));
        $this->sources->save($source);
        $this->em->clear();

        self::assertSame('2026-02-01 08:00:00', $this->sources->ofId($source->id())?->revokedAt()?->format('Y-m-d H:i:s'));
    }

    public function testOfProjectReturnsOnlyThatProjectOldestFirst(): void
    {
        $this->source('Newer', SourceToken::generate(), createdAt: '2026-03-01');
        $this->source('Older', SourceToken::generate(), createdAt: '2026-01-01');
        $this->source('Other project', SourceToken::generate(), '00000000-0000-7000-a000-000000000002');
        $this->em->clear();

        $names = array_map(static fn (Source $s): string => $s->name(), $this->sources->ofProject(ProjectId::fromString(self::PROJECT)));

        self::assertSame(['Older', 'Newer'], $names);
    }
}
