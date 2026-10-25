<?php

declare(strict_types=1);

namespace Watchdog\Tests\Functional;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Watchdog\Identity\Application\Command\CreateUser;
use Watchdog\Identity\Domain\Role\Role;
use Watchdog\Project\Application\Query\ListProjects;

final class ProjectPagesTest extends WebTestCase
{
    use SignsIn;

    private const string USER = 'user@example.com';
    private const string ADMIN = 'root@example.com';
    private const string UNKNOWN = '01890000-0000-7000-8000-000000000000';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $createUser = self::getContainer()->get(CreateUser::class);
        $createUser(self::USER, self::PASSWORD);
        $createUser(self::ADMIN, self::PASSWORD, [Role::USER, Role::SUPER_ADMIN]);
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        return array_map(static fn ($p): string => $p->name, (self::getContainer()->get(ListProjects::class))());
    }

    public function testProjectLifecycle(): void
    {
        $this->logIn($this->client, self::ADMIN);

        $this->client->request('GET', '/projects');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.empty', 'Nie masz jeszcze projektów');

        $this->client->submitForm('Dodaj projekt', ['project[name]' => '  Watchdog  ']);
        self::assertResponseRedirects('/projects');
        $crawler = $this->client->followRedirect();
        self::assertSame(['Watchdog'], $this->names());
        self::assertSelectorTextContains('.rows', 'Watchdog');
        self::assertSelectorTextContains('.notice', 'Projekt został dodany.');

        $this->client->submit($crawler->selectButton('Zapisz')->form(['project[name]' => 'Renamed']));
        self::assertResponseRedirects('/projects');
        $crawler = $this->client->followRedirect();
        self::assertSame(['Renamed'], $this->names());

        $this->client->submit($crawler->selectButton('Usuń projekt')->form());
        self::assertResponseRedirects('/projects');
        $this->client->followRedirect();
        self::assertSame([], $this->names());
        self::assertSelectorTextContains('.notice', 'Projekt został usunięty.');
    }

    public function testInvalidNameIsRejected(): void
    {
        $this->logIn($this->client, self::ADMIN);
        $this->client->request('GET', '/projects');

        $this->client->submitForm('Dodaj projekt', ['project[name]' => '   ']);
        self::assertResponseStatusCodeSame(422);

        $this->client->submitForm('Dodaj projekt', ['project[name]' => str_repeat('x', 101)]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->names());
    }

    public function testOpeningAProjectLandsOnItsLiveTailAndHomeComesBack(): void
    {
        $id = (self::getContainer()->get(\Watchdog\Project\Application\Command\CreateProject::class))('Sklep')->value;
        $this->logIn($this->client, self::ADMIN);

        $this->client->request('GET', '/');
        self::assertResponseRedirects('/projects');

        $this->client->request('GET', "/projects/{$id}");
        self::assertResponseRedirects("/projects/{$id}/live");

        $this->client->request('GET', "/projects/{$id}/live");
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/');
        self::assertResponseRedirects("/projects/{$id}/live");
    }

    public function testUnknownProjectIsNotFound(): void
    {
        $this->logIn($this->client, self::ADMIN);

        $this->client->request('GET', '/projects/'.self::UNKNOWN.'/sources');
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/projects/'.self::UNKNOWN.'/live');
        self::assertResponseStatusCodeSame(404);
    }

    public function testUserWithoutPermissionIsForbidden(): void
    {
        $this->logIn($this->client, self::USER);

        $this->client->request('GET', '/projects');
        self::assertResponseStatusCodeSame(403);
    }

    public function testDeletingNeedsTheCsrfToken(): void
    {
        $id = (self::getContainer()->get(\Watchdog\Project\Application\Command\CreateProject::class))('Sklep')->value;
        $this->logIn($this->client, self::ADMIN);

        $this->client->request('POST', "/projects/{$id}/delete", ['_token' => 'forged']);
        self::assertResponseRedirects('http://localhost/login', message: 'refused, not deleted');
        self::assertSame(['Sklep'], $this->names());
    }
}
