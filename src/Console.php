<?php
 /*
        This code is under MIT License

        +--------------------------------+
        |   DO NOT MODIFY THIS HEADERS   |
        +--------------------------------+-----------------+
        |   Created by BiuStudio                           |
        |   Email: support@biuhub.net                      |
        |   Link: https://www.biurad.ml                    |
        |   Source: https://github.com/biustudios/         |
        |   Real Name: Divine Niiquaye - Ghana             |
        |   Copyright Copyright (c) 2018-2019 BiuStudio    |
        |   License: https://biurad.ml/LICENSE.md          |
        +--------------------------------------------------+

        +--------------------------------------------------------------------------------+
        |   Version: 0.0.1.1, Relased at 18/02/2019 13:13 (GMT + 1.00)                       |
        +--------------------------------------------------------------------------------+

        +----------------+
        |   Tested on    |
        +----------------+-----+
        |  APACHE => 2.0.55    |
        |     PHP => 5.4       |
        +----------------------+

        +---------------------+
        |  How to report bug  |
        +---------------------+-----------------------------------------------------------------+
        |   You can e-mail me using the email addres written above. That email is also my msn   |
        |   contact, so you can use it for contact me on MSN.                                   |
        +---------------------------------------------------------------------------------------+

        +-----------+
        |  Notes    |
        +-----------+------------------------------------------------------------------------------------------------+
        |   - BiuRad's simple-as-possible architecture was inspired by several conference talks, slides              |
        |     and articles about php frameworks that - surprisingly and intentionally -                              |
        |     go back to the basics of programming, using procedural programming, static classes,                    |
        |     extremely simple constructs, not-totally-DRY code etc. while keeping the code extremely readable.      |
        |   - Features of Biuraad Php Framework
        |     +--> Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |     +--> Built with the official PHP password hashing functions, fitting the most modern password          |
                        hashing/salting web standards.                                                                    |
        |     +--> Uses [Post-Redirect-Get pattern](https://en.wikipedia.org/wiki/Post/Redirect/Get)                 |
        |     <--+ Uses URL rewriting ("beautiful URLs").                                                            |
        |   - Masses of comments                                                                                     |                                                                              |
        |     +--> Uses Libraries including Composer to load external dependencies.                                  |
        |     <--+ Proper security features, like CSRF blocking (via form tokens), encryption of cookie contents etc.|
        |   - Fits PSR-0/1/2/4 coding guideline.                                                                     |
        +------------------------------------------------------------------------------------------------------------+

        +------------------+
        |  Special Thanks  |
        +------------------+-----------------------------------------------------------------------------------------+
        |  I always thank the HTML FORUM COMMUNITY (http://www.html.it) for the advice about the regular expressions |
        |  A special thanks at github.com(http://www.github.com), because they provide me the list of php libraries, |
        |  snippets, and any more...                                                                                 |
        |  I thanks Php.net and Sololearn.com for its guildline in PHP Programming                                   |
        |  Finally, i thank Wikipedia for the countries's icons 20px                                                 |
        +------------------------------------------------------------------------------------------------------------+
*/
namespace Radion;

use Closure;
use InvalidArgumentException;
use LogicException;
use Carbon\Carbon;
use Cron\CronExpression;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\ProcessUtils;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;


use ExceptionManger as Exception;

class Command extends SymfonyCommand
{
    /**
     * The Biurad application instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The input interface implementation.
     *
     * @var \Symfony\Component\Console\Input\InputInterface
     */
    protected $input;

    /**
     * The output interface implementation.
     *
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    protected $output;

    /**
     * The console command name.
     *
     * @var string
     */
    protected $name;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description;


    /**
     * Create a new console command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct($this->name);

        $this->setDescription($this->description);

        $this->specifyParameters();
    }

    /**
     * Specify the arguments and options on the command.
     *
     * @return void
     */
    protected function specifyParameters()
    {
        foreach ($this->getArguments() as $arguments) {
            call_user_func_array(array($this, 'addArgument'), $arguments);
        }

        foreach ($this->getOptions() as $options) {
            call_user_func_array(array($this, 'addOption'), $options);
        }
    }

