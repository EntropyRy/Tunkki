<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Event;
use App\Entity\Ticket;
use App\Repository\CheckoutRepository;
use App\Repository\TicketRepository;
use App\Service\StripeServiceInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'entropy:checkout:backfill-receipts',
    description: 'Safely link legacy tickets to checkouts and optionally backfill Stripe receipt URLs.',
)]
final class CheckoutReceiptBackfillCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CheckoutRepository $checkoutRepo,
        private readonly TicketRepository $ticketRepo,
        private readonly StripeServiceInterface $stripe,
    ) {
        parent::__construct();
    }

    #[\Override]
    protected function configure(): void
    {
        $this
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Report what would change without persisting.',
            )
            ->addOption(
                'fetch-receipts',
                null,
                InputOption::VALUE_NONE,
                'Fetch missing receipt URLs from Stripe (network required).',
            )
            ->addOption(
                'show-ambiguous',
                null,
                InputOption::VALUE_NONE,
                'List ambiguous matches (ticket + checkout identifiers).',
            )
            ->addOption(
                'resolve-ambiguous',
                null,
                InputOption::VALUE_OPTIONAL,
                'Resolve ambiguous matches by checkout id (lowest-checkout|highest-checkout).',
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_OPTIONAL,
                'Maximum number of tickets to inspect.',
            );
    }

    #[\Override]
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $fetchReceipts = (bool) $input->getOption('fetch-receipts');
        $showAmbiguous = (bool) $input->getOption('show-ambiguous');
        $resolveAmbiguous = $input->getOption('resolve-ambiguous');
        $limitOption = $input->getOption('limit');
        $limit = null !== $limitOption ? (int) $limitOption : null;

        $resolveMode = null;
        if (\is_string($resolveAmbiguous) && '' !== $resolveAmbiguous) {
            $resolveMode = $resolveAmbiguous;
        }
        if (null !== $resolveMode && !\in_array($resolveMode, ['lowest-checkout', 'highest-checkout'], true)) {
            $io->error('Invalid --resolve-ambiguous value. Use lowest-checkout or highest-checkout.');

            return Command::INVALID;
        }

        $qb = $this->ticketRepo->createQueryBuilder('t')
            ->leftJoin('t.checkout', 'c')
            ->andWhere('c.id IS NULL')
            ->andWhere('t.status IN (:statuses)')
            ->setParameter('statuses', ['paid', 'paid_with_bus'])
            ->orderBy('t.id', 'ASC');

        if (null !== $limit && $limit > 0) {
            $qb->setMaxResults($limit);
        }

        /** @var Ticket[] $tickets */
        $tickets = $qb->getQuery()->getResult();

        $linked = 0;
        $receiptUpdated = 0;
        $problems = [
            'missing_email' => 0,
            'missing_stripe_product' => 0,
            'no_match' => 0,
            'ambiguous' => 0,
            'receipt_missing' => 0,
        ];
        $ambiguousResolved = 0;
        $ambiguousRows = [];

        foreach ($tickets as $ticket) {
            $email = $ticket->getEmail() ?? $ticket->getOwnerEmail();
            if (null === $email || '' === $email) {
                ++$problems['missing_email'];
                continue;
            }

            $stripeProductId = $ticket->getStripeProductId();
            if (null === $stripeProductId || '' === $stripeProductId) {
                ++$problems['missing_stripe_product'];
                continue;
            }

            $event = $ticket->getEvent();
            $matches = $this->findMatchingCheckouts(
                $email,
                $stripeProductId,
                $event,
            );

            if (0 === \count($matches)) {
                ++$problems['no_match'];
                continue;
            }

            if (\count($matches) > 1) {
                if (null === $resolveMode) {
                    ++$problems['ambiguous'];
                    if ($showAmbiguous) {
                        $ambiguousRows[] = [
                            'ticket_id' => (string) $ticket->getId(),
                            'ticket_email' => $email,
                            'event_id' => (string) $event->getId(),
                            'stripe_product_id' => $stripeProductId,
                            'checkout_ids' => implode(
                                ',',
                                array_map(
                                    static fn ($checkout): string => (string) $checkout->getId(),
                                    $matches,
                                ),
                            ),
                            'checkout_sessions' => implode(
                                ',',
                                array_map(
                                    static fn ($checkout): string => $checkout->getStripeSessionId(),
                                    $matches,
                                ),
                            ),
                        ];
                    }
                    continue;
                }

                $checkout = $this->resolveAmbiguousCheckout($matches, $resolveMode);
                ++$ambiguousResolved;
                $matches = [$checkout];
            }

            $checkout = $matches[0];
            $ticket->setCheckout($checkout);
            ++$linked;

            if (
                $fetchReceipts
                && null === $checkout->getReceiptUrl()
            ) {
                $receiptUrl = $this->stripe->getReceiptUrlForSessionId(
                    $checkout->getStripeSessionId(),
                );
                if (null !== $receiptUrl) {
                    $checkout->setReceiptUrl($receiptUrl);
                    ++$receiptUpdated;
                } else {
                    ++$problems['receipt_missing'];
                }
            }

            if (!$dryRun) {
                $this->em->persist($ticket);
                $this->em->persist($checkout);
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(
            \sprintf(
                'Scanned %d ticket(s). Linked %d. Receipt URLs updated: %d.%s',
                \count($tickets),
                $linked,
                $receiptUpdated,
                $dryRun ? ' (dry-run)' : '',
            ),
        );

        $problemRows = [];
        foreach ($problems as $label => $count) {
            if ($count > 0) {
                $problemRows[] = [$label, (string) $count];
            }
        }

        if ([] !== $problemRows) {
            $io->warning('Problems encountered (tickets skipped):');
            $io->table(['type', 'count'], $problemRows);
        } else {
            $io->note('No problems detected.');
        }

        if ($ambiguousResolved > 0) {
            $io->note(\sprintf('Ambiguous matches resolved by rule: %s (%d).', $resolveMode, $ambiguousResolved));
        }

        if ($showAmbiguous && [] !== $ambiguousRows) {
            $io->warning('Ambiguous matches (manual review needed):');
            $io->table(
                ['ticket_id', 'ticket_email', 'event_id', 'stripe_product_id', 'checkout_ids', 'checkout_sessions'],
                $ambiguousRows,
            );
        }

        if ($fetchReceipts) {
            $io->note('Receipt fetching uses Stripe API access; missing receipts are counted separately.');
        }

        return Command::SUCCESS;
    }

    /**
     * @return array<int, \App\Entity\Checkout>
     */
    private function findMatchingCheckouts(
        string $email,
        string $stripeProductId,
        Event $event,
    ): array {
        return $this->checkoutRepo->createQueryBuilder('c')
            ->innerJoin('c.cart', 'cart')
            ->innerJoin('cart.products', 'item')
            ->innerJoin('item.product', 'product')
            ->andWhere('cart.email = :email')
            ->andWhere('product.stripeId = :stripeId')
            ->andWhere('product.event = :event')
            ->andWhere('c.status >= :minStatus')
            ->setParameter('email', $email)
            ->setParameter('stripeId', $stripeProductId)
            ->setParameter('event', $event)
            ->setParameter('minStatus', 1)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, \App\Entity\Checkout> $matches
     */
    private function resolveAmbiguousCheckout(array $matches, string $mode): \App\Entity\Checkout
    {
        usort(
            $matches,
            static fn ($a, $b): int => $a->getId() <=> $b->getId(),
        );

        if ('highest-checkout' === $mode) {
            return $matches[\count($matches) - 1];
        }

        return $matches[0];
    }
}
