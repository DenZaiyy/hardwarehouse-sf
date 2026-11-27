<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Carrier;
use App\Tests\Support\CreatesShopEntities;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\FileFormField;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Écrans du back-office avec des données comme celles de la production : un compte avec avatar, un transporteur
 * enregistré avec son logo.
 */
final class AdminWithProductionDataTest extends WebTestCase
{
    use CreatesShopEntities;

    public function testUserListShowsAnAccountWithAnAvatar(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $this->createUser('avec-avatar')->setAvatar('avatar-test.png');
        $this->entityManager()->flush();

        $client->request('GET', '/fr/admin/user');

        self::assertResponseIsSuccessful();
    }

    public function testCarrierIsCreatedWithItsLogo(): void
    {
        $client = static::createClient();
        $client->loginUser($this->createUser('admin')->setRoles(['ROLE_SUPER_ADMIN']));
        $this->entityManager()->flush();
        $logo = tempnam(sys_get_temp_dir(), 'logo').'.png';
        file_put_contents($logo, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=', true));

        $crawler = $client->request('GET', '/fr/admin/carrier/new');
        $form = $crawler->selectButton('Créer')->form([
            'Carrier[name]' => 'DHL Express test',
            'Carrier[price]' => '14,90',
        ]);
        $file = $form['Carrier[logo][file]'];
        self::assertInstanceOf(FileFormField::class, $file);
        $file->upload($logo);
        $client->submit($form);

        self::assertResponseRedirects();
        $carrier = $this->entityManager()->getRepository(Carrier::class)->findOneBy(['name' => 'DHL Express test']);
        self::assertNotNull($carrier);
        self::assertNotNull($carrier->getLogo());
        $projectDir = static::getContainer()->getParameter('kernel.project_dir');
        self::assertIsString($projectDir);
        @unlink($projectDir.'/public/uploads/images/carriers/'.$carrier->getLogo());
        @unlink($logo);
    }
}