    /**
     * Run the console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return int
     */
    public function run(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;

        $this->output = $output;

        return parent::run($input, $output);
    }

    /**
     * Execute the console command.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return mixed
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        return $this->fire();
    }

    /**
     * Call another console command.
     *
     * @param  string  $command
     * @param  array   $arguments
     * @return int
     */
    public function call($command, array $arguments = array())
    {
        $instance = $this->getApplication()->find($command);

        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), $this->output);
    }

    /**
     * Call another console command silently.
     *
     * @param  string  $command
     * @param  array   $arguments
     * @return int
     */
    public function callSilent($command, array $arguments = array())
    {
        $instance = $this->getApplication()->find($command);

        $arguments['command'] = $command;

        return $instance->run(new ArrayInput($arguments), new NullOutput);
    }

    /**
     * Get the value of a command argument.
     *
     * @param  string  $key
     * @return string|array
     */
    public function argument($key = null)
    {
        if (is_null($key)) {
            return $this->input->getArguments();
        }

        return $this->input->getArgument($key);
    }

    /**
     * Get the value of a command option.
     *
     * @param  string  $key
     * @return string|array
     */
    public function option($key = null)
    {
        if (is_null($key)) {
            return $this->input->getOptions();
        }

        return $this->input->getOption($key);
    }

    /**
     * Confirm a question with the user.
     *
     * @param  string  $question
     * @param  bool    $default
     * @return bool
     */
    public function confirm($question, $default = false)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new ConfirmationQuestion("<question>{$question}</question> ", $default);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Prompt the user for input.
     *
     * @param  string  $question
     * @param  string  $default
     * @return string
     */
    public function ask($question, $default = null)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new Question("<question>$question</question>", $default);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Prompt the user for input with auto completion.
     *
     * @param  string  $question
     * @param  array   $choices
     * @param  string  $default
     * @return string
     */
    public function askWithCompletion($question, array $choices, $default = null)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new Question("<question>$question</question>", $default);

        $question->setAutocompleterValues($choices);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Prompt the user for input but hide the answer from the console.
     *
     * @param  string  $question
     * @param  bool    $fallback
     * @return string
     */
    public function secret($question, $fallback = true)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new Question("<question>$question</question>");

        $question->setHidden(true)->setHiddenFallback($fallback);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Give the user a single choice from an array of answers.
     *
     * @param  string  $question
     * @param  array   $choices
     * @param  string  $default
     * @param  mixed   $attempts
     * @param  bool    $multiple
     * @return bool
     */
    public function choice($question, array $choices, $default = null, $attempts = null, $multiple = null)
    {
        $helper = $this->getHelperSet()->get('question');

        $question = new ChoiceQuestion("<question>$question</question>", $choices, $default);

        $question->setMaxAttempts($attempts)->setMultiselect($multiple);

        return $helper->ask($this->input, $this->output, $question);
    }

    /**
     * Format input to textual table
     *
     * @param  array   $headers
     * @param  array   $rows
     * @param  string  $style
     * @return void
     */
    public function table(array $headers, array $rows, $style = 'default')
    {
        $table = new Table($this->output);

        $table->setHeaders($headers)->setRows($rows)->setStyle($style)->render();
    }

    /**
     * Write a string as information output.
     *
     * @param  string  $string
     * @return void
     */
    public function info($string)
    {
        $this->output->writeln("<info>$string</info>");
    }

    /**
     * Write a string as standard output.
     *
     * @param  string  $string
     * @return void
     */
    public function line($string)
    {
        $this->output->writeln($string);
    }

    /**
     * Write a string as comment output.
     *
     * @param  string  $string
     * @return void
     */
    public function comment($string)
    {
        $this->output->writeln("<comment>$string</comment>");
    }

    /**
     * Write a string as question output.
     *
     * @param  string  $string
     * @return void
     */
    public function question($string)
    {
        $this->output->writeln("<question>$string</question>");
    }

    /**
     * Write a string as error output.
     *
     * @param  string  $string
     * @return void
     */
    public function error($string)
    {
        $this->output->writeln("<error>$string</error>");
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array();
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return array();
    }

    /**
     * Get the output implementation.
     *
     * @return \Symfony\Component\Console\Output\OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Get the Biurad-Slim application instance.
     *
     * @return Application
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the Biurad-Slim application instance.
     *
     * @param  Application  $container
     * @return void
     */
    public function setContainer($container)
    {
        $this->container = $container;
    }

}

