<?php

declare(strict_types=1);

/*
 * This file is part of DivineNii opensource projects.
 *
 * PHP version 7.4 and above required
 *
 * @author    Divine Niiquaye Ibok <divineibok@gmail.com>
 * @copyright 2019 DivineNii (https://divinenii.com/)
 * @license   https://opensource.org/licenses/BSD-3-Clause License
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Flange\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs|Stops a local web server in a background process.
 *
 * @author Divine Niiquaye Ibok <divineibok@gmail.com>
 */
#[AsCommand('serve', 'Runs|Stops a local web server in a background process')]
final class ServerCommand extends Command
{
    protected static $defaultName = 'serve';
    protected static $defaultDescription = 'Runs|Stops a local web server in a background process';

    private string $router, $hostname, $address;
    private int $port;

    public function __construct(private ?string $documentRoot, private bool $debug)
    {
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure(): void
    {
        $this
            ->setDefinition([
                new InputArgument('addressport', InputArgument::OPTIONAL, 'The address to listen to (can be address:port, address, or port)'),
                new InputOption('docroot', 'd', InputOption::VALUE_REQUIRED, 'Document root'),
                new InputOption('router', 'r', InputOption::VALUE_REQUIRED, 'Path to custom router script'),
                new InputOption('pidfile', null, InputOption::VALUE_REQUIRED, 'PID file'),
                new InputOption('stop', 's', InputOption::VALUE_NONE, 'Stops the local web server that was started with the serve command'),
            ])
            ->setHelp(
                <<<'EOF'
The <info>%command.name%</info> command runs a local web server: By default, the server
listens on <comment>127.0.0.1</> address and the port number is automatically selected
as the first free port starting from <comment>8000</>:

  <info>php %command.full_name%</info>

If your PHP version supports <info>pcntl extension</info>, the server will run in the background
and you can keep executing other commands. Execute <comment>php %command.full_name% --stop</> to stop it.

Else command will block the console. If you want to run other commands, stop it by
pressing <comment>Control+C</> instead.

Change the default address and port by passing them as an argument:

  <info>php %command.full_name% 127.0.0.1:8080</info>

Use the <info>--docroot</info> option to change the default docroot directory:

  <info>php %command.full_name% --docroot=htdocs/</info>

Specify your own router script via the <info>--router</info> option:

  <info>php %command.full_name% --router=app/config/router.php</info>

See also: http://www.php.net/manual/en/features.commandline.webserver.php
EOF
            )
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output);

        if (!$this->debug) {
            $io->error('Running this server in production environment is NOT recommended!');

            return 1;
        }

        if ($input->getOption('stop')) {
            $pidFile = $input->getOption('pidfile') ?? self::getDefaultPidFile();

            if (!\file_exists($pidFile)) {
                $io->error('No web server is listening.');

                return 1;
            }

            if (\unlink($pidFile)) {
                $io->success('Web server stopped successfully');
            }

            return self::SUCCESS;
        }

        if (null === $documentRoot = $input->getOption('docroot') ?? $this->documentRoot) {
            $io->error('The document root directory must be either passed as first argument of the constructor or through the "--docroot" input option.');

            return 1;
        }

        if (null !== $router = $input->getOption('router')) {
            $absoluteRouterPath = \realpath($router);

            if (false === $absoluteRouterPath) {
                throw new \InvalidArgumentException(\sprintf('Router script "%s" does not exist.', $router));
            }
        }

        $this->findFrontController($this->documentRoot = $documentRoot);
        $this->router = $router ?? __DIR__ . '/../Resources/dev-router.php';
        $this->address = $this->findServerAddress($input->getArgument('addressport'));

        if (!\extension_loaded('pcntl')) {
            $io->error('This command needs the pcntl extension to run.');

            if ($io->confirm('Do you want to execute <info>built in server run</info> immediately?', false)) {
                return $this->runBlockingServer($io, $input, $output);
            }

            return 1;
        }

        try {
            $pidFile = $input->getOption('pidfile') ?? self::getDefaultPidFile();

            if ($this->isRunning($pidFile)) {
                $io->error(\sprintf('The web server has already been started. It is currently listening on http://%s. Please stop the web server before you try to start it again.', \file_get_contents($pidFile)));

                return 1;
            }

            if (self::SUCCESS === $this->start($pidFile)) {
                $message = \sprintf('Server listening on http://%s', $this->address);

                if ('' !== $displayAddress = $this->getDisplayAddress()) {
                    $message = \sprintf('Server listening on all interfaces, port %s -- see http://%s', $this->port, $displayAddress);
                }
                $io->success($message);

                if (\ini_get('xdebug.profiler_enable_trigger')) {
                    $io->comment('Xdebug profiler trigger enabled.');
                }
            }
        } catch (\Exception $e) {
            $io->error($e->getMessage());

            return 1;
        }

        return self::SUCCESS;
    }

    private static function getDefaultPidFile(): string
    {
        return \getcwd() . '/.web-server-pid';
    }

