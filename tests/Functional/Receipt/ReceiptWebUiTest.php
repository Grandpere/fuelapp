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

namespace App\Tests\Functional\Receipt;

use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptEntity;
use App\Receipt\Infrastructure\Persistence\Doctrine\Entity\ReceiptLineEntity;
use App\Station\Infrastructure\Persistence\Doctrine\Entity\StationEntity;
use App\User\Infrastructure\Persistence\Doctrine\Entity\UserEntity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

final class ReceiptWebUiTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private UserPasswordHasherInterface $passwordHasher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();
        $this->client->disableReboot();
        $container = static::getContainer();

        $em = $container->get(EntityManagerInterface::class);
        if (!$em instanceof EntityManagerInterface) {
            throw new RuntimeException('EntityManager service is invalid.');
        }
        $this->em = $em;

        $passwordHasher = $container->get(UserPasswordHasherInterface::class);
        if (!$passwordHasher instanceof UserPasswordHasherInterface) {
            throw new RuntimeException('Password hasher service is invalid.');
        }
        $this->passwordHasher = $passwordHasher;

        $this->em->getConnection()->executeStatement('TRUNCATE TABLE maintenance_planned_costs, maintenance_reminders, maintenance_reminder_rules, maintenance_events, vehicles, import_jobs, user_identities, receipt_lines, receipts, stations, users CASCADE');
    }

    public function testUserCanEditReceiptLinesFromUi(): void
    {
        $email = 'receipt.ui.editor@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('Edit Station');
        $station->setStreetName('1 Edit Street');
        $station->setPostalCode('75011');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-03 11:00:00'));
        $receipt->setTotalCents(1800);
        $receipt->setVatAmountCents(300);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(10000);
        $line->setUnitPriceDeciCentsPerLiter(1800);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $receiptId = $receipt->getId()->toRfc4122();
        $this->loginWithUiForm($email, $password);

        $editPage = $this->request('GET', '/ui/receipts/'.$receiptId.'/edit');
        self::assertSame(Response::HTTP_OK, $editPage->getStatusCode());
        $csrf = $this->extractFormCsrf((string) $editPage->getContent());

        $editResponse = $this->request(
            'POST',
            '/ui/receipts/'.$receiptId.'/edit',
            [
                '_token' => $csrf,
                'lines' => [
                    [
                        'fuelType' => 'sp95',
                        'quantityLiters' => '12.000',
                        'unitPriceEurosPerLiter' => '1.750',
                        'vatRatePercent' => '20',
                    ],
                ],
            ],
        );
        self::assertSame(Response::HTTP_SEE_OTHER, $editResponse->getStatusCode());

        $this->em->clear();
        $updated = $this->em->find(ReceiptEntity::class, $receiptId);
        self::assertInstanceOf(ReceiptEntity::class, $updated);
        $updatedLines = $updated->getLines()->toArray();
        self::assertCount(1, $updatedLines);
        $updatedLine = $updatedLines[0];
        self::assertInstanceOf(ReceiptLineEntity::class, $updatedLine);
        self::assertSame('sp95', $updatedLine->getFuelType());
        self::assertSame(12000, $updatedLine->getQuantityMilliLiters());
        self::assertSame(1750, $updatedLine->getUnitPriceDeciCentsPerLiter());
    }

    public function testUserCanCreateReceiptFromHumanFriendlyUnits(): void
    {
        $email = 'receipt.ui.create@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $createPage = $this->request('GET', '/ui/receipts/new');
        self::assertSame(Response::HTTP_OK, $createPage->getStatusCode());
        $createContent = (string) $createPage->getContent();
        self::assertStringContainsString('Quantity (L)', $createContent);
        self::assertStringContainsString('Unit price (€/L)', $createContent);
        $csrf = $this->extractFormCsrf($createContent);

        $createResponse = $this->request('POST', '/ui/receipts/new', [
            '_token' => $csrf,
            'issuedAt' => '2026-03-05T14:20',
            'fuelType' => 'diesel',
            'quantityLiters' => '40,40',
            'unitPriceEurosPerLiter' => '1,769',
            'vatRatePercent' => '20',
            'stationName' => 'PETRO EST',
            'stationStreetName' => 'LECLERC SEZANNE HYPER',
            'stationPostalCode' => '51120',
            'stationCity' => 'SEZANNE',
            'latitudeMicroDegrees' => '',
            'longitudeMicroDegrees' => '',
            'odometerKilometers' => '120450',
        ]);
        self::assertSame(Response::HTTP_SEE_OTHER, $createResponse->getStatusCode());

        $this->em->clear();
        $receipts = $this->em->getRepository(ReceiptEntity::class)->findAll();
        self::assertCount(1, $receipts);
        $receipt = $receipts[0];
        self::assertInstanceOf(ReceiptEntity::class, $receipt);
        self::assertSame($owner->getId()->toRfc4122(), $receipt->getOwner()?->getId()->toRfc4122());
        self::assertSame(120450, $receipt->getOdometerKilometers());
        $lines = $receipt->getLines()->toArray();
        self::assertCount(1, $lines);
        $line = $lines[0];
        self::assertInstanceOf(ReceiptLineEntity::class, $line);
        self::assertSame(40400, $line->getQuantityMilliLiters());
        self::assertSame(1769, $line->getUnitPriceDeciCentsPerLiter());
    }

    public function testReceiptIndexRowsUseSharedRowLinkNavigation(): void
    {
        $email = 'receipt.ui.list@example.com';
        $password = 'test1234';
        $owner = $this->createUser($email, $password, ['ROLE_USER']);

        $station = new StationEntity();
        $station->setId(Uuid::v7());
        $station->setName('List Station');
        $station->setStreetName('9 View Street');
        $station->setPostalCode('75012');
        $station->setCity('Paris');
        $this->em->persist($station);

        $receipt = new ReceiptEntity();
        $receipt->setId(Uuid::v7());
        $receipt->setOwner($owner);
        $receipt->setStation($station);
        $receipt->setIssuedAt(new DateTimeImmutable('2026-03-04 09:30:00'));
        $receipt->setTotalCents(2200);
        $receipt->setVatAmountCents(367);

        $line = new ReceiptLineEntity();
        $line->setId(Uuid::v7());
        $line->setFuelType('diesel');
        $line->setQuantityMilliLiters(12000);
        $line->setUnitPriceDeciCentsPerLiter(1833);
        $line->setVatRatePercent(20);
        $receipt->addLine($line);

        $this->em->persist($receipt);
        $this->em->flush();

        $this->loginWithUiForm($email, $password);

        $response = $this->request('GET', '/ui/receipts');
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = (string) $response->getContent();
        self::assertStringContainsString('data-controller="row-link"', $content);
        self::assertStringContainsString('data-row-link-url-value="/ui/receipts/'.$receipt->getId()->toRfc4122().'"', $content);
    }

    /**
     * @param array<string, string|int|float|bool|array<int, array<string, string>>|null> $parameters
     * @param array<string, string>                                                       $server
     * @param array<string, string>                                                       $cookies
     */
    private function request(string $method, string $uri, array $parameters = [], array $server = [], array $cookies = []): Response
    {
        $this->client->request($method, $uri, $parameters, [], $server);

        return $this->client->getResponse();
    }

    /** @return array<string, string> */
    private function loginWithUiForm(string $email, string $password): array
    {
        $loginPageResponse = $this->request('GET', '/ui/login');
        self::assertSame(Response::HTTP_OK, $loginPageResponse->getStatusCode());

        $content = (string) $loginPageResponse->getContent();
        preg_match('/name="_csrf_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);

        $loginResponse = $this->request(
            'POST',
            '/ui/login',
            [
                'email' => $email,
                'password' => $password,
                '_csrf_token' => $csrfToken,
            ],
        );

        self::assertSame(Response::HTTP_FOUND, $loginResponse->getStatusCode());

        return [];
    }

    private function extractFormCsrf(string $content): string
    {
        self::assertMatchesRegularExpression('/name="_token" value="([^"]+)"/', $content);
        preg_match('/name="_token" value="([^"]+)"/', $content, $matches);
        $csrfToken = $matches[1] ?? null;
        self::assertIsString($csrfToken);
        self::assertNotSame('', $csrfToken);

        return $csrfToken;
    }

    /** @param list<string> $roles */
    private function createUser(string $email, string $password, array $roles): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::v7());
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $this->em->persist($user);

        return $user;
    }
}
