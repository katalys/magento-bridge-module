<?php

namespace OneO\Shop\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * RecordDatesCommand class
 */
class RecordDatesCommand extends Command
{
    const COMMAND_REVOFFERS_RECORD_DATES = 'katalys:record:dates';
    const FROM_ARGUMENT = 'from';
    const TO_ARGUMENT = 'to';
    const LIMIT_ARGUMENT = 'limit';
    const OFFSET_ARGUMENT = 'offset';
    const TIMEOUT_ARGUMENT = 'timeout';

    /**
     * @var \OneO\Shop\Util\DatesSenderFactory
     */
    protected $datesSenderFactory;

    /**
     * @param \OneO\Shop\Util\DatesSenderFactory $datesSenderFactory
     * @param string $name
     */
    public function __construct(
        \OneO\Shop\Util\DatesSenderFactory $datesSenderFactory,
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
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fromArg = $input->getArgument(self::FROM_ARGUMENT);
        $toArg = $input->getArgument(self::TO_ARGUMENT);
        $limitArg = $input->getArgument(self::LIMIT_ARGUMENT);
        $offsetArg = $input->getArgument(self::OFFSET_ARGUMENT);
        $timeoutArg = $input->getArgument(self::TIMEOUT_ARGUMENT);
        if (!$timeoutArg) {
            $timeoutArg = 1000000000;
        }
        $dateSender = $this->datesSenderFactory->create();
        $ret = $dateSender->send($fromArg, $toArg, $limitArg, $offsetArg, $timeoutArg);
        $output->writeln(json_encode($ret, JSON_PRETTY_PRINT));
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName(self::COMMAND_REVOFFERS_RECORD_DATES);
        $this->setDescription('Record order(s) by date(s)');
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
            new InputArgument(
                self::TIMEOUT_ARGUMENT,
                InputArgument::OPTIONAL,
                'timeout'
            ),
        ]);
        parent::configure();
    }
}