trait ConfirmableTrait
{
    /**
     * Confirm before proceeding with the action
     *
     * @param  string    $warning
     * @param  \Closure  $callback
     * @return bool
     */
    public function confirmToProceed($warning = 'Application In Production!', Closure $callback = null)
    {
        $shouldConfirm = $callback ?: $this->getDefaultConfirmCallback();

        if (call_user_func($shouldConfirm)) {
                if ($this->option('force')) return true;

                $this->comment(str_repeat('*', strlen($warning) + 12));
                $this->comment('*     ' . $warning . '     *');
                $this->comment(str_repeat('*', strlen($warning) + 12));
                $this->output->writeln('');

                $confirmed = $this->confirm('Do you really wish to run this command?(yes/no)');

                if (!$confirmed) {
                    $this->comment('Command Cancelled!');

                    return false;
                }
            }

        return true;
    }

    /**
     * Get the default confirmation callback.
     *
     * @return \Closure
     */
    protected function getDefaultConfirmCallback()
    {
        return function () {
            return $this->getContainer()->environment() == 'production';
        };
    }
}

abstract class GeneratorCommand extends Command
{
    /**
     * The filesystem instance.
     *
     * @var \Mini\Filesystem\Filesystem
     */
    protected $files;

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type;


    /**
     * Create a new controller creator command instance.
     *
     * @param  \Mini\Filesystem\Filesystem  $files
     * @return void
     */
    public function __construct(Filesystem $files)
    {
        parent::__construct();

        //
        $this->files = $files;
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    abstract protected function getStub();

    /**
     * Execute the console command.
     *
     * @return bool|null
     */
    public function fire()
    {
        $name = $this->parseName($this->getNameInput());

        $path = $this->getPath($name);

        if ($this->alreadyExists($this->getNameInput())) {
            $this->error($this->type . ' already exists!');

            return false;
        }

        $this->makeDirectory($path);

        $this->files->put($path, $this->buildClass($name));

        $this->info($this->type . ' created successfully.');
    }

    /**
     * Determine if the class already exists.
     *
     * @param  string  $rawName
     * @return bool
     */
    protected function alreadyExists($rawName)
    {
        $name = $this->parseName($rawName);

        return $this->files->exists($this->getPath($name));
    }

    /**
     * Get the destination class path.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        $name = str_replace($this->container->getNamespace(), '', $name);

        return $this->container['path'] . DS . str_replace('\\', DS, $name) . '.php';
    }

    /**
     * Parse the name and format according to the root namespace.
     *
     * @param  string  $name
     * @return string
     */
    protected function parseName($name)
    {
        $rootNamespace = $this->container->getNamespace();

        if (Str::startsWith($name, $rootNamespace)) {
            return $name;
        }

        if (Str::contains($name, '/')) {
            $name = str_replace('/', '\\', $name);
        }

        return $this->parseName($this->getDefaultNamespace(trim($rootNamespace, '\\')) . '\\' . $name);
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace;
    }

    /**
     * Build the directory for the class if necessary.
     *
     * @param  string  $path
     * @return string
     */
    protected function makeDirectory($path)
    {
        if (!$this->files->isDirectory(dirname($path))) {
            $this->files->makeDirectory(dirname($path), 0777, true, true);
        }
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceNamespace($stub, $name)->replaceClass($stub, $name);
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace('{{namespace}}', $this->getNamespace($name), $stub);

        $stub = str_replace('{{rootNamespace}}', $this->container->getNamespace(), $stub);

        return $this;
    }

    /**
     * Get the full namespace name for a given class.
     *
     * @param  string  $name
     * @return string
     */
    protected function getNamespace($name)
    {
        return trim(implode('\\', array_slice(explode('\\', $name), 0, -1)), '\\');
    }

    /**
     * Replace the class name for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return string
     */
    protected function replaceClass($stub, $name)
    {
        $class = str_replace($this->getNamespace($name) . '\\', '', $name);

        return str_replace('{{className}}', $class, $stub);
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getNameInput()
    {
        return trim($this->argument('name'));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return array(
            array('name', InputArgument::REQUIRED, 'The name of the class'),
        );
    }
}

class ConsoleManager extends SymfonyApplication
{
    /**
     * The Biurad-Slim application instance.
     *
     * @var Container
     */
    protected $container;

    /**
     * The output from the previous command.
     *
     * @var \Symfony\Component\Console\Output\BufferedOutput
     */
    protected $lastOutput;


    /**
     * Create a new Artisan console application.
     *
     * @param  Container  $container
     * @param  Dispatcher  $events
     * @param  string  $version
     * @return void
     */
    public function __construct(Container $container, Dispatcher $events, $version)
    {
        parent::__construct('Biurad Slim Framework', $version);

        $this->container = $container;

        $this->setAutoExit(false);
        $this->setCatchExceptions(false);

        $events->fire('forge.start', array($this));
    }

    /**
     * Run an Biurad-Slim console command by name.
     *
     * @param  string  $command
     * @param  array   $parameters
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output
     * @return void
     */
    public function call($command, array $parameters = array(), OutputInterface $output = null)
    {
        $parameters['command'] = $command;

        //
        $this->lastOutput = new BufferedOutput;

        $this->setCatchExceptions(false);

        $result = $this->run(new ArrayInput($parameters), $this->lastOutput);

        $this->setCatchExceptions(true);

        return $result;
    }

    /**
     * Get the output for the last run command.
     *
     * @return string
     */
    public function output()
    {
        return $this->lastOutput ? $this->lastOutput->fetch() : '';
    }

    /**
     * Add a command to the console.
     *
     * @param  \Symfony\Component\Console\Command\Command  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function add(SymfonyCommand $command)
    {
        if ($command instanceof Command) {
            $command->setContainer($this->container);
        }

        return parent::add($command);
    }

    /**
     * Add a command, resolving through the application.
     *
     * @param  string  $command
     * @return \Symfony\Component\Console\Command\Command
     */
    public function resolve($command)
    {
        return $this->add($this->container[$command]);
    }

    /**
     * Resolve an array of commands through the application.
     *
     * @param  array|mixed  $commands
     * @return void
     */
    public function resolveCommands($commands)
    {
        $commands = is_array($commands) ? $commands : func_get_args();

        foreach ($commands as $command) {
            $this->resolve($command);
        }
    }

    /**
     * Get the default input definitions for the applications.
     *
     * @return \Symfony\Component\Console\Input\InputDefinition
     */
    protected function getDefaultInputDefinition()
    {
        $definition = parent::getDefaultInputDefinition();

        $definition->addOption($this->getEnvironmentOption());

        return $definition;
    }

    /**
     * Get the global environment option for the definition.
     *
     * @return \Symfony\Component\Console\Input\InputOption
     */
    protected function getEnvironmentOption()
    {
        $message = 'The environment the command should run under.';

        return new InputOption('--env', null, InputOption::VALUE_OPTIONAL, $message);
    }

    /**
     * Set the Laravel application instance.
     *
     * @param  Application  $container
     * @return $this
     */
    public function setContainer($container)
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Set whether the Console app should auto-exit when done.
     *
     * @param  bool  $boolean
     * @return $this
     */
    public function setAutoExit($boolean)
    {
        parent::setAutoExit($boolean);

        return $this;
    }

}

class CallbackEvent extends Event
{
    /**
     * The callback to call.
     *
     * @var string
     */
    protected $callback;

    /**
     * The parameters to pass to the method.
     *
     * @var array
     */
    protected $parameters;


    /**
     * Create a new event instance.
     *
     * @param  string  $callback
     * @param  array  $parameters
     * @return void
     */
    public function __construct($callback, array $parameters = array())
    {
        $this->callback = $callback;

        $this->parameters = $parameters;

        if (!is_string($this->callback) && !is_callable($this->callback)) {
            throw new InvalidArgumentException(
                "Invalid scheduled callback event. Must be string or callable."
            );
        }
    }

    /**
     * Run the given event.
     *
     * @param  Container  $container
     * @return mixed
     *
     * @throws \Exception
     */
    public function run(Container $container)
    {
        if ($this->description) {
            touch($this->mutexPath());
        }

        try {
            $response = $container->call($this->callback, $this->parameters);
        } finally {
            $this->removeMutex();
        }

        parent::callAfterCallbacks($container);

        return $response;
    }

    /**
     * Remove the mutex file from disk.
     *
     * @return void
     */
    protected function removeMutex()
    {
        if ($this->description) {
            @unlink($this->mutexPath());
        }
    }

    /**
     * Do not allow the event to overlap each other.
     *
     * @return $this
     */
    public function withoutOverlapping()
    {
        if (!isset($this->description)) {
            throw new LogicException(
                "A scheduled event name is required to prevent overlapping. Use the 'name' method before 'withoutOverlapping'."
            );
        }

        return $this->skip(function () {
            return file_exists($this->mutexPath());
        });
    }

    /**
     * Get the mutex path for the scheduled command.
     *
     * @return string
     */
    protected function mutexPath()
    {
        return storage_path('schedule-' .md5( $this->description));
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return is_string($this->callback) ? $this->callback : 'Closure';
    }
}

class Event
{
    /**
     * The command string.
     *
     * @var string
     */
    public $command;

    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public $expression = '* * * * * *';

    /**
     * The timezone the date should be evaluated on.
     *
     * @var \DateTimeZone|string
     */
    public $timezone;

    /**
     * The user the command should run as.
     *
     * @var string
     */
    public $user;

    /**
     * The list of environments the command should run under.
     *
     * @var array
     */
    public $environments = array();

    /**
     * Indicates if the command should run in maintenance mode.
     *
     * @var bool
     */
    public $evenInMaintenanceMode = false;

    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    public $withoutOverlapping = false;

    /**
     * The filter callback.
     *
     * @var \Closure
     */
    protected $filter;

    /**
     * The reject callback.
     *
     * @var \Closure
     */
    protected $reject;

    /**
     * The location that output should be sent to.
     *
     * @var string
     */
    public $output = '/dev/null';

    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    protected $shouldAppendOutput = false;

    /**
     * The array of callbacks to be run before the event is started.
     *
     * @var array
     */
    protected $beforeCallbacks = array();

    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array
     */
    protected $afterCallbacks = array();

    /**
     * The human readable description of the event.
     *
     * @var string
     */
    public $description;


    /**
     * Create a new event instance.
     *
     * @param  string  $command
     * @return void
     */
    public function __construct($command)
    {
        $this->command = $command;

        $this->output = $this->getDefaultOutput();
    }

    /**
     * Get the default output depending on the OS.
     *
     * @return string
     */
    protected function getDefaultOutput()
    {
        return (DIRECTORY_SEPARATOR === '\\') ? 'NUL' : '/dev/null';
    }

    /**
     * Run the given event.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    public function run(Container $container)
    {
        if ((count($this->afterCallbacks) > 0) || (count($this->beforeCallbacks) > 0)) {
            $this->runCommandInForeground($container);
        } else {
            $this->runCommandInBackground();
        }
    }

    /**
     * Run the command in the background using exec.
     *
     * @return void
     */
    protected function runCommandInBackground()
    {
        chdir(base_path());

        $command = $this->buildCommand();

        exec($command);
    }

    /**
     * Run the command in the foreground.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    protected function runCommandInForeground(Container $container)
    {
        $this->callBeforeCallbacks($container);

        $command = $this->buildCommand();

        //
        $process = new Process(trim($command, '& '), base_path(), null, null, null);

        $process->run();

        $this->callAfterCallbacks($container);
    }

    /**
     * Call all of the "before" callbacks for the event.
     *
     * @param  \Mini\Container\Container  $container
     * @return void
     */
    protected function callBeforeCallbacks(Container $container)
    {
        foreach ($this->beforeCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Call all of the "after" callbacks for the event.
     *
     * @param  Container  $container
     * @return void
     */
    protected function callAfterCallbacks(Container $container)
    {
        foreach ($this->afterCallbacks as $callback) {
            $container->call($callback);
        }
    }

    /**
     * Build the command string.
     *
     * @return string
     */
    public function buildCommand()
    {
        $command = $this->compileCommand();

        if (!is_null($this->user) && !windows_os()) {
            return 'sudo -u ' .$this->user .' --  sh -c \'' .$command .'\'' ;
        }

        return $command;
    }

    /**
     * Build a command string with mutex.
     *
     * @return string
     */
    protected function compileCommand()
    {
        $output = ProcessUtils::escapeArgument($this->output);

        $redirect = $this->shouldAppendOutput ? ' >> ' : ' > ';

        if (!$this->withoutOverlapping) {
            return $this->command .$redirect .$output .' 2> &1 &';
        }

        $mutexPath = $this->mutexPath();

        if (!windows_os()) {
            return '(touch ' .$mutexPath .'; '  .$this->command .'; r m ' .$mutexPath .')'  .$redirect .$output .' 2> &1 &';
        } else {
            return '(echo \'\' > "' .$mutexPath .'" &  ' .$this->command .' &  del "'.$mutexPath .'")'  .$redirect .$output .' 2> &1 &';
        }
    }

    /**
     * Get the mutex path for the scheduled command.
     *
     * @return string
     */
    protected function mutexPath()
    {
        return storage_path('schedule-' .md5( $this->expression .$this->command));
    }

    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @param  Application  $app
     * @return bool
     */
    public function isDue(Application $app)
    {
        if (!$this->runsInMaintenanceMode() && $app->isDownForMaintenance()) {
            return false;
        }

        return $this->expressionPasses() && $this->filtersPass($app) && $this->runsInEnvironment($app->environment());
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool
     */
    protected function expressionPasses()
    {
        $date = Carbon::now();

        if ($this->timezone) {
            $date->setTimezone($this->timezone);
        }

        return CronExpression::factory($this->expression)->isDue($date->toDateTimeString());
    }

    /**
     * Determine if the filters pass for the event.
     *
     * @param  Application  $app
     * @return bool
     */
    protected function filtersPass(Application $app)
    {
        if (($this->filter && !$app->call($this->filter)) || ($this->reject && $app->call($this->reject))) {
            return false;
        }

        return true;
    }

    /**
     * Determine if the event runs in the given environment.
     *
     * @param  string  $environment
     * @return bool
     */
    public function runsInEnvironment($environment)
    {
        return empty($this->environments) || in_array($environment, $this->environments);
    }

    /**
     * Determine if the event runs in maintenance mode.
     *
     * @return bool
     */
    public function runsInMaintenanceMode()
    {
        return $this->evenInMaintenanceMode;
    }

    /**
     * The Cron expression representing the event's frequency.
     *
     * @param  string  $expression
     * @return $this
     */
    public function cron($expression)
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Schedule the event to run hourly.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->cron('0 * * * * *');
    }

    /**
     * Schedule the event to run daily.
     *
     * @return $this
     */
    public function daily()
    {
        return $this->cron('0 0 * * * *');
    }

    /**
     * Schedule the command at a given time.
     *
     * @param  string  $time
     * @return $this
     */
    public function at($time)
    {
        return $this->dailyAt($time);
    }

    /**
     * Schedule the event to run daily at a given time (10:00, 19:30, etc).
     *
     * @param  string  $time
     * @return $this
     */
    public function dailyAt($time)
    {
        $segments = explode(':', $time);

        //
        $hours = (int)$segments[0];

        $minutes = (count($segments) == 2) ? (int)$segments[1] : '0';

        return $this->spliceIntoPosition(2, $hours)->spliceIntoPosition(1, $minutes);
    }

    /**
     * Schedule the event to run twice daily.
     *
     * @param  int  $first
     * @param  int  $second
     * @return $this
     */
    public function twiceDaily($first = 1, $second = 13)
    {
        $hours = $first .','  .$second;

        return $this->spliceIntoPosition(1, 0)->spliceIntoPosition(2, $hours);
    }

    /**
     * Schedule the event to run weekly.
     *
     * @return $this
     */
    public function weekly()
    {
        return $this->cron('0 0 * * 0 *');
    }

    /**
     * Schedule the event to run weekly on a given day and time.
     *
     * @param  int  $day
     * @param  string  $time
     * @return $this
     */
    public function weeklyOn($day, $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(5, $day);
    }

    /**
     * Schedule the event to run monthly.
     *
     * @return $this
     */
    public function monthly()
    {
        return $this->cron('0 0 1 * * *');
    }

    /**
     * Schedule the event to run yearly.
     *
     * @return $this
     */
    public function yearly()
    {
        return $this->cron('0 0 1 1 * *');
    }

    /**
     * Schedule the event to run every minute.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->cron('* * * * * *');
    }

    /**
     * Schedule the event to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->cron('*/5 * * * * *');
    }

    /**
     * Schedule the event to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->cron('*/10 * * * * *');
    }

    /**
     * Schedule the event to run every thirty minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->cron('0,30 * * * * *');
    }

    /**
     * Set the days of the week the command should run on.
     *
     * @param  array|mixed  $days
     * @return $this
     */
    public function days($days)
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * Set the timezone the date should be evaluated on.
     *
     * @param  \DateTimeZone|string  $timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Set which user the command should run as.
     *
     * @param  string  $user
     * @return $this
     */
    public function user($user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * Limit the environments the command should run in.
     *
     * @param  array|mixed  $environments
     * @return $this
     */
    public function environments($environments)
    {
        $this->environments = is_array($environments) ? $environments : func_get_args();

        return $this;
    }

    /**
     * State that the command should run even in maintenance mode.
     *
     * @return $this
     */
    public function evenInMaintenanceMode()
    {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    /**
     * Do not allow the event to overlap each other.
     *
     * @return $this
     */
    public function withoutOverlapping()
    {
        $this->withoutOverlapping = true;

        return $this->skip(function () {
            return file_exists($this->mutexPath());
        });
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function when(Closure $callback)
    {
        $this->filter = $callback;

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function skip(Closure $callback)
    {
        $this->reject = $callback;

        return $this;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param  string  $location
     * @param  bool  $append
     * @return $this
     */
    public function sendOutputTo($location, $append = false)
    {
        $this->output = $location;

        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Append the output of the command to a given location.
     *
     * @param  string  $location
     * @return $this
     */
    public function appendOutputTo($location)
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * E-mail the results of the scheduled operation.
     *
     * @param  array|mixed  $addresses
     * @return $this
     *
     * @throws \LogicException
     */
    public function emailOutputTo($addresses)
    {
        if (is_null($this->output) || ($this->output == $this->getDefaultOutput())) {
            throw new LogicException('Must direct output to a file in order to e-mail results.');
        }

        $addresses = is_array($addresses) ? $addresses : func_get_args();

        return $this->then(function (Mailer $mailer) use ($addresses) {
            $this->emailOutput($mailer, $addresses);
        });
    }

    /**
     * E-mail the output of the event to the recipients.
     *
     * @param  Mailer  $mailer
     * @param  array  $addresses
     * @return void
     */
    protected function emailOutput(Mailer $mailer, $addresses)
    {
        $mailer->raw(file_get_contents($this->output), function ($message) use ($addresses) {
            $message->subject($this->getEmailSubject());

            foreach ($addresses as $address) {
                $message->to($address);
            }
        });
    }

    /**
     * Get the e-mail subject line for output results.
     *
     * @return string
     */
    protected function getEmailSubject()
    {
        if ($this->description) {
            return 'Scheduled Job Output (' .$this->description .')'; 
        }

        return 'Scheduled Job Output';
    }

    /**
     * Register a callback to be called before the operation.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function before(Closure $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function after(Closure $callback)
    {
        return $this->then($callback);
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param  \Closure  $callback
     * @return $this
     */
    public function then(Closure $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param  string  $description
     * @return $this
     */
    public function name($description)
    {
        return $this->description($description);
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param  string  $description
     * @return $this
     */
    public function description($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Splice the given value into the given position of the expression.
     *
     * @param  int  $position
     * @param  string  $value
     * @return $this
     */
    protected function spliceIntoPosition($position, $value)
    {
        $segments = explode(' ', $this->expression);

        //
        $key = $position - 1;

        $segments[$key] = $value;

        return $this->cron(implode(' ', $segments));
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string
     */
    public function getSummaryForDisplay()
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return $this->buildCommand();
    }

    /**
     * Get the Cron expression for the event.
     *
     * @return string
     */
    public function getExpression()
    {
        return $this->expression;
    }
}

class Schedule
{
    /**
     * All of the events on the schedule.
     *
     * @var array
     */
    protected $events = array();


    /**
     * Add a new callback event to the schedule.
     *
     * @param  string  $callback
     * @param  array   $parameters
     * @return Event
     */
    public function call($callback, array $parameters = array())
    {
        $this->events[] = $event = new CallbackEvent($callback, $parameters);

        return $event;
    }

    /**
     * Add a new Artisan command event to the schedule.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return Event
     */
    public function command($command, array $parameters = array())
    {
        $binary = ProcessUtils::escapeArgument((new PhpExecutableFinder)->find(false));

        if (defined('HHVM_VERSION')) {
            $binary .= ' --php';
        }

        if (defined('FORGE_BINARY')) {
            $forge = ProcessUtils::escapeArgument(FORGE_BINARY);
        } else {
            $forge = 'forge';
        }

        return $this->exec("{$binary} {$forge} {$command}", $parameters);
    }

    /**
     * Add a new command event to the schedule.
     *
     * @param  string  $command
     * @param  array  $parameters
     * @return Event
     */
    public function exec($command, array $parameters = array())
    {
        if (count($parameters)) {
             $command .= ' ' .$this->compileParameters($parameters);
        }

        $this->events[] = $event = new Event($command);

        return $event;
    }

    /**
     * Compile parameters for a command.
     *
     * @param  array  $parameters
     * @return string
     */
    protected function compileParameters(array $parameters)
    {
        return collect($parameters)->map(function ($value, $key) {
            if (is_numeric($key)) {
                return $value;
            }

             return $key .'=' .(is_numeric($value) ? $value : ProcessUtils::escapeArgument($value));
        })->implode(' ');
    }

    /**
     * Get all of the events on the schedule.
     *
     * @return array
     */
    public function events()
    {
        return $this->events;
    }

    /**
     * Get all of the events on the schedule that are due.
     *
     * @param  Application  $app
     * @return array
     */
    public function dueEvents(Application $app)
    {
        return array_filter($this->events, function ($event) use ($app) {
            return $event->isDue($app);
        });
    }
}

class
ScheduleRunCommand extends Command
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'schedule:run';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run the scheduled commands';

    /**
     * The schedule instance.
     *
     * @var \Mini\Console\Scheduling\Schedule
     */
    protected $schedule;


    /**
     * Create a new command instance.
     *
     * @param  \Biurad\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;

        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function fire()
    {
        $events = $this->schedule->dueEvents($this->container);

        foreach ($events as $event) {
            $this->line('<info>Running scheduled co mmand:</info> ' .$event->getSummaryForDisplay());

            $event->run($this->container);
        }

        if (count($events) === 0) {
            $this->info('No scheduled commands are ready to run.');
        }
    }
}

interface KernelInterface
{
    //
}