    private function runBlockingServer(SymfonyStyle $io, InputInterface $input, OutputInterface $output): int
    {
        $callback = null;
        $disableOutput = false;

        if ($output->isQuiet()) {
            $disableOutput = true;
        } else {
            $callback = static function ($type, $buffer) use ($output): void {
                if (Process::ERR === $type && $output instanceof ConsoleOutputInterface) {
                    $output = $output->getErrorOutput();
                }

                $output->write($buffer, false, OutputInterface::OUTPUT_RAW);
            };
        }

        if ('' !== $displayAddress = $this->getDisplayAddress()) {
            $message = \sprintf('Server listening on all interfaces, port %s -- see http://%s', $this->port, $displayAddress);
        }
        $io->success($message ?? \sprintf('Server listening on http://%s', $this->address));

        if (\ini_get('xdebug.profiler_enable_trigger')) {
            $io->comment('Xdebug profiler trigger enabled.');
        }
        $io->comment('Quit the server with CONTROL-C.');

        if ($this->isRunning($input->getOption('pidfile') ?? self::getDefaultPidFile())) {
            $io->error(\sprintf('A process is already listening on http://%s.', $this->address));
            $exitCode = 1;
        } else {
            $process = $this->createServerProcess();

            if ($disableOutput) {
                $process->disableOutput();
                $callback = null;
            } else {
                try {
                    $process->setTty(true);
                    $callback = null;
                } catch (\RuntimeException $e) {
                }
            }

            $process->run($callback);

            if (!$process->isSuccessful()) {
                $error = 'Server terminated unexpectedly.';

                if ($process->isOutputDisabled()) {
                    $error .= ' Run the command again with -v option for more details.';
                }

                $io->error($error);
                $exitCode = 1;
            }
        }

        return $exitCode ?? self::SUCCESS;
    }

    public function start(string $pidFile)
    {
        $pid = pcntl_fork();

        if ($pid < 0) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        if ($pid > 0) {
            return self::SUCCESS;
        }

        if (posix_setsid() < 0) {
            throw new \RuntimeException('Unable to set the child process as session leader.');
        }

        $process = $this->createServerProcess();
        $process->disableOutput();
        $process->start();

        if (!$process->isRunning()) {
            throw new \RuntimeException('Unable to start the server process.');
        }

        \file_put_contents($pidFile, $this->address);

        // stop the web server when the lock file is removed
        while ($process->isRunning()) {
            if (!\file_exists($pidFile)) {
                $process->stop();
            }

            \sleep(1);
        }

        return 1;
    }

    private function isRunning(string $pidFile): bool
    {
        if (!\file_exists($pidFile)) {
            return false;
        }

        $address = \file_get_contents($pidFile);
        $pos = \strrpos($address, ':');
        $hostname = \substr($address, 0, $pos);
        $port = \substr($address, $pos + 1);

        if (false !== $fp = @\fsockopen($hostname, (int) $port, $errno, $errstr, 1)) {
            \fclose($fp);

            return true;
        }

        \unlink($pidFile);

        return false;
    }

    /**
     * @return string contains resolved hostname if available, empty string otherwise
     */
    private function getDisplayAddress(): string
    {
        if ('0.0.0.0' !== $this->hostname) {
            return '';
        }

        if (false === $localHostname = \gethostname()) {
            return '';
        }

        return \gethostbyname($localHostname) . ':' . $this->port;
    }

    private function findServerAddress(?string $address): string
    {
        if (null === $address) {
            $this->hostname = '127.0.0.1';
            $this->port = $this->findBestPort();
        } elseif (false !== $pos = \mb_strrpos($address, ':')) {
            $this->hostname = \mb_substr($address, 0, $pos);

            if ('*' === $this->hostname) {
                $this->hostname = '0.0.0.0';
            }
            $this->port = (int) \mb_substr($address, $pos + 1);
        } elseif (\ctype_digit($address)) {
            $this->hostname = '127.0.0.1';
            $this->port = (int) $address;
        } else {
            $this->hostname = $address;
            $this->port = $this->findBestPort();
        }

        return $this->hostname . ':' . $this->port;
    }

    private function findBestPort(): int
    {
        $port = 8000;

        while (false !== $fp = @\fsockopen($this->hostname, $port, $errno, $errstr, 1)) {
            \fclose($fp);

            if ($port++ >= 8100) {
                throw new \RuntimeException('Unable to find a port available to run the web server.');
            }
        }

        return $port;
    }

    private function findFrontController(string $documentRoot): void
    {
        $fileNames = ['index.php', 'app_' . ($env = $this->debug ? 'debug' : 'prod') . '.php', 'app.php', 'server.php', 'server_' . $env . '.php'];

        if (!\is_dir($documentRoot)) {
            throw new \InvalidArgumentException(\sprintf('The document root directory "%s" does not exist.', $documentRoot));
        }

        foreach ($fileNames as $fileName) {
            if (\file_exists($documentRoot . '/' . $fileName)) {
                $_ENV['APP_FRONT_CONTROLLER'] = $fileName;

                return;
            }
        }

        throw new \InvalidArgumentException(\sprintf('Unable to find the front controller under "%s" (none of these files exist: %s).', $documentRoot, \implode(', ', $fileNames)));
    }

    private function createServerProcess(): Process
    {
        $finder = new PhpExecutableFinder();

        if (false === $binary = $finder->find(false)) {
            throw new \RuntimeException('Unable to find the PHP binary.');
        }

        $xdebugArgs = \ini_get('xdebug.profiler_enable_trigger') ? ['-dxdebug.profiler_enable_trigger=1'] : [];

        $process = new Process(\array_merge([$binary], $finder->findArguments(), $xdebugArgs, ['-dvariables_order=EGPCS', '-S', $this->address, $this->router]));
        $process->setWorkingDirectory($this->documentRoot);
        $process->setTimeout(null);

        if (\in_array('APP_ENV', \explode(',', \getenv('SYMFONY_DOTENV_VARS') ?: ''))) {
            $process->setEnv(['APP_ENV' => false]);
        }

        return $process;
    }
}
