<?php

namespace PAG\GitDvr\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class ReplayCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('gitdvr:replay')
            ->setDescription('Replay GIT commits')
            ->addArgument(
                'path',
                InputArgument::OPTIONAL,
                'Path of git repository',
                 getcwd()
            )
            ->addArgument(
                'start',
                InputArgument::OPTIONAL,
                'Branch/commit to start',
                'master'

            )
            ->addArgument(
                'branch',
                InputArgument::OPTIONAL,
                'Branch',
                'master'

            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $path = $input->getArgument('path');
        $start = $input->getArgument('start');
        $branch = $input->getArgument('branch');

        if (empty($path)) {
            $path = getcwd();
        }

        $output->writeln("Checking <info>$path</info>");

        if (!file_exists($path)) {
            throw new \InvalidArgumentException("$path not found");
        }

        if (!file_exists($path.DIRECTORY_SEPARATOR.'.git')) {
            throw new \InvalidArgumentException(".git directory not found inside $path");
        }

        $process = $this->gitExec($path, 'checkout ', array($branch));
        if (!$process->isSuccessful()) {
            throw new \Exception($process->getErrorOutput());
        }
        $output->writeln($process->getOutput());

        $output->write("Reading log...");

        $process = $this->gitExec($path, 'log --oneline');
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process->getErrorOutput());
        }
        //$output->writeln($process->getOutput());

        $lines = explode("\n", $process->getOutput());
        $commits = array();
        foreach($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            $parts = explode(" ", $line, 2);
            $commit = array(
                'hash' => $parts[0],
                'message' => isset($parts[1]) ? $parts[1] : '',
            );
            $commits[$parts[0]] = $commit;
        }

        $output->writeln(sprintf(" <info>%d</info> commits found.", count($commits)));

        $output->write("Setting starting point... ");

        $process = $this->gitExec($path, 'checkout ', array($start));
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process->getErrorOutput());
        }
        $output->writeln($process->getOutput());

        $output->write("Getting current commit... ");

        $process = $this->gitExec($path, 'rev-parse HEAD');
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process->getErrorOutput());
        }

        $currentCommit = trim($process->getOutput());

        $output->writeln("<info>$currentCommit</info>");


        $dialog = $this->getHelperSet()->get('dialog');

        $options = array('quit', 'previous', 'forward');

        $selection = null;
        while(null === $selection || 'quit' != $options[$selection]) {

            $this->getPrevNext($commits, $currentCommit, $prev, $next);

            $selection = $dialog->select(
                $output,
                "Current: <info>".$currentCommit."</info> \nNext:    <info>".$next['hash']."</info> ".$next['message']."\nPrev:    <info>".$prev['hash']."</info> ".$prev['message'].'',
                $options,
                2
            );
            if ('quit' == $options[$selection]) {
                continue;
            }

            if ($options[$selection] == 'previous' ) {
                if ($prev != null) {
                    $currentCommit = $prev['hash'];
                    $process = $this->gitExec($path, 'checkout', array($currentCommit));
                    $output->writeln($process->getOutput());

                } else {
                    $output->writeln("<error>No more previous commits</error>");
                }

            }

            if ($options[$selection] == 'forward') {
                if ($next != null) {
                    $currentCommit = $next['hash'];
                    $process = $this->gitExec($path, 'checkout', array($currentCommit));
                    $output->writeln($process->getOutput());
                } else {
                    $output->writeln("<error>No more commits</error>");
                }
            }

            //$output->writeln("$currentCommit ".$prev['hash']." ".$next['hash']);

        }
    }

    protected function getPrevNext($commits, $current, &$prev, &$next) {
        $total = count($commits);
        $commits = array_values($commits);
        foreach($commits as $idx => $commit) {
            $prev = $idx+1 < $total ? $commits[$idx+1] : null;
            $next = $idx-1 >= 0 ? $commits[$idx-1] : null;
            if (strpos($current, $commit['hash']) === 0) {
                return;
            }
        }
        $next = $prev = null;
    }

    /**
     * @param $path
     * @param $command
     * @param array $args
     * @return Process
     */
    protected function gitExec($path, $command, array $args = array())
    {
        $command = 'git '.$command;
        foreach($args as $a) {
            $command .= ' '.escapeshellarg($a);
        }
        //echo "executing $command\n";
        $process = new Process($command, $path);
        $process->run();
        return $process;
    }
}
