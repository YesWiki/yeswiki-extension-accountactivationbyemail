<?php

/* activation:spam lists the accounts that look like robots, and deletes those that left nothing. Part of the YesWiki extension accountactivationbyemail, see LICENSE. */

namespace YesWiki\Accountactivationbyemail\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use YesWiki\Accountactivationbyemail\Service\AccountActivationService;
use YesWiki\Accountactivationbyemail\Service\AccountCleaner;
use YesWiki\Accountactivationbyemail\Service\SignupSpamScore;
use YesWiki\Core\Service\UserManager;
use YesWiki\Wiki;

class SpamAccountsCommand extends Command
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
            ->setName('activation:spam')
            ->setDescription(_t('ACCOUNTACTIVATION_BY_EMAIL_CLI_SPAM'))
            ->addOption('all', null, InputOption::VALUE_NONE, _t('ACCOUNTACTIVATION_BY_EMAIL_CLI_SPAM_ALL'))
            ->addOption('delete', null, InputOption::VALUE_NONE, _t('ACCOUNTACTIVATION_BY_EMAIL_CLI_SPAM_DELETE'));
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $score = $this->wiki->services->get(SignupSpamScore::class);
        $cleaner = $this->wiki->services->get(AccountCleaner::class);
        $revisions = $cleaner->revisionsByUser();
        $status = $cleaner->activationByUser();
        $delete = (bool)$input->getOption('delete');
        $rows = [];
        $deleted = 0;

        foreach ($this->wiki->services->get(UserManager::class)->getAll() as $user) {
            $written = $revisions[$user['name']] ?? 0;
            $verdict = $score->ofAccount($user->getArrayCopy(), $written);
            $spam = $score->isSpam($verdict);
            if (!$spam && !($input->getOption('all') && $verdict['score'] > 0)) {
                continue;
            }
            $action = '';
            if ($spam && $delete && $cleaner->leftNothing($user, $written)) {
                $cleaner->remove($user);
                $deleted++;
                $action = _t('ACCOUNTACTIVATION_BY_EMAIL_CLI_DELETED');
            }
            $rows[] = [
                $verdict['score'],
                ($status[$user['name']] ?? AccountActivationService::TRIPLE_ACTIVATED_VALUE) === AccountActivationService::TRIPLE_ACTIVATED_VALUE ? _t('YES') : _t('NO'),
                $user['name'],
                $user['email'],
                substr((string)$user['signuptime'], 0, 10),
                $written,
                implode(',', $verdict['reasons']),
                $action !== '' ? $action : $cleaner->deletionLink($user['name']),
            ];
        }

        usort($rows, fn ($left, $right) => $right[0] <=> $left[0] ?: strcmp($left[2], $right[2]));
        (new Table($output))
            ->setHeaders(['score', _t('ACCOUNTACTIVATION_BY_EMAIL_DELETE_ACTIVATED'), 'name', 'email', 'signup', 'revisions', 'reasons', ''])
            ->setRows($rows)
            ->render();
        if ($delete) {
            $output->writeln(_t('ACCOUNTACTIVATION_BY_EMAIL_CLI_DELETED_COUNT', ['count' => $deleted]));
        }

        return Command::SUCCESS;
    }
}
