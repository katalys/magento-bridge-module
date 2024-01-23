<?php

namespace OneO\Shop\Console;

use OneO\Shop\Util\OrderPackagerFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * RecordOrderCommand class
 */
class RecordOrderCommand extends Command
{
    const COMMAND_REVOFFERS_RECORD_ORDER = 'katalys:record:order';
    const ORDER_ARGUMENT = 'order';
    const KEY_ARGUMENT = 'key';

    /**
     * @var OrderPackagerFactory
     */
    protected $orderPackagerFactory;

    /**
     * @param OrderPackagerFactory $orderPackagerFactory
     * @param string $name
     */
    public function __construct(
        OrderPackagerFactory $orderPackagerFactory,
        $name = null
    ) {
        $this->orderPackagerFactory = $orderPackagerFactory;
        parent::__construct($name);
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
                    $output->writeln("order$label=$orderId : unable to get params to send.");
                    continue;
                }
                $params['action'] = 'offline_conv';
                $res = \OneO\Shop\Util\Curl::post($params);
                if ($res) {
                    $res->callback = function($out, $info) use ($orderId, $output, $label) {
                        $output->writeln("order$label=$orderId" . ' : http status=' . $info['http_code']);
                    };
                }
            }

        } catch (\Exception $e) {
            $output->writeln('EXCEPTION: ' . $e->getMessage());
        }
    }

    /**
     * Parse order argument by parsing on non-numeric characters and removing falsy
     *
     * @param $orderArg
     * @return array
     */
    protected function parseOrderArg($orderArg)
    {
        if (preg_match('/^\d+$/', $orderArg)) {
            return $orderArg;
        }

        return array_filter(preg_split('/[^\d]+/', $orderArg), function($v) {
            return !!$v;
        });
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_REVOFFERS_RECORD_ORDER);
        $this->setDescription('Record order(s)');
        $this->setDefinition([
            new InputArgument(
                self::ORDER_ARGUMENT,
                InputArgument::REQUIRED,
                'Order ID can be simple string or csv ex. "123,456"'
            ),
            new InputArgument(
                self::KEY_ARGUMENT,
                InputArgument::OPTIONAL,
                'optional field that sets whether to use primary "key" or not'
            ),
        ]);
        parent::configure();
    }

}