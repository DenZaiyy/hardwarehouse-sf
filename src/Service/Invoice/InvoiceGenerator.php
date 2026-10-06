<?php

declare(strict_types=1);

namespace App\Service\Invoice;

use App\Entity\Invoice;
use App\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Twig\Environment;

/**
 * Facture d'une commande payée : numéro FAC-AAAA-NNNNNN sans rupture, PDF conservé hors de public/.
 */
final readonly class InvoiceGenerator
{
    /**
     * @param array{name: string, address: string, siret: string, vat_number: string, notice: string} $seller
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private Environment $twig,
        private Filesystem $filesystem,
        #[Autowire('%kernel.project_dir%/var/invoices/%kernel.environment%')]
        private string $directory,
        #[Autowire(param: 'app.invoice_seller')]
        private array $seller,
    ) {
    }

    /** Une seule facture par commande : un second appel renvoie la première. */
    public function forOrder(Order $order): Invoice
    {
        $existing = $order->getInvoice();
        if (null !== $existing) {
            return $existing;
        }

        return $this->entityManager->wrapInTransaction(function () use ($order): Invoice {
            $issuedAt = new \DateTimeImmutable();
            $year = (int) $issuedAt->format('Y');
            $reference = \sprintf('FAC-%d-%06d', $year, $this->nextNumber($year));

            $invoice = (new Invoice())
                ->setReference($reference)
                ->setFilePath(\sprintf('%d/%s.pdf', $year, $reference))
                ->setCreatedAt($issuedAt);
            $order->setInvoice($invoice);
            $this->entityManager->persist($invoice);

            // Écrit avant la validation : si l'écriture échoue, le numéro n'est pas consommé
            $this->filesystem->dumpFile($this->absolutePath($invoice), $this->render($invoice, $order));

            return $invoice;
        });
    }

    public function absolutePath(Invoice $invoice): string
    {
        return $this->directory.'/'.$invoice->getFilePath();
    }

    private function nextNumber(int $year): int
    {
        $number = $this->entityManager->getConnection()->fetchOne(
            'INSERT INTO invoice_counter (year, last_number) VALUES (:year, 1)
             ON CONFLICT (year) DO UPDATE SET last_number = invoice_counter.last_number + 1
             RETURNING last_number',
            ['year' => $year],
        );

        if (!is_numeric($number)) {
            throw new \UnexpectedValueException(\sprintf('The invoice counter of %d returned no number.', $year));
        }

        return (int) $number;
    }

    private function render(Invoice $invoice, Order $order): string
    {
        $options = new Options();
        // Aucune ressource distante : le PDF ne déclenche aucune requête sortante
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->twig->render('invoice/invoice.html.twig', [
            'invoice' => $invoice,
            'order' => $order,
            'seller' => $this->seller,
        ]));
        $dompdf->setPaper('A4');
        $dompdf->render();

        return (string) $dompdf->output();
    }
}
