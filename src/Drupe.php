<?php

namespace Pecan;

use Evenement\EventEmitterTrait;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;

/**
 * Drupe is a standalone EventEmitter for I/O Streams.
 * It's Pecan without the Shell! (-;
 *
 * @event running
 * @event data
 * @event error
 * @event close
 */
class Drupe
{
    use EventEmitterTrait;

    /** @var \Pecan\Readline */
    protected $readline;

    /** @var \Pecan\Console\ConsoleInterface */
    protected $console;

    /** @var \Pecan\Console\Input */
    protected $input;

    /** @var boolean */
    private $running = false;

    /** @var integer */
    private $exitCode = 0;

    /**
     * Constructor.
     * @param string $history
     */
    public function __construct($history = null)
    {
        $history        = $history ?: getenv('HOME').'/.drupe_history';
        $this->readline = new Readline($history);

        $this->once('running', function() {
            $this->console = $this->readline->console();
            $this->readline->setPrompt('drupe> ');
        });
    }

    /**
     * Starts the shell.
     *
     * @param LoopInterface $loop
     * @param float $interval
     * @return LoopInterface Returns the provided loop to allow for fluent calls to "run()"
     * @throws \LogicException if the shell is already running.
     */
    public function start(LoopInterface $loop, $interval = 0.001)
    {
        if ($this->running) {
            throw new \LogicException('The shell is already running.');
        }

        $this->running  = true;

        $this->readline->on('error', function ($error, $object) {
            $this->emit('error', [ $error, $object, $this ]);
        });

        $this->readline->on('line', function ($command) {
            $this->readline->addHistory($command);
            $this->emit('data', [ $command, $this ]);
        });

        $this->readline->on('close', function() {
            $this->close();
        });

        $this->on('close', function ($exitCode = 0) {
            if ($this->running) {
                $this->exitCode = $exitCode;
                $this->running   = false;
            }
        });

        $loop->addPeriodicTimer($interval, [ $this, 'checkRunning' ]);

        $this->readline->start($loop);

        $this->emit('running', [ $this ]);

        return $loop;
    }

    /**
     * @return Console\ConsoleInterface|null
     */
    public function console()
    {
        return $this->readline->console();
    }

    /**
     * Closes the shell.
     *
     * @param int $exitExitCode
     * @return $this
     */
    public function close($exitExitCode = 0)
    {
        if ($this->running) {
            $this->emit('close', [ $exitExitCode, $this ]);
        }

        return $this;
    }

    /**
     * Sets the prompt on the terminal, if running.
     *
     * @param string $prompt
     * @return $this
     */
    public function setPrompt($prompt)
    {
        if ($this->running) {
            $this->readline->setPrompt($prompt);
        }

        return $this;
    }

    /**
     * @see Readline::prompt()
     */
    public function prompt()
    {
        if ($this->running) {
            $this->readline->prompt();
        }
        return $this;
    }

    /**
     * Checks whether or not the shell is still running.
     *
     * @param Timer $timer
     */
    public function checkRunning(Timer $timer)
    {
        if (!$this->running) {
            $timer->cancel();
            // @codeCoverageIgnoreStart
            exit($this->exitCode);
            // @codeCoverageIgnoreEnd
        }
    }

}
