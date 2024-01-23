<?php

namespace OneO\Shop\Console;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * QueueOrderCommand class
 */
class QueueOrderCommand extends RecordOrderCommand
{
    const COMMAND_REVOFFERS_QUEUE_ORDER = 'katalys:queue:order';

    /**
     * @var \OneO\Shop\Model\QueueEntryFactory
     */
    protected $queueEntryFactory;

    /**
     * @param \OneO\Shop\Util\OrderPackagerFactory $orderPackagerFactory
     * @param \OneO\Shop\Model\QueueEntryFactory $queueEntryFactory
     * @param string $name
     */
    public function __construct(
        \OneO\Shop\Util\OrderPackagerFactory $orderPackagerFactory,
        \OneO\Shop\Model\QueueEntryFactory $queueEntryFactory,
        $name = null
    ) {
        parent::__construct($orderPackagerFactory, $name);
        $this->queueEntryFactory = $queueEntryFactory;
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws \Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $orderArg = $input->getArgument(self::ORDER_ARGUMENT);
        $keyArg = $input->getArgument(self::KEY_ARGUMENT);

        $orderArgParsed = $this->parseOrderArg($orderArg);
        if (!$orderArgParsed) {
            throw new \Exception("ERROR: unable to parse order argument.");
        }

        try {
            $packager = $this->orderPackagerFactory->create();

            if (!is_array($orderArgParsed)) {
                $orderIds = [ $orderArgParsed ];
            } else {
                $orderIds = $orderArgParsed;
            }

            $label = $keyArg ? '_id' : ' increment_id';
            foreach ($orderIds as $orderId) {
                $params = $packager->getParams($orderId, $keyArg);
                if (!$params) {
                    $output->writeln("order$label=$orderId : unable to get order to queue.");
                    continue;
                }
                /** @var \OneO\Shop\Model\QueueEntry $entry */
                $entry = $this->queueEntryFactory->create();
                $entry->setData('order_id', $params['order_key']); // queued orders always use key
                $entry->save();
                $output->writeln("order$label=$orderId : queued");
            }

        } catch (\Exception $e) {
            $output->writeln('EXCEPTION: ' . $e->getMessage());
        }
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();
        $this->setName(self::COMMAND_REVOFFERS_QUEUE_ORDER);
        $this->setDescription('Queue order(s)');
    }
}