<?php

namespace Katalys\Shop\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Katalys\Shop\Util\DatesSenderFactory;

/**
 * QueueDatesCommand class
 */
class QueueDatesCommand extends Command
{
    const COMMAND_REVOFFERS_QUEUE_DATES = 'katalys:queue:dates';
    const FROM_ARGUMENT = 'from';
    const TO_ARGUMENT = 'to';
    const LIMIT_ARGUMENT = 'limit';
    const OFFSET_ARGUMENT = 'offset';

    /**
     * @var DatesSenderFactory
     */
    protected $datesSenderFactory;

    /**
     * @param DatesSenderFactory $datesSenderFactory
     * @param string $name
     */
    public function __construct(
        DatesSenderFactory $datesSenderFactory,
        $name = null
    ) {
        $this->datesSenderFactory = $datesSenderFactory;
        parent::__construct($name);
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $fromArg = $input->getArgument(self::FROM_ARGUMENT);
        $toArg = $input->getArgument(self::TO_ARGUMENT);
        $limitArg = $input->getArgument(self::LIMIT_ARGUMENT);
        $offsetArg = $input->getArgument(self::OFFSET_ARGUMENT);

        $dateSender = $this->datesSenderFactory->create();
        $ret = $dateSender->queue($fromArg, $toArg, $limitArg, $offsetArg);
        $output->writeln(json_encode($ret, JSON_PRETTY_PRINT));
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_REVOFFERS_QUEUE_DATES);
        $this->setDescription('Queue order(s) by date(s)');
        $this->setDefinition([
            new InputArgument(
                self::FROM_ARGUMENT,
                InputArgument::REQUIRED,
                'from Date'
            ),
            new InputArgument(
                self::TO_ARGUMENT,
                InputArgument::OPTIONAL,
                'to Date'
            ),
            new InputArgument(
                self::LIMIT_ARGUMENT,
                InputArgument::OPTIONAL,
                'limit'
            ),
            new InputArgument(
                self::OFFSET_ARGUMENT,
                InputArgument::OPTIONAL,
                'offset'
            ),
        ]);
        parent::configure();
    }
}