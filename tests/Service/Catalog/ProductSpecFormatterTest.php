<?php

declare(strict_types=1);

namespace App\Tests\Service\Catalog;

use App\DTO\Api\Attributes\AttributeDto;
use App\DTO\Api\Attributes\CategoryAttributeDto;
use App\DTO\Api\Attributes\ProductAttributeValueDto;
use App\DTO\Catalog\ProductSpec;
use App\Enum\AttributeType;
use App\Service\Catalog\ProductSpecFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;

final class ProductSpecFormatterTest extends TestCase
{
    /**
     * @return iterable<string, array{string, AttributeType, string, string, string}>
     */
    public static function values(): iterable
    {
        yield 'unité passée sur la valeur, virgule décimale' => ['Fréquence de base (GHz)', AttributeType::NUMBER, '3.5', 'Fréquence de base', "3,5\u{a0}GHz"];
        yield 'octets écrits à la française' => ['Cache L3 (MB)', AttributeType::NUMBER, '32', 'Cache L3', "32\u{a0}Mo"];
        yield 'débit écrit à la française' => ['Lecture séquentielle (MB/s)', AttributeType::NUMBER, '7450', 'Lecture séquentielle', "7\u{202f}450\u{a0}Mo/s"];
        yield 'tours par minute' => ['Vitesse de rotation (RPM)', AttributeType::NUMBER, '7200', 'Vitesse de rotation', "7\u{202f}200\u{a0}tr/min"];
        yield 'nombre sans unité' => ['Nombre de cœurs', AttributeType::NUMBER, '6', 'Nombre de cœurs', '6'];
        yield 'texte gardé tel quel' => ['Socket', AttributeType::TEXT, 'AM4', 'Socket', 'AM4'];
        yield 'liste gardée telle quelle, avec son unité' => ['Radiateurs acceptés (mm)', AttributeType::MULTI_SELECT, '120, 240, 280', 'Radiateurs acceptés', "120, 240, 280\u{a0}mm"];
        yield 'parenthèse qui n\'est pas une unité' => ['USB (Lightspeed)', AttributeType::TEXT, 'Oui', 'USB (Lightspeed)', 'Oui'];
    }

    #[DataProvider('values')]
    public function testValueIsWrittenForTheCustomer(string $name, AttributeType $type, string $value, string $label, string $expected): void
    {
        $spec = $this->formatter()->format(self::value($name, $type, $value));

        self::assertNotNull($spec);
        self::assertSame([$label, $expected], [$spec->label, $spec->value]);
    }

    public function testBooleanReadsYesOrNo(): void
    {
        $formatter = $this->formatter();

        self::assertEquals(new ProductSpec('Graphiques intégrés', 'Oui', false), $formatter->format(self::value('Graphiques intégrés', AttributeType::BOOLEAN, 'true')));
        self::assertEquals(new ProductSpec('Graphiques intégrés', 'Non', false), $formatter->format(self::value('Graphiques intégrés', AttributeType::BOOLEAN, 'false')));
    }

    public function testSpecsFollowTheDisplayOrderOfTheCategory(): void
    {
        $specs = $this->formatter()->specs([
            self::value('TDP (W)', AttributeType::NUMBER, '65', order: 2),
            self::value('Socket', AttributeType::TEXT, 'AM4', order: 0),
            self::value('Nombre de cœurs', AttributeType::NUMBER, '6', order: 1),
        ]);

        self::assertSame(['Socket', 'Nombre de cœurs', 'TDP'], array_map(static fn (ProductSpec $spec): string => $spec->label, $specs));
    }

    public function testHighlightsAreTheFirstFourSingleValuesWithoutYesOrNoNorLists(): void
    {
        $formatter = $this->formatter();
        $specs = $formatter->specs([
            self::value('Socket', AttributeType::TEXT, 'AM4', order: 0),
            self::value('Graphiques intégrés', AttributeType::BOOLEAN, 'false', order: 1),
            self::value('Mémoire prise en charge', AttributeType::MULTI_SELECT, 'DDR4, DDR5', order: 2),
            self::value('Nombre de cœurs', AttributeType::NUMBER, '6', order: 3),
            self::value('Nombre de threads', AttributeType::NUMBER, '12', order: 4),
            self::value('Fréquence de base (GHz)', AttributeType::NUMBER, '3.5', order: 5),
            self::value('TDP (W)', AttributeType::NUMBER, '65', order: 6),
        ]);

        self::assertSame(
            ['Socket', 'Nombre de cœurs', 'Nombre de threads', 'Fréquence de base'],
            array_map(static fn (ProductSpec $spec): string => $spec->label, $formatter->highlights($specs)),
        );
    }

    public function testValueWithoutAttributeDefinitionIsLeftOut(): void
    {
        self::assertSame([], $this->formatter()->specs([new ProductAttributeValueDto(id: 'valeur', value: 'AM4')]));
    }

    private function formatter(): ProductSpecFormatter
    {
        $translator = new Translator('fr');
        $translator->addLoader('array', new ArrayLoader());
        $translator->addResource('array', ['listing.product.specs.boolean_true' => 'Oui', 'listing.product.specs.boolean_false' => 'Non'], 'fr');

        return new ProductSpecFormatter($translator);
    }

    private static function value(string $name, AttributeType $type, string $value, int $order = 0): ProductAttributeValueDto
    {
        return new ProductAttributeValueDto(
            id: 'valeur-'.$order,
            value: $value,
            categoryAttribute: new CategoryAttributeDto(id: 'lien-'.$order, displayOrder: $order, attribute: new AttributeDto(id: 'attribut-'.$order, name: $name, type: $type)),
        );
    }
}
