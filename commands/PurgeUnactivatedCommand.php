<?php

/* activation:purge removes the accounts that never used their activation link. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Accountactivationbyemail\Service\AccountCleaner;
use YesWiki\Wiki;

class PurgeUnactivatedCommand extends Command
{
    protected $wiki;

    public function __construct(Wiki &$wiki)
    {
        parent::__construct();
        $this->wiki = $wiki;
    }

    protected function configure()
    {
        $this
            ->setName('activation:purge')
            ->setDescription(_t('ACCOUNTACTIVATION_BY_EMAIL_CLI_PURGE'))
            ->addOption('dry-run', null, InputOption::VALUE_NONE, _t('ACCOUNTACTIVATION_BY_EMAIL_CLI_DRY_RUN'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cleaner = $this->wiki->services->get(AccountCleaner::class);
        $dryRun = (bool)$input->getOption('dry-run');
        $names = $cleaner->purgeStale($dryRun);
        foreach ($names as $name) {
            $output->writeln(($dryRun ? '[dry-run] ' : '') . $name);
        }
        $output->writeln(_t($dryRun ? 'ACCOUNTACTIVATION_BY_EMAIL_CLI_PURGE_WOULD' : 'ACCOUNTACTIVATION_BY_EMAIL_CLI_PURGE_DONE', [
            'count' => count($names),
            'days' => $cleaner->lifetimeDays(),
        ]));

        return Command::SUCCESS;
    }
